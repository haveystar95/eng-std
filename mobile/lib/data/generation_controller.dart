import 'dart:async';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'local/app_database.dart';
import 'models.dart';

/// Owns the lifecycle of an AI generation from the client's side: a client-ULID pre-upload so the
/// POST is idempotent (B2), a durable offline prompt queue (the [PendingGenerations] row survives an
/// app kill AND a no-network start), polling for the outcome, and start-up reconciliation. Screens
/// never poll — they watch the drift table and rebuild.
///
/// Queue model (the prompt queue, mirrored on the triage queue):
///  - [start] mints a client ULID, records a `sent:false` row, and tries to send it. If the network
///    is down the row simply stays queued.
///  - [flushQueue] (network return / resume / launch) re-sends every un-sent row; re-sending a POST
///    the server already got returns the **existing** request (200), so retries are free.
///  - an un-sent row is NEVER polled (it has no server row yet) and NEVER dropped by the age rule —
///    dropping an offline-created generation would lose a prompt the user is still waiting on.
///
/// Reconciliation rules (start-up), for **sent** rows: succeeded → sync + drop (the collection
/// arrives via /sync); failed → keep as an error card with «повторить»; pending/running → resume
/// polling; a row older than 24h or a 404 → drop with a log note.
class GenerationController {
  GenerationController(this._api, this._db, this._sync, {this.coverResyncDelay = _defaultCoverResync});

  final ApiClient _api;
  final AppDatabase _db;
  final SyncFn _sync;

  /// How long after a success to sync ONE more time, for the covers — see [resyncForCovers].
  /// Injectable so a test doesn't have to wait it out.
  final Duration coverResyncDelay;

  static const _defaultCoverResync = Duration(seconds: 12);

  /// Ids with a live poll loop, so a resume/reconcile can't start a second one for the same id.
  final Set<String> _polling = {};

  /// Ids with a send in flight, so overlapping flushes don't double-POST the same row.
  final Set<String> _sending = {};

  static const _pollEvery = Duration(seconds: 2, milliseconds: 500);
  static const _pollBudget = 72; // ~3 min before we back off to launch-time reconciliation
  static const _maxAge = Duration(hours: 24);

  /// Kick off a generation: mint a client ULID, record a queued (`sent:false`) row that survives an
  /// app kill and an offline start, then try to send it. Returns the client ULID (the row/request
  /// id). [targetLangExplicit] false → omit `target_lang` on the POST (B4 profile fallback).
  Future<String> start({
    required String topic,
    required List<String> levels,
    required int size,
    required String sourceLang,
    required String targetLang,
    required bool targetLangExplicit,
  }) async {
    final id = ApiClient.ulid();
    final now = DateTime.now();
    await _db.upsertPendingGeneration(PendingGenerationsCompanion.insert(
      id: id,
      topic: topic,
      createdAt: now,
      updatedAt: now,
      status: const Value('pending'),
      sent: const Value(false),
      requested: Value(size),
      sourceLang: Value(sourceLang),
      targetLang: Value(targetLang),
      targetLangExplicit: Value(targetLangExplicit),
      levelsCsv: Value(levels.join(',')),
      size: Value(size),
    ));
    unawaited(_send(id));
    return id;
  }

  /// Re-run a failed generation with its original params; replace the row (a fresh client ULID).
  Future<void> retry(PendingGeneration row) async {
    await _db.deletePendingGeneration(row.id);
    await start(
      topic: row.topic,
      levels: _levels(row),
      size: row.size,
      sourceLang: row.sourceLang,
      targetLang: row.targetLang,
      targetLangExplicit: row.targetLangExplicit,
    );
  }

  /// Drop a card the user acknowledged (opened a ready one, or dismissed an error/queued one).
  Future<void> dismiss(String id) => _db.deletePendingGeneration(id);

  /// Re-send every queued (un-sent) generation and resume polling the sent ones. Wired to network
  /// return / app resume. Safe to call anytime — per-id guards collapse overlaps.
  Future<void> flushQueue() async {
    for (final r in await _db.allPendingGenerations()) {
      if (r.status == 'succeeded' || r.status == 'failed') continue;
      if (r.sent) {
        _poll(r.id);
      } else {
        unawaited(_send(r.id));
      }
    }
  }

  /// Reconcile every stored pending generation on app start.
  Future<void> reconcile() async {
    for (final r in await _db.allPendingGenerations()) {
      // Age rule applies to SENT rows only — an un-sent (offline) row is a prompt still owed, not a
      // ghost, so it never times out here; it is re-sent below.
      if (r.sent && DateTime.now().difference(r.createdAt) > _maxAge) {
        debugPrint('[gen] dropping stale pending ${r.id} (>24h)');
        await _db.deletePendingGeneration(r.id);
        continue;
      }
      switch (r.status) {
        case 'succeeded':
          // Finished in a previous session — ensure the collection is mirrored, then drop.
          unawaited(_sync());
          await _db.deletePendingGeneration(r.id);
        case 'failed':
          break; // leave the error card for the user to retry or dismiss
        default:
          if (r.sent) {
            _poll(r.id); // pending / running → resume polling
          } else {
            unawaited(_send(r.id)); // queued offline → re-send (never poll an un-sent row → no 404 drop)
          }
      }
    }
  }

  /// Send one queued row's POST (idempotent by its client ULID). On success mark it sent and start
  /// polling; on a permanent reject (422/413) surface a failed card; on a transient failure (offline
  /// / timeout / 5xx / 429) leave it queued for the next flush.
  Future<void> _send(String id) async {
    if (_sending.contains(id)) return;
    _sending.add(id);
    try {
      final r = _find(await _db.allPendingGenerations(), id);
      if (r == null) return; // dismissed meanwhile
      if (r.sent) {
        _poll(id);
        return;
      }
      try {
        await _api.generateCollection(
          id: r.id,
          topic: r.topic,
          levels: _levels(r),
          size: r.size,
          sourceLang: r.sourceLang,
          targetLang: r.targetLangExplicit ? r.targetLang : null,
        );
      } on DioException catch (e) {
        if (isPermanentReject(e)) {
          debugPrint('[gen] $id rejected (${e.response?.statusCode}) — failing the card');
          await _db.updatePendingGeneration(id, PendingGenerationsCompanion(
            status: const Value('failed'),
            updatedAt: Value(DateTime.now()),
          ));
        }
        return; // transient → keep queued (sent stays false); the next flush retries
      }
      await _db.updatePendingGeneration(id, PendingGenerationsCompanion(
        sent: const Value(true),
        updatedAt: Value(DateTime.now()),
      ));
      _poll(id);
    } finally {
      _sending.remove(id);
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
        await _sync(); // pull the new collection + terms (and their images as they land)
        unawaited(resyncForCovers());
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

  /// One more sync a few seconds after a generation succeeds, for the COVERS.
  ///
  /// The generation reports done the moment the words exist; the images are searched and attached
  /// after that, on their own job. The client synced at 17:00:45 and the server was still fetching
  /// pictures at 17:00:53, so the collection appeared with an empty cover and stayed that way until
  /// something else happened to sync (QA-4). One extra pull closes the gap without asking the user
  /// to pull-to-refresh a thing they cannot know is missing.
  ///
  /// Fire-and-forget and idempotent: the sync is a delta pull, so a second one that finds nothing
  /// new costs a round trip and changes nothing.
  Future<void> resyncForCovers() async {
    await Future<void>.delayed(coverResyncDelay);
    await _sync();
  }

  Future<bool> _rowExists(String id) async {
    final rows = await _db.allPendingGenerations();
    return rows.any((r) => r.id == id);
  }

  static List<String> _levels(PendingGeneration r) =>
      r.levelsCsv.split(',').where((s) => s.isNotEmpty).toList();

  static PendingGeneration? _find(List<PendingGeneration> rows, String id) {
    for (final r in rows) {
      if (r.id == id) return r;
    }
    return null;
  }
}

/// Minimal `sync()` dependency — a function so the controller doesn't import the whole SyncService
/// (and so tests can inject a no-op / spy).
typedef SyncFn = Future<void> Function();
