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
}

const _kCursor = SyncKeys.cursor;
const _kStreak = SyncKeys.streak;
const _kReviewsToday = SyncKeys.reviewsToday;

/// Pulls the delta feed into the local DB. Read-through screens never call this; it runs in the
/// background (app start, network return, app resume) and writes to drift, whose reactive queries
/// push the change to whatever screen is watching. Non-blocking, re-entrancy-guarded, and silent
/// on network failure — being offline is expected, not a fault to surface.
class SyncService {
  SyncService(this._api, this._db);

  final ApiClient _api;
  final AppDatabase _db;

  final ValueNotifier<SyncState> state = ValueNotifier<SyncState>(SyncState.idle);

  bool _running = false;

  /// Run one full sync: page from the stored cursor until `has_more` is false, applying each page
  /// atomically, then persist the new cursor. First run (no stored cursor) omits `since` → the
  /// server returns a full snapshot. Safe to call anytime; overlapping calls collapse to one.
  Future<void> sync() async {
    if (_running) return;
    _running = true;
    state.value = SyncState.syncing;
    try {
      final since = await _db.getMeta(_kCursor); // null on a fresh install → full snapshot
      String? cursor;
      String? serverTime;
      var hasMore = true;

      while (hasMore) {
        final page = await _api.syncDelta(since: since, cursor: cursor);
        await _applyPage(page);
        serverTime = page['server_time'] as String?;
        cursor = page['next_cursor'] as String?;
        hasMore = (page['has_more'] as bool?) ?? false;
      }

      // Advance the cursor only after the whole snapshot/delta is durably applied.
      if (serverTime != null) {
        await _db.setMeta(_kCursor, serverTime);
      }
      await _refreshStatsCache();
      state.value = SyncState.idle;
    } catch (e) {
      // Offline / timeout / 5xx: keep the old cursor, try again on the next trigger. No modal.
      debugPrint('SyncService: sync deferred ($e)');
      state.value = SyncState.offline;
    } finally {
      _running = false;
    }
  }

  Future<void> _applyPage(Map<String, dynamic> page) async {
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

    await _db.applyDelta(
      collectionUpserts: collectionUpserts,
      collectionDeletes: collectionDeletes,
      itemUpserts: itemUpserts,
      itemDeletes: itemDeletes,
      termUpserts: termUpserts,
      progressUpserts: progressUpserts,
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
    } catch (_) {
      // leave the last-known values in place
    }
  }

  static DateTime _dt(Object? v) => DateTime.parse(v as String);
  static DateTime? _dtn(Object? v) => v == null ? null : DateTime.parse(v as String);
}
