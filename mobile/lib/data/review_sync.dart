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
  ReviewSync(this._api, this._queue, this._seq, this._ref);

  /// Server caps a batch at 200; 100 leaves headroom for an offline backlog.
  static const int _chunkSize = 100;

  final ApiClient _api;
  final ReviewQueue _queue;
  final SeqCounter _seq;
  final Ref _ref;

  List<PendingReview>? _mem; // in-memory mirror of the persisted queue
  bool _flushing = false;

  Future<List<PendingReview>> _list() async => _mem ??= await _queue.load();

  Future<int> pendingCount() async => (await _list()).length;

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
  }) async {
    final list = await _list();
    list.add(PendingReview(
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
    ));
    await _queue.save(list);
    // Count this review toward today's local activity tally (Progress screen / session-summary goal
    // card). Real session reviews only — free practice is excluded (Training Loop v2 / F17: practice
    // has zero influence on the daily goal and activity), as is triage. Local, not synced;
    // independent of upload success.
    if (!isPractice) {
      await _ref.read(appDatabaseProvider).bumpDailyActivity(localDayKey(DateTime.now()));
    }
    unawaited(flush());
  }

  /// Upload the pending queue in chunks (see TriageSync.flush for the full rationale).
  /// Success drops the chunk; a permanent reject (422/413) drops it with a log without blocking
  /// the rest; a transient failure stops and keeps the remainder for next time. Order rides on
  /// each answer's `client_seq`; chunks are still sent in order.
  Future<void> flush() async {
    if (_flushing) return;
    final list = await _list();
    if (list.isEmpty) return;

    _flushing = true;
    try {
      final snapshot = List<PendingReview>.from(list);
      final drop = <String>{};
      for (var i = 0; i < snapshot.length; i += _chunkSize) {
        final chunk = snapshot.sublist(i, min(i + _chunkSize, snapshot.length));
        try {
          await _api.submitReviews(chunk);
          drop.addAll(chunk.map((e) => e.id)); // success → drop
        } on DioException catch (e) {
          if (!isPermanentReject(e)) break; // transient → keep this + remaining chunks
          drop.addAll(chunk.map((e) => e.id)); // 422/413 → drop with a log, don't block the rest
          debugPrint('ReviewSync: dropped ${chunk.length} rejected answer(s) '
              '(${e.response?.statusCode}): ${e.response?.data}');
        } catch (_) {
          break; // unknown → treat as transient
        }
      }
      if (drop.isNotEmpty) {
        list.removeWhere((e) => drop.contains(e.id));
        await _queue.save(list);
        // Uploaded answers changed server-side progress; pull it back into the local mirror
        // (stats + per-collection progress read from there). Study cards stay on the network.
        _ref.read(syncServiceProvider).sync();
        _ref.invalidate(dueCardsProvider);
      }
    } finally {
      _flushing = false;
    }
  }
}
