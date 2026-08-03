import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'models.dart';
import 'providers.dart';
import 'review_queue.dart';

/// Offline-first review pipeline. Every answer is recorded locally first (so it
/// survives no-network and app kills), then flushed to `/reviews/batch` as a
/// batch. Uploads are idempotent by the client ULID, so retries are free and a
/// 2xx lets us drop the sent ids — including `unknown`/`duplicate` ones, which
/// would never be accepted on a retry anyway.
class ReviewSync {
  ReviewSync(this._api, this._queue, this._ref);

  /// Server caps a batch at 200; 100 leaves headroom for an offline backlog.
  static const int _chunkSize = 100;

  final ApiClient _api;
  final ReviewQueue _queue;
  final Ref _ref;

  List<PendingReview>? _mem; // in-memory mirror of the persisted queue
  bool _flushing = false;

  Future<List<PendingReview>> _list() async => _mem ??= await _queue.load();

  Future<int> pendingCount() async => (await _list()).length;

  /// Persist one graded answer, then opportunistically flush.
  Future<void> record(Rating grade, String termId, {int? latencyMs}) async {
    final list = await _list();
    list.add(PendingReview(
      id: ApiClient.ulid(),
      termId: termId,
      grade: grade.grade,
      answeredAt: DateTime.now().toUtc().toIso8601String(),
      latencyMs: latencyMs,
    ));
    await _queue.save(list);
    unawaited(flush());
  }

  /// Upload the pending queue in chunks (see TriageSync.flush for the full rationale).
  /// Success drops the chunk; a permanent reject (422/413) drops it with a log without blocking
  /// the rest; a transient failure stops and keeps the remainder for next time. Order rides on
  /// client_seq once the review pipeline supplies it; chunks are still sent in order.
  ///
  /// NOTE: the review pipeline is still stale (pre-`client_seq`, pre-raw-answer), so every flush
  /// currently 422s and the records are dropped here rather than piling up. Full wiring lands
  /// with the exercise/session screens.
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
