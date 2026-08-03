import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'models.dart';
import 'providers.dart';
import 'seq_counter.dart';
import 'triage_queue.dart';

/// Offline-first triage pipeline. Every swipe is recorded on disk first (so it
/// survives no-network and app kills), then flushed to `/triage/batch` as a
/// batch. Uploads are idempotent by the client ULID, so retries are free and a
/// 2xx lets us drop the sent ids.
class TriageSync {
  TriageSync(this._api, this._queue, this._seq, this._ref);

  /// Server caps a batch at 200; 100 leaves headroom for an offline backlog.
  static const int _chunkSize = 100;

  final ApiClient _api;
  final TriageQueue _queue;
  final SeqCounter _seq;
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
      clientSeq: await _seq.next(SeqCounter.triage),
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

  /// Upload the pending queue in chunks. Safe to call anytime (guarded against re-entrancy).
  ///
  /// Online this is one swipe = one 1-item chunk = one POST (unchanged). Offline the queue can
  /// grow past the server's 200-item batch cap, so it goes out in [_chunkSize] chunks:
  ///  - a chunk that succeeds is dropped from the queue (partial progress is saved);
  ///  - a chunk rejected permanently (422/413) is dropped with a log — it would never succeed,
  ///    and it must not block the chunks after it;
  ///  - a transient failure (offline/5xx/401/429) stops the run and keeps that chunk and the
  ///    rest for next time. Order is carried by client_seq, so chunks needn't be strictly
  ///    sequential — but we send them in order anyway; it's cheaper and clearer in the logs.
  Future<void> flush() async {
    if (_flushing) return;
    final list = await _list();
    if (list.isEmpty) return;

    _flushing = true;
    try {
      final snapshot = List<PendingTriage>.from(list);
      final drop = <String>{};
      for (var i = 0; i < snapshot.length; i += _chunkSize) {
        final chunk = snapshot.sublist(i, min(i + _chunkSize, snapshot.length));
        try {
          await _api.submitTriages(chunk);
          drop.addAll(chunk.map((e) => e.id)); // success → drop
        } on DioException catch (e) {
          if (!isPermanentReject(e)) break; // transient → keep this + remaining chunks, retry later
          drop.addAll(chunk.map((e) => e.id)); // 422/413 → drop with a log, don't block the rest
          debugPrint('TriageSync: dropped ${chunk.length} rejected swipe(s) '
              '(${e.response?.statusCode}): ${e.response?.data}');
        } catch (_) {
          break; // unknown → treat as transient
        }
      }
      if (drop.isNotEmpty) {
        list.removeWhere((e) => drop.contains(e.id));
        await _queue.save(list);
        // Uploaded verdicts changed server-side progress; pull it back so the local mirror
        // (collection lists + progress) reflects it. Upload/queue logic is untouched.
        _ref.read(syncServiceProvider).sync();
      }
    } finally {
      _flushing = false;
    }
  }
}
