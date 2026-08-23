import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'local/app_database.dart';

/// Offline-first pipeline for «this run was played to its end».
///
/// `study_sessions.ended_at` and `stats` had no writer at all: a grep over the backend found one
/// mention of the column, the migration that declares it. Every run therefore looked abandoned, and
/// neither «did the learner finish this» nor «how long did it take» could be asked of the data
/// (QA-12).
///
/// Its own durable queue, alongside [ReviewSync] and [ExposureSync] and for the same reason: the
/// summary screen is reachable in airplane mode, and a completion that only existed in memory would
/// be lost exactly when the run was most worth recording. The queue is keyed by SESSION, so a
/// re-send is the same event rather than a later one, and the server's write is conditional on the
/// session still being open — the two idempotencies agree without either trusting the other.
///
/// It deliberately carries no counters. The server recomputes what happened from the run's own
/// append-only logs; a client-supplied summary could only ever disagree with the log it summarises.
class SessionCompletionSync {
  SessionCompletionSync(this._api, this._db);

  /// Transient-failure backoff, matching the sibling queues: 5 s, 10 s, 20 s … capped at 5 min.
  static const Duration _backoffBase = Duration(seconds: 5);
  static const Duration _backoffMax = Duration(minutes: 5);

  final ApiClient _api;
  final AppDatabase _db;

  bool _flushing = false;
  int _failures = 0;
  DateTime? _retryAfter;

  /// Record that [sessionId] was played to its summary, then try to send it.
  Future<void> record({required String sessionId, DateTime? endedAt}) async {
    await _db.enqueueCompletion(
      SessionCompletionQueueRowsCompanion.insert(
        sessionId: sessionId,
        endedAt: (endedAt ?? DateTime.now()).toUtc().toIso8601String(),
      ),
    );
    unawaited(flush());
  }

  Future<int> pendingCount() async => (await _db.completionQueue()).length;

  /// Upload the pending completions, oldest first. A completion is one small request per session, so
  /// there is nothing to chunk; what matters is that a transient failure keeps the row for the next
  /// trigger and a permanent reject drops it rather than retrying forever.
  Future<void> flush() async {
    if (_flushing) return;
    final retryAfter = _retryAfter;
    if (retryAfter != null && DateTime.now().isBefore(retryAfter)) return;

    final rows = await _db.completionQueue();
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
          await _api.completeSession(sessionId: row.sessionId, endedAt: row.endedAt);
          drop.add(row.sessionId);
        } on DioException catch (e) {
          if (!isPermanentReject(e)) {
            transientFailure = true;
            break;
          }
          drop.add(row.sessionId);
          debugPrint(
            'SessionCompletionSync: dropped a rejected completion '
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
      if (drop.isNotEmpty) await _db.dequeueCompletions(drop);
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
