import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'local/day_key.dart';
import 'providers.dart';
import 'review_queue.dart';
import 'seq_counter.dart';

/// Offline-first review pipeline. Every answer is recorded locally first (so it
/// survives no-network and app kills), then flushed to `/reviews/batch` as a
/// batch. Uploads are idempotent by the client ULID, so retries are free and a
/// 2xx lets us drop the sent ids — including `unknown`/`duplicate` ones, which
/// would never be accepted on a retry anyway.
///
/// The client sends the RAW answer (mode, response text, hint, latency) with a per-user monotonic
/// [PendingReview.clientSeq] from `seq_review`; the server grades and folds in sequence order.
class ReviewSync {
  ReviewSync(
    this._api,
    this._queue,
    this._seq,
    this._ref, {
    this.maxQueue = _defaultMaxQueue,
    this.backoffBase = _defaultBackoffBase,
    this.backoffMax = _defaultBackoffMax,
  });

  /// Server caps a batch at 200; 100 leaves headroom for an offline backlog.
  static const int _chunkSize = 100;

  /// How many un-uploaded answers we are willing to hold. A day-long server fault (the 500 from a
  /// poisoned cache entry was exactly that) used to grow this without limit.
  static const int _defaultMaxQueue = 500;

  /// Transient-failure backoff: 5 s, 10 s, 20 s … capped at 5 min. Without it every flush trigger
  /// — app resume, network change, each scheduling answer — hammered a failing server.
  static const Duration _defaultBackoffBase = Duration(seconds: 5);
  static const Duration _defaultBackoffMax = Duration(minutes: 5);

  /// Overridable so tests can drive the cap and the backoff without waiting on wall-clock time.
  final int maxQueue;
  final Duration backoffBase;
  final Duration backoffMax;

  final ApiClient _api;
  final ReviewQueue _queue;
  final SeqCounter _seq;
  final Ref _ref;

  bool _flushing = false;
  int _failures = 0;
  DateTime? _retryAfter;

  /// True when the queue is at its cap and holds scheduling answers we refuse to drop — i.e. real
  /// progress is stuck on the device. Surfaced to the user; practice-only backlog never sets it.
  final ValueNotifier<bool> stuck = ValueNotifier<bool>(false);

  Future<int> pendingCount() => _queue.length();

  /// Import the legacy Keychain blob once. Called on app start, before anything reads the queue.
  Future<void> migrate() => _queue.migrateFromKeychain();

  /// Persist one raw answer (server grades it), then opportunistically flush. [clientSeq] is
  /// assigned here from the monotonic `seq_review` counter so ordering survives a queue clear.
  Future<void> record({
    required String termId,
    required String exerciseMode,
    required String response,
    bool usedHint = false,
    bool isPractice = false,
    int? latencyMs,
    String? sessionId,
    int? ladderStep,
  }) async {
    await _enforceCap();
    // One insert on the background isolate. Awaited, so the answer is durable before this returns.
    await _queue.add(
      PendingReview(
        id: ApiClient.ulid(),
        termId: termId,
        exerciseMode: exerciseMode,
        response: response,
        clientSeq: await _seq.next(SeqCounter.review),
        answeredAt: DateTime.now().toUtc().toIso8601String(),
        usedHint: usedHint,
        isPractice: isPractice,
        latencyMs: latencyMs,
        sessionId: sessionId,
        ladderStep: ladderStep,
      ),
    );
    // Count this review toward today's local activity tally (Progress screen / session-summary goal
    // card). Real session reviews only — free practice is excluded (Training Loop v2 / F17: practice
    // has zero influence on the daily goal and activity), as is triage. Local, not synced;
    // independent of upload success.
    if (!isPractice) {
      await _ref.read(appDatabaseProvider).bumpDailyActivity(localDayKey(DateTime.now()));
    }
    // Nobody is waiting on a practice answer: it moves no schedule, so there is no «увидишь снова
    // через N дней» to fill in and nothing to pull back. It rides the durable queue and goes up as
    // ONE batch when the session ends (the summary flushes) instead of one HTTP round trip per
    // word — which is what the batch endpoint was for in the first place. Offline safety is
    // unchanged: the queue is persisted before this returns, and app start, network return, app
    // resume and the summary all flush it. A scheduling answer still uploads immediately, because
    // the feedback line genuinely waits on the server's schedule.
    if (!isPractice) unawaited(flush());
  }

  /// Keep the queue under [maxQueue]. Practice answers are the only ones eligible to be dropped:
  /// they move no progress, so losing the oldest of them costs a log row. A scheduling answer
  /// carries progress the server has not seen — it is NEVER dropped. When only those remain, we
  /// stop trimming and raise [stuck] instead, so the user is told rather than silently losing work.
  Future<void> _enforceCap() async {
    final length = await _queue.length();
    if (length < maxQueue) {
      if (stuck.value) stuck.value = false;
      return;
    }
    final room = length - maxQueue + 1;
    final droppable = await _queue.oldestPracticeIds(room);
    if (droppable.isNotEmpty) {
      await _queue.removeIds(droppable);
      debugPrint('ReviewSync: queue at cap, dropped ${droppable.length} oldest practice answer(s)');
    }
    stuck.value = droppable.length < room;
  }

  /// Upload the pending queue in chunks (see TriageSync.flush for the full rationale).
  /// Success drops the chunk; a permanent reject (422/413) drops it with a log without blocking
  /// the rest; a transient failure stops and keeps the remainder for next time. Order rides on
  /// each answer's `client_seq`; chunks are still sent in order.
  Future<void> flush() async {
    if (_flushing) return;
    // Back off after a transient failure. Every trigger (app start, resume, network change, each
    // scheduling answer) calls flush, so without this a failing server is hammered for as long as
    // it stays broken — which, for the poisoned-cache 500, was a day.
    final retryAfter = _retryAfter;
    if (retryAfter != null && DateTime.now().isBefore(retryAfter)) return;

    final snapshot = await _queue.load();
    if (snapshot.isEmpty) {
      _resetBackoff();
      return;
    }

    _flushing = true;
    try {
      final drop = <String>{};
      var transientFailure = false;
      // Did anything we managed to upload actually move server-side progress? Practice answers
      // never do — SubmitReviewsHandler filters them out before folding — so a sync after a pure
      // practice batch is a guaranteed-empty round trip. Measured on device: the delta brought
      // back zero rows after every practice session, not once. Skipping it takes two network calls
      // per answer out of the hot path and changes nothing on screen.
      var scheduledAny = false;
      for (var i = 0; i < snapshot.length; i += _chunkSize) {
        final chunk = snapshot.sublist(i, min(i + _chunkSize, snapshot.length));
        try {
          await _api.submitReviews(chunk);
          drop.addAll(chunk.map((e) => e.id)); // success → drop
          scheduledAny = scheduledAny || chunk.any((e) => !e.isPractice);
        } on DioException catch (e) {
          if (!isPermanentReject(e)) {
            transientFailure = true;
            break; // transient → keep this + remaining chunks
          }
          drop.addAll(chunk.map((e) => e.id)); // 422/413 → drop with a log, don't block the rest
          debugPrint(
            'ReviewSync: dropped ${chunk.length} rejected answer(s) '
            '(${e.response?.statusCode}): ${e.response?.data}',
          );
        } catch (_) {
          transientFailure = true;
          break; // unknown → treat as transient
        }
      }
      if (transientFailure) {
        _noteFailure();
      } else {
        _resetBackoff();
      }
      if (drop.isNotEmpty) {
        await _queue.removeIds(drop);
        // Only a scheduling answer changed anything server-side; pull that back into the local
        // mirror (stats + per-collection progress read from there). Study cards stay on the
        // network. A pure practice batch skips this — see [scheduledAny].
        if (scheduledAny) {
          _ref.read(syncServiceProvider).sync();
          _ref.invalidate(dueCardsProvider);
        }
      }
    } finally {
      _flushing = false;
    }
  }

  void _noteFailure() {
    _failures++;
    final ms = backoffBase.inMilliseconds * (1 << (_failures - 1).clamp(0, 10));
    final wait = Duration(milliseconds: min(ms, backoffMax.inMilliseconds));
    _retryAfter = DateTime.now().add(wait);
  }

  void _resetBackoff() {
    _failures = 0;
    _retryAfter = null;
  }
}
