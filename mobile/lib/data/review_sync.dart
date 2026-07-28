import 'dart:async';

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

  /// Upload the whole pending batch. Safe to call anytime (guarded against
  /// re-entrancy); keeps everything on failure for the next attempt.
  Future<void> flush() async {
    if (_flushing) return;
    final list = await _list();
    if (list.isEmpty) return;

    _flushing = true;
    try {
      final batch = List<PendingReview>.from(list);
      await _api.submitReviews(batch);

      final sent = batch.map((e) => e.id).toSet();
      list.removeWhere((e) => sent.contains(e.id));
      await _queue.save(list);

      _ref.invalidate(statsProvider);
      _ref.invalidate(dueCardsProvider);
    } catch (_) {
      // Offline or server error — leave the queue intact and try again later.
    } finally {
      _flushing = false;
    }
  }
}
