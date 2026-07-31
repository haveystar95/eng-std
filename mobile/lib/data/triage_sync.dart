import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'models.dart';
import 'providers.dart';
import 'triage_queue.dart';

/// Offline-first triage pipeline. Every swipe is recorded on disk first (so it
/// survives no-network and app kills), then flushed to `/triage/batch` as a
/// batch. Uploads are idempotent by the client ULID, so retries are free and a
/// 2xx lets us drop the sent ids.
class TriageSync {
  TriageSync(this._api, this._queue, this._ref);

  final ApiClient _api;
  final TriageQueue _queue;
  final Ref _ref;

  List<PendingTriage>? _mem; // in-memory mirror of the persisted queue
  bool _flushing = false;

  Future<List<PendingTriage>> _list() async => _mem ??= await _queue.load();

  Future<int> pendingCount() async => (await _list()).length;

  /// Term ids that already have a swipe queued locally (sent or not). The deck
  /// subtracts these so a swiped-but-not-yet-uploaded card is not shown again
  /// after a restart — the server can't exclude what it hasn't received.
  Future<Set<String>> pendingTermIds() async => (await _list()).map((e) => e.termId).toSet();

  /// Persist one swipe (client ULID, client-measured latency), then flush.
  Future<void> record({
    required String termId,
    required TriageVerdict verdict,
    required String? collectionId,
    required int? latencyMs,
  }) async {
    final list = await _list();
    list.add(PendingTriage(
      id: ApiClient.ulid(),
      termId: termId,
      verdict: verdict.value,
      collectionId: collectionId,
      decidedAt: DateTime.now().toUtc().toIso8601String(),
      latencyMs: latencyMs,
    ));
    await _queue.save(list);
    unawaited(flush());
  }

  /// Undo: drop the still-unsent swipe for [termId] (most recent wins). Returns
  /// true if something was removed. If it was already uploaded there is nothing
  /// to remove — the re-swipe that follows appends a newer verdict, which the
  /// server takes as current.
  Future<bool> removePending(String termId) async {
    final list = await _list();
    final idx = list.lastIndexWhere((e) => e.termId == termId);
    if (idx < 0) return false;
    list.removeAt(idx);
    await _queue.save(list);
    return true;
  }

  /// Upload the whole pending batch. Safe to call anytime (guarded against
  /// re-entrancy); keeps everything on failure for the next attempt.
  Future<void> flush() async {
    if (_flushing) return;
    final list = await _list();
    if (list.isEmpty) return;

    _flushing = true;
    try {
      final batch = List<PendingTriage>.from(list);
      await _api.submitTriages(batch);

      final sent = batch.map((e) => e.id).toSet();
      list.removeWhere((e) => sent.contains(e.id));
      await _queue.save(list);

      _ref.invalidate(collectionsProvider);
      _ref.invalidate(collectionsProgressProvider);
    } catch (_) {
      // Offline or server error — leave the queue intact and try again later.
    } finally {
      _flushing = false;
    }
  }
}
