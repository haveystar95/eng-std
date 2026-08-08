import 'dart:async';

import 'package:drift/drift.dart' show Value;
import 'package:flutter/foundation.dart';

import '../api_client.dart';
import 'app_database.dart';

/// What the quiet sync indicator reflects. `offline` is not an error — it's the normal state
/// underground; the UI shows nothing alarming for it.
enum SyncState { idle, syncing, offline }

/// Meta keys in [AppDatabase]'s sync_meta table. Public so the stats provider can read the two
/// cached values the delta feed doesn't carry.
abstract final class SyncKeys {
  static const cursor = 'sync_cursor'; // last server_time; the next `since`
  static const streak = 'streak'; // cached — not in the delta feed
  static const reviewsToday = 'reviews_today'; // cached — not in the delta feed
  static const bestStreak = 'best_streak'; // running max of observed streak (Progress screen)
}

const _kCursor = SyncKeys.cursor;
const _kStreak = SyncKeys.streak;
const _kReviewsToday = SyncKeys.reviewsToday;
const _kBestStreak = SyncKeys.bestStreak;

/// A human-readable summary of the last sync, surfaced on the Profile diagnostics panel so the
/// device acceptance run is verifiable on-screen (release hides debugPrint). Records exactly the
/// watch-points: the `since` sent (∅ = full snapshot, the reinstall check), the cursor it advanced
/// to, page count, and per-type change counts including both delete kinds.
class SyncReport {
  const SyncReport({
    required this.since,
    required this.serverTime,
    required this.pages,
    required this.colUpserts,
    required this.colDeletes,
    required this.itemUpserts,
    required this.itemDeletes,
    required this.termUpserts,
    required this.progressUpserts,
    required this.triageUpserts,
    required this.at,
    this.error,
  });
  final String since; // '∅' means a full snapshot was requested (no stored cursor)
  final String? serverTime;
  final int pages, colUpserts, colDeletes, itemUpserts, itemDeletes, termUpserts, progressUpserts, triageUpserts;
  final DateTime at;
  final String? error; // set when the sync was deferred (offline/transient)
}

/// Pulls the delta feed into the local DB. Read-through screens never call this; it runs in the
/// background (app start, network return, app resume) and writes to drift, whose reactive queries
/// push the change to whatever screen is watching. Non-blocking, re-entrancy-guarded, and silent
/// on network failure — being offline is expected, not a fault to surface.
class SyncService {
  SyncService(this._api, this._db);

  final ApiClient _api;
  final AppDatabase _db;

  final ValueNotifier<SyncState> state = ValueNotifier<SyncState>(SyncState.idle);

  /// Summary of the last sync attempt, for the on-device diagnostics panel. Null until first run.
  final ValueNotifier<SyncReport?> lastReport = ValueNotifier<SyncReport?>(null);

  bool _running = false;

  /// Force a full reconcile: clear the cursor so the next [sync] pulls a full snapshot, which is
  /// authoritative — [sync] then reaps local collections absent from it (ghost cleanup). Wired to
  /// pull-to-refresh. Overlapping calls collapse to one via the same guard.
  Future<void> resync() async {
    await _db.setMeta(_kCursor, null);
    await sync();
  }

  /// Run one full sync: page from the stored cursor until `has_more` is false, applying each page
  /// atomically, then persist the new cursor. First run (no stored cursor) omits `since` → the
  /// server returns a full snapshot. Safe to call anytime; overlapping calls collapse to one.
  Future<void> sync() async {
    if (_running) return;
    _running = true;
    state.value = SyncState.syncing;
    try {
      final since = await _db.getMeta(_kCursor); // null on a fresh install → full snapshot
      final isSnapshot = since == null;
      final seenCollectionIds = <String>{};
      String? cursor;
      String? serverTime;
      var hasMore = true;
      var pages = 0;
      var cu = 0, cd = 0, iu = 0, id = 0, tu = 0, pu = 0, tr = 0;

      while (hasMore) {
        final page = await _api.syncDelta(since: since, cursor: cursor);
        final n = await _applyPage(page);
        cu += n.cu; cd += n.cd; iu += n.iu; id += n.id; tu += n.tu; pu += n.pu; tr += n.tr;
        if (isSnapshot) seenCollectionIds.addAll(n.colIds);
        serverTime = page['server_time'] as String?;
        cursor = page['next_cursor'] as String?;
        hasMore = (page['has_more'] as bool?) ?? false;
        pages++;
      }

      // A full snapshot is the authoritative live set — reap any local collection not in it
      // (ghosts left by a server-side removal that sent no tombstone).
      if (isSnapshot) {
        await _db.reconcileCollections(seenCollectionIds);
      }

      // Advance the cursor only after the whole snapshot/delta is durably applied.
      if (serverTime != null) {
        await _db.setMeta(_kCursor, serverTime);
      }
      await _refreshStatsCache();
      lastReport.value = SyncReport(
        since: since ?? '∅', serverTime: serverTime, pages: pages,
        colUpserts: cu, colDeletes: cd, itemUpserts: iu, itemDeletes: id,
        termUpserts: tu, progressUpserts: pu, triageUpserts: tr, at: DateTime.now(),
      );
      state.value = SyncState.idle;
    } catch (e) {
      // Offline / timeout / 5xx: keep the old cursor, try again on the next trigger. No modal.
      debugPrint('[sync] deferred (offline/transient): $e');
      lastReport.value = SyncReport(
        since: (await _db.getMeta(_kCursor)) ?? '∅', serverTime: null, pages: 0,
        colUpserts: 0, colDeletes: 0, itemUpserts: 0, itemDeletes: 0, termUpserts: 0,
        progressUpserts: 0, triageUpserts: 0, at: DateTime.now(), error: '$e',
      );
      state.value = SyncState.offline;
    } finally {
      _running = false;
    }
  }

  Future<({int cu, int cd, int iu, int id, int tu, int pu, int tr, List<String> colIds})> _applyPage(Map<String, dynamic> page) async {
    final changes = (page['changes'] as Map<String, dynamic>?) ?? const {};

    final collectionUpserts = <CollectionsCompanion>[];
    final collectionDeletes = <String>[];
    for (final raw in (changes['collections'] as List?) ?? const []) {
      final c = raw as Map<String, dynamic>;
      final id = c['id'] as String;
      if (c['op'] == 'delete') {
        collectionDeletes.add(id);
      } else {
        collectionUpserts.add(CollectionsCompanion.insert(
          id: id,
          updatedAt: _dt(c['updated_at']),
          title: Value(c['title'] as String?),
          description: Value(c['description'] as String?),
          topic: Value(c['topic'] as String?),
          sourceLang: Value(c['source_lang'] as String?),
          targetLang: Value(c['target_lang'] as String?),
          itemsCount: Value((c['items_count'] as int?) ?? 0),
          source: Value(c['source'] as String?),
          type: Value(c['type'] as String?),
          imageUrl: Value(c['image_url'] as String?),
          imageAuthor: Value(c['image_author'] as String?),
          imageAuthorUrl: Value(c['image_author_url'] as String?),
        ));
      }
    }

    final itemUpserts = <CollectionItemsCompanion>[];
    final itemDeletes = <(String, String)>[];
    for (final raw in (changes['collection_items'] as List?) ?? const []) {
      final i = raw as Map<String, dynamic>;
      final collectionId = i['collection_id'] as String;
      final termId = i['term_id'] as String;
      if (i['op'] == 'delete') {
        itemDeletes.add((collectionId, termId));
      } else {
        itemUpserts.add(CollectionItemsCompanion.insert(
          collectionId: collectionId,
          termId: termId,
          updatedAt: _dt(i['updated_at']),
          position: Value((i['position'] as int?) ?? 0),
          note: Value(i['note'] as String?),
        ));
      }
    }

    final termUpserts = <TermsCompanion>[];
    for (final raw in (changes['terms'] as List?) ?? const []) {
      final t = raw as Map<String, dynamic>;
      termUpserts.add(TermsCompanion.insert(
        id: t['id'] as String,
        updatedAt: _dt(t['updated_at']),
        termText: Value(t['text'] as String?),
        type: Value((t['type'] as String?) ?? 'word'),
        transcription: Value(t['transcription'] as String?),
        translation: Value(t['translation'] as String?),
        example: Value(t['example'] as String?),
        exampleTranslation: Value(t['example_translation'] as String?),
        imageUrl: Value(t['image_url'] as String?),
        imageAuthor: Value(t['image_author'] as String?),
        imageAuthorUrl: Value(t['image_author_url'] as String?),
      ));
    }

    final progressUpserts = <TermProgressCompanion>[];
    for (final raw in (changes['progress'] as List?) ?? const []) {
      final p = raw as Map<String, dynamic>;
      progressUpserts.add(TermProgressCompanion.insert(
        termId: p['term_id'] as String,
        updatedAt: _dt(p['updated_at']),
        state: Value((p['state'] as String?) ?? 'new'),
        easeFactor: Value(((p['ease_factor'] as num?) ?? 2.5).toDouble()),
        intervalDays: Value((p['interval_days'] as int?) ?? 0),
        dueAt: Value(_dtn(p['due_at'])),
        reps: Value((p['reps'] as int?) ?? 0),
        lapses: Value((p['lapses'] as int?) ?? 0),
        lastReviewedAt: Value(_dtn(p['last_reviewed_at'])),
      ));
    }

    // Triage markers: restore the local deck-exclusion the server keeps in term_triages. An
    // `unknown` swipe writes no progress row, so this is the ONLY thing that stops it resurrecting
    // in the deck after a sign-out wipe + re-login (the marker is what triageEligible excludes on).
    final triageUpserts = <TriagedTermsCompanion>[];
    for (final raw in (changes['triages'] as List?) ?? const []) {
      final t = raw as Map<String, dynamic>;
      triageUpserts.add(TriagedTermsCompanion.insert(
        termId: t['term_id'] as String,
        collectionId: Value(t['collection_id'] as String?),
        decidedAt: _dt(t['updated_at']),
      ));
    }

    await _db.applyDelta(
      collectionUpserts: collectionUpserts,
      collectionDeletes: collectionDeletes,
      itemUpserts: itemUpserts,
      itemDeletes: itemDeletes,
      termUpserts: termUpserts,
      progressUpserts: progressUpserts,
      triageUpserts: triageUpserts,
    );

    return (
      cu: collectionUpserts.length, cd: collectionDeletes.length,
      iu: itemUpserts.length, id: itemDeletes.length,
      tu: termUpserts.length, pu: progressUpserts.length,
      tr: triageUpserts.length,
      colIds: [for (final c in collectionUpserts) c.id.value],
    );
  }

  /// Streak and reviews-today are server-side daily aggregates that the delta feed doesn't carry.
  /// Cache them opportunistically while online so the stats footer stays populated offline (stale,
  /// but never wrong-to-zero). Best-effort: a failure here must not fail the sync.
  Future<void> _refreshStatsCache() async {
    try {
      final s = await _api.stats();
      await _db.setMeta(_kStreak, '${s.streakDays}');
      await _db.setMeta(_kReviewsToday, '${s.reviewsTotal}');
      // «Лучший результат» (Progress screen): there is no server best-streak field, so keep a local
      // running maximum of the streak we've observed. Accumulates from now, like the activity chart.
      final best = int.tryParse(await _db.getMeta(_kBestStreak) ?? '') ?? 0;
      if (s.streakDays > best) await _db.setMeta(_kBestStreak, '${s.streakDays}');
    } catch (_) {
      // leave the last-known values in place
    }
  }

  static DateTime _dt(Object? v) => DateTime.parse(v as String);
  static DateTime? _dtn(Object? v) => v == null ? null : DateTime.parse(v as String);
}
