import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'local/app_database.dart';

/// Offline-first pipeline for the two acts that decide what the trainer works on: «Учить это слово»
/// and «Убрать из изучения».
///
/// Its own durable queue, alongside [ReviewSync], [ExposureSync] and [SessionCompletionSync], and
/// for a sharper reason than any of them: these taps are the ONLY way a word reaches the trainer.
/// A tap lost in the metro would cost the learner a word they deliberately asked for — and they
/// would never notice, because the local mirror already shows it enrolled.
///
/// The queue holds the DESIRED MEMBERSHIP per term rather than a log of events, because membership
/// is a set and both verbs are idempotent: enrol-then-remove offline collapses into one row saying
/// «out», and replaying that lands exactly where replaying both would have. There is no ordering to
/// protect, so there is no `client_seq` here — the one place in this app where that is true.
///
/// Every write goes to the LOCAL mirror first and unconditionally (see [AppDatabase.enrollLocally]):
/// the screen has to change under the finger, and the pool has to be right in airplane mode. This
/// class only carries the same decision to the server; `/sync` then brings back the server's own
/// answer, which is the authority.
class PoolSync {
  PoolSync(this._api, this._db, {this.onPoolChanged});

  /// Transient-failure backoff, matching the sibling queues: 5 s, 10 s, 20 s … capped at 5 min.
  static const Duration _backoffBase = Duration(seconds: 5);
  static const Duration _backoffMax = Duration(minutes: 5);

  final ApiClient _api;
  final AppDatabase _db;

  /// Called after the local half of an enrolment lands, BEFORE the server has heard about it.
  ///
  /// The day is a server aggregate the client caches, and taking a word into the queue changes it —
  /// so the cache has to be told. Optional, because the queue must keep working in the tests and in
  /// the background paths that have no screen behind them.
  final Future<void> Function()? onPoolChanged;

  bool _flushing = false;
  int _failures = 0;
  DateTime? _retryAfter;

  /// «Учить это слово»: enrol locally, queue the same decision for the server, try to send it.
  ///
  /// [acquisition]/[learningStep] describe where a NEWLY created row starts on the ladder. The
  /// button always means «rung 0, never shown»; the triage pipeline enrols with its own values.
  Future<void> enroll(String termId) async {
    final now = DateTime.now();
    await _db.enrollLocally(termId, now);
    await _db.enqueuePoolChange(termId, enrolled: true, at: now);
    unawaited(flush().then((_) => onPoolChanged?.call()));
  }

  /// «Убрать из изучения»: a PAUSE. One column to null locally, the same intent queued, and nothing
  /// else touched — the rung, the counter, the schedule and the answer history all stand.
  Future<void> unenroll(String termId) async {
    final now = DateTime.now();
    await _db.unenrollLocally(termId, now);
    await _db.enqueuePoolChange(termId, enrolled: false, at: now);
    unawaited(flush().then((_) => onPoolChanged?.call()));
  }

  Future<int> pendingCount() async => (await _db.poolQueue()).length;

  /// Send the pending decisions, oldest first. One small request per term, so there is nothing to
  /// chunk; what matters is that a transient failure keeps the row for the next trigger and a
  /// permanent reject drops it rather than retrying forever.
  Future<void> flush() async {
    if (_flushing) return;
    final retryAfter = _retryAfter;
    if (retryAfter != null && DateTime.now().isBefore(retryAfter)) return;

    final rows = await _db.poolQueue();
    if (rows.isEmpty) {
      _resetBackoff();
      return;
    }

    _flushing = true;
    try {
      final drop = <String>{};
      var transientFailure = false;

      for (final row in rows) {
        try {
          row.enrolled ? await _api.enrollTerm(row.termId) : await _api.unenrollTerm(row.termId);
          drop.add(row.termId);
        } on DioException catch (e) {
          if (!isPermanentReject(e)) {
            transientFailure = true;
            break;
          }
          // A 404 (the term is gone) or a 422 would never succeed on a retry. The local mirror
          // keeps whatever the learner chose; the next sync is what reconciles it.
          drop.add(row.termId);
          debugPrint(
            'PoolSync: dropped a rejected pool change '
            '(${e.response?.statusCode}): ${e.response?.data}',
          );
        } catch (_) {
          transientFailure = true;
          break;
        }
      }

      if (transientFailure) {
        _noteFailure();
      } else {
        _resetBackoff();
      }
      if (drop.isNotEmpty) await _db.dequeuePoolChanges(drop);
    } finally {
      _flushing = false;
    }
  }

  void _noteFailure() {
    _failures++;
    final ms = _backoffBase.inMilliseconds * (1 << (_failures - 1).clamp(0, 10));
    _retryAfter = DateTime.now().add(Duration(milliseconds: min(ms, _backoffMax.inMilliseconds)));
  }

  void _resetBackoff() {
    _failures = 0;
    _retryAfter = null;
  }
}
