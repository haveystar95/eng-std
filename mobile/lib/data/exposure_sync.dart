import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'local/app_database.dart';
import 'providers.dart';

/// One intro acknowledgement waiting to be uploaded: the word was SHOWN.
///
/// No mode, no response, no grade, no `client_seq` — an exposure is not an answer, and the shape
/// says so. Sequence numbers exist to order events whose ORDER changes the outcome; this one is
/// idempotent on the pair, so a re-upload lands the same row and replay order cannot matter.
class PendingExposure {
  const PendingExposure({required this.termId, required this.shownAt, this.sessionId});

  final String termId;
  final String shownAt; // ISO-8601 UTC, reference-only (device clock)
  final String? sessionId;

  /// The exact shape `/reviews/batch` expects under `exposures`.
  Map<String, dynamic> toBatchJson() => {
        'term_id': termId,
        'shown_at': shownAt,
        if (sessionId != null) 'session_id': sessionId,
      };
}

/// Offline-first pipeline for intro cards, alongside [ReviewSync] and for the same reasons: the
/// meeting is recorded locally first, so it survives no-network and app kills, then rides
/// `/reviews/batch` — the SAME endpoint and the same session-adoption path as the answers it sits
/// between, which is what lets an offline session upload as one whole thing.
///
/// It is a separate queue from the reviews one because it is a separate KIND of event. Folding an
/// exposure into the review queue would mean a row with a null grade and an empty response, and the
/// review log is exactly where a retrieval that never happened must not appear.
class ExposureSync {
  ExposureSync(this._api, this._db, this._ref);

  /// The server caps a batch at 200; the same headroom the review flush leaves.
  static const int _chunkSize = 100;

  /// Transient-failure backoff, matching [ReviewSync]: 5 s, 10 s, 20 s … capped at 5 min.
  static const Duration _backoffBase = Duration(seconds: 5);
  static const Duration _backoffMax = Duration(minutes: 5);

  final ApiClient _api;
  final AppDatabase _db;
  final Ref _ref;

  bool _flushing = false;
  int _failures = 0;
  DateTime? _retryAfter;

  /// Record that a word was shown, and step it onto the first recognition rung LOCALLY.
  ///
  /// Both writes are idempotent on the pair — the queue by its primary key, the ladder by refusing
  /// to move a pair that has already left `new` — so a double tap, a replayed batch or a resumed
  /// session all land the same state. The local step is what lets this word's recognition cards,
  /// later in this same session, know it has been met with the network off the whole time.
  Future<void> record({required String termId, String? sessionId}) async {
    final now = DateTime.now();
    await _db.enqueueExposure(ExposureQueueRowsCompanion.insert(
      termId: termId,
      shownAt: now.toUtc().toIso8601String(),
      sessionId: Value(sessionId),
    ));
    await _db.markIntroduced(termId, now);
    unawaited(flush());
  }

  Future<int> pendingCount() async => (await _db.exposureQueue()).length;

  /// Upload the pending exposures in chunks. Success drops the chunk; a permanent reject (422/413)
  /// drops it with a log; a transient failure keeps the remainder for the next trigger.
  Future<void> flush() async {
    if (_flushing) return;
    final retryAfter = _retryAfter;
    if (retryAfter != null && DateTime.now().isBefore(retryAfter)) return;

    final rows = await _db.exposureQueue();
    if (rows.isEmpty) {
      _resetBackoff();
      return;
    }

    _flushing = true;
    try {
      final snapshot = [
        for (final r in rows)
          PendingExposure(termId: r.termId, shownAt: r.shownAt, sessionId: r.sessionId),
      ];
      final drop = <String>{};
      var transientFailure = false;

      for (var i = 0; i < snapshot.length; i += _chunkSize) {
        final chunk = snapshot.sublist(i, min(i + _chunkSize, snapshot.length));
        try {
          await _api.submitExposures(chunk);
          drop.addAll(chunk.map((e) => e.termId));
        } on DioException catch (e) {
          if (!isPermanentReject(e)) {
            transientFailure = true;
            break;
          }
          drop.addAll(chunk.map((e) => e.termId));
          debugPrint('ExposureSync: dropped ${chunk.length} rejected exposure(s) '
              '(${e.response?.statusCode}): ${e.response?.data}');
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
      if (drop.isNotEmpty) {
        await _db.dequeueExposures(drop);
        // An exposure DOES move server-side state — it steps the pair onto the ladder's first
        // recognition rung and spends the day's new-term quota — so pull that back, unlike a pure
        // practice batch which moves nothing.
        _ref.read(syncServiceProvider).sync();
      }
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
