import 'dart:async';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'local/app_database.dart';
import 'local/sync_service.dart';
import 'models.dart';

/// Owns the lifecycle of an AI generation from the client's side: fire the request, record a local
/// [PendingGenerations] row so the card survives an app kill, poll for the outcome, and reconcile
/// leftover rows on launch. Screens never poll — they watch the drift table and rebuild.
///
/// Reconciliation rules (start-up): succeeded → sync + drop (the collection arrives via /sync);
/// failed → keep as an error card with «повторить»; pending/running → resume polling; a row older
/// than 24h or a 404 → drop with a log note.
class GenerationController {
  GenerationController(this._api, this._db, this._sync);

  final ApiClient _api;
  final AppDatabase _db;
  final SyncService _sync;

  /// Ids with a live poll loop, so a resume/reconcile can't start a second one for the same id.
  final Set<String> _polling = {};

  static const _pollEvery = Duration(seconds: 2, milliseconds: 500);
  static const _pollBudget = 72; // ~3 min before we back off to launch-time reconciliation
  static const _maxAge = Duration(hours: 24);

  /// Kick off a generation: POST, record a pending row, begin polling. Returns the request id.
  Future<String> start({
    required String topic,
    required List<String> levels,
    required int size,
    required String sourceLang,
    required String targetLang,
  }) async {
    final id = await _api.generateCollection(
      topic: topic, levels: levels, size: size, sourceLang: sourceLang, targetLang: targetLang,
    );
    final now = DateTime.now();
    await _db.upsertPendingGeneration(PendingGenerationsCompanion.insert(
      id: id,
      topic: topic,
      createdAt: now,
      updatedAt: now,
      status: const Value('pending'),
      requested: Value(size),
      sourceLang: Value(sourceLang),
      targetLang: Value(targetLang),
      levelsCsv: Value(levels.join(',')),
      size: Value(size),
    ));
    _poll(id);
    return id;
  }

  /// Re-run a failed generation with its original params; replace the row.
  Future<void> retry(PendingGeneration row) async {
    await _db.deletePendingGeneration(row.id);
    await start(
      topic: row.topic,
      levels: row.levelsCsv.split(',').where((s) => s.isNotEmpty).toList(),
      size: row.size,
      sourceLang: row.sourceLang,
      targetLang: row.targetLang,
    );
  }

  /// Drop a card the user acknowledged (opened a ready one, or dismissed an error).
  Future<void> dismiss(String id) => _db.deletePendingGeneration(id);

  /// Reconcile every stored pending generation on app start.
  Future<void> reconcile() async {
    for (final r in await _db.allPendingGenerations()) {
      if (DateTime.now().difference(r.createdAt) > _maxAge) {
        debugPrint('[gen] dropping stale pending ${r.id} (>24h)');
        await _db.deletePendingGeneration(r.id);
        continue;
      }
      switch (r.status) {
        case 'succeeded':
          // Finished in a previous session — ensure the collection is mirrored, then drop (no stale
          // ready card across launches).
          unawaited(_sync.sync());
          await _db.deletePendingGeneration(r.id);
        case 'failed':
          break; // leave the error card for the user to retry or dismiss
        default:
          _poll(r.id); // pending / running → resume
      }
    }
  }

  void _poll(String id) {
    if (_polling.contains(id)) return;
    _polling.add(id);
    unawaited(_pollLoop(id).whenComplete(() => _polling.remove(id)));
  }

  Future<void> _pollLoop(String id) async {
    for (var i = 0; i < _pollBudget; i++) {
      await Future<void>.delayed(_pollEvery);
      if (!await _rowExists(id)) return; // dismissed → stop

      final GenerationStatusView s;
      try {
        s = await _api.jobStatus(id);
      } on DioException catch (e) {
        if (e.response?.statusCode == 404) {
          debugPrint('[gen] $id → 404, dropping');
          await _db.deletePendingGeneration(id);
          return;
        }
        continue; // offline / timeout / 5xx → keep trying within the budget
      }

      final now = DateTime.now();
      if (s.isSucceeded) {
        await _db.updatePendingGeneration(id, PendingGenerationsCompanion(
          status: const Value('succeeded'),
          collectionId: Value(s.collectionId),
          requested: Value(s.requested),
          delivered: Value(s.delivered),
          updatedAt: Value(now),
        ));
        await _sync.sync(); // pull the new collection + terms (and their images as they land)
        return;
      }
      if (s.isFailed) {
        await _db.updatePendingGeneration(id, PendingGenerationsCompanion(
          status: const Value('failed'),
          error: Value(s.error),
          updatedAt: Value(now),
        ));
        return;
      }
      if (s.status == 'running') {
        await _db.updatePendingGeneration(id,
            PendingGenerationsCompanion(status: const Value('running'), updatedAt: Value(now)));
      }
    }
    // Budget spent while still pending: leave the row as-is; the next app launch reconciles it.
  }

  Future<bool> _rowExists(String id) async {
    final rows = await _db.allPendingGenerations();
    return rows.any((r) => r.id == id);
  }
}
