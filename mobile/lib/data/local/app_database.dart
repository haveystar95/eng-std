import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

part 'app_database.g.dart';

// The local mirror of the sync payload. Screens read from here; the background sync writes here.
// Deletes are modelled as actual row removals when a tombstone (op:delete) arrives — the client
// keeps no `deleted_at`, it just drops the row, so a screen query never sees a ghost.

/// Owned collections. Mirrors `changes.collections` (upserts only; deletes remove the row).
class Collections extends Table {
  TextColumn get id => text()();
  TextColumn get title => text().nullable()();
  TextColumn get description => text().nullable()();
  TextColumn get topic => text().nullable()();
  TextColumn get sourceLang => text().nullable()();
  TextColumn get targetLang => text().nullable()();
  IntColumn get itemsCount => integer().withDefault(const Constant(0))();
  TextColumn get source => text().nullable()(); // curated | ai | user — origin badge
  TextColumn get type => text().nullable()(); // system | shared | custom
  // Pexels cover + attribution (A3). Populated by sync; screens/badges are Part B.
  TextColumn get imageUrl => text().nullable()();
  TextColumn get imageAuthor => text().nullable()();
  TextColumn get imageAuthorUrl => text().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

/// Collection ↔ term membership. Mirrors `changes.collection_items`.
class CollectionItems extends Table {
  TextColumn get collectionId => text()();
  TextColumn get termId => text()();
  IntColumn get position => integer().withDefault(const Constant(0))();
  TextColumn get note => text().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {collectionId, termId};
}

/// Global term content. Mirrors `changes.terms`. `termText` (getter renamed to dodge the
/// inherited `text()` builder) is the term itself.
class Terms extends Table {
  TextColumn get id => text()();
  TextColumn get termText => text().named('text').nullable()();
  TextColumn get type => text().withDefault(const Constant('word'))();
  TextColumn get transcription => text().nullable()();
  TextColumn get translation => text().nullable()();
  TextColumn get example => text().nullable()();
  TextColumn get exampleTranslation => text().nullable()();
  // Pexels photo + attribution (A3). Populated by sync; the card image is Part B.
  TextColumn get imageUrl => text().nullable()();
  TextColumn get imageAuthor => text().nullable()();
  TextColumn get imageAuthorUrl => text().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

/// (user, term) progress. Single-user app, so no user_id column. Mirrors `changes.progress`.
class TermProgress extends Table {
  TextColumn get termId => text()();
  TextColumn get state => text().withDefault(const Constant('new'))();
  RealColumn get easeFactor => real().withDefault(const Constant(2.5))();
  IntColumn get intervalDays => integer().withDefault(const Constant(0))();
  DateTimeColumn get dueAt => dateTime().nullable()();
  IntColumn get reps => integer().withDefault(const Constant(0))();
  IntColumn get lapses => integer().withDefault(const Constant(0))();
  DateTimeColumn get lastReviewedAt => dateTime().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {termId};
}

/// Small key/value store for sync bookkeeping: the sync cursor (`server_time`) and cached
/// stats numbers that aren't in the delta feed (streak, reviews-today). Lives in the DB — NOT
/// the keychain — so a reinstall wipes the cursor with the data and the next sync is a full snapshot.
class SyncMeta extends Table {
  TextColumn get key => text()();
  TextColumn get value => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {key};
}

/// Local mirror of the server's `term_triages` exclusion, keyed by (user→implicit, term). A term
/// swiped in triage is marked here so it leaves the offline deck and does not return — the server's
/// term_triages isn't in the delta feed, and an `unknown` swipe writes NO progress row, so without
/// this marker such a term would resurrect after a sync (BUG-1). Wiped with the DB on sign-out /
/// reinstall (a fresh install re-triages from scratch, same as everything else re-syncing).
class TriagedTerms extends Table {
  TextColumn get termId => text()();
  TextColumn get collectionId => text().nullable()(); // where it was swiped (informational)
  DateTimeColumn get decidedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {termId};
}

/// A generation the user kicked off, tracked locally so the pending card survives an app kill and
/// reconciles on launch. NOT synced — purely client-side bookkeeping. `id` is the server request id.
class PendingGenerations extends Table {
  TextColumn get id => text()();
  TextColumn get topic => text()();
  TextColumn get status => text().withDefault(const Constant('pending'))(); // pending|running|succeeded|failed
  TextColumn get collectionId => text().nullable()();
  TextColumn get error => text().nullable()();
  IntColumn get requested => integer().nullable()();
  IntColumn get delivered => integer().nullable()();
  // Original request params — kept so «повторить» works even after an app kill.
  TextColumn get sourceLang => text().withDefault(const Constant('ru'))();
  TextColumn get targetLang => text().withDefault(const Constant('en'))();
  TextColumn get levelsCsv => text().withDefault(const Constant('A2,B1'))();
  IntColumn get size => integer().withDefault(const Constant(15))();
  // Whether the POST has reached the server. false → still in the durable offline prompt queue
  // (client ULID pre-upload, B2): re-sent when the network returns, NEVER polled (it has no server
  // row yet) and NEVER dropped as a ghost — a not-yet-sent generation is a promise we still owe.
  BoolColumn get sent => boolean().withDefault(const Constant(true))();
  // B4: send `target_lang` on the POST only when the user explicitly chose it; otherwise omit it
  // and let the server fall back to the profile's target_language.
  BoolColumn get targetLangExplicit => boolean().withDefault(const Constant(true))();
  DateTimeColumn get createdAt => dateTime()(); // device time — drives the >24h drop rule
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

/// Per-day review count — the source for the Progress screen's activity chart, week calendar and
/// «за неделю»/«сегодня» counters (кадр 2.6). NOT synced: purely local, incremented only in
/// [ReviewSync.record] (session + free practice; triage is deliberately excluded — the chart must
/// converge with the streak dots beside it, and the streak is "days with reviews"). Accumulates
/// from the current day forward with no backfill (there is no server history endpoint). `day` is a
/// local calendar day key `YYYY-MM-DD`.
class DailyActivity extends Table {
  TextColumn get day => text()();
  IntColumn get reviews => integer().withDefault(const Constant(0))();

  @override
  Set<Column<Object>> get primaryKey => {day};
}

/// One term as shown on the collection view screen: content joined with its live position and
/// (optional) learning state, so a single reactive query feeds the whole screen.
class CollectionTermRow {
  const CollectionTermRow({required this.term, required this.position, this.state, this.triaged = false});
  final Term term; // generated data class for the Terms table
  final int position;
  final String? state; // null → not started (no progress row)
  final bool triaged; // swiped in triage — lets the UI distinguish "не знаю" (still new) from untouched
}

/// (collectionId, term progress snapshot) for deriving per-collection progress locally.
class ItemProgressRow {
  const ItemProgressRow({
    required this.collectionId,
    required this.termId,
    this.state,
    this.intervalDays,
    this.dueAt,
  });
  final String collectionId;
  final String termId;
  final String? state;
  final int? intervalDays;
  final DateTime? dueAt;
}

@DriftDatabase(tables: [
  Collections,
  CollectionItems,
  Terms,
  TermProgress,
  SyncMeta,
  TriagedTerms,
  PendingGenerations,
  DailyActivity,
])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_open());
  AppDatabase.forTesting(super.e);

  @override
  int get schemaVersion => 7;

  @override
  MigrationStrategy get migration => MigrationStrategy(
        onCreate: (m) => m.createAll(),
        onUpgrade: (m, from, to) async {
          if (from < 2) await m.createTable(triagedTerms); // triage-from-local-DB
          if (from < 3) {
            await m.addColumn(collections, collections.source); // origin badge
            await m.addColumn(collections, collections.type);
          }
          if (from < 4) {
            // Pexels imagery (A3): cover on collections, photo on terms, + attribution each.
            await m.addColumn(collections, collections.imageUrl);
            await m.addColumn(collections, collections.imageAuthor);
            await m.addColumn(collections, collections.imageAuthorUrl);
            await m.addColumn(terms, terms.imageUrl);
            await m.addColumn(terms, terms.imageAuthor);
            await m.addColumn(terms, terms.imageAuthorUrl);
          }
          if (from < 5) await m.createTable(pendingGenerations); // pending-generation card (Part B)
          if (from < 6) {
            // Offline prompt queue (A3.5): a generation may sit un-sent until the network returns.
            await m.addColumn(pendingGenerations, pendingGenerations.sent);
            await m.addColumn(pendingGenerations, pendingGenerations.targetLangExplicit);
          }
          if (from < 7) await m.createTable(dailyActivity); // Progress-screen activity (A3.6)
        },
      );

  // ---- Reads (reactive) -----------------------------------------------------

  /// All owned collections, ordered by title for a stable list (created_at isn't synced).
  Stream<List<Collection>> watchCollections() {
    return (select(collections)..orderBy([(t) => OrderingTerm(expression: t.title)])).watch();
  }

  /// The terms of one collection with content + live status, in study order (position).
  Stream<List<CollectionTermRow>> watchCollectionTerms(String collectionId) {
    final query = select(collectionItems).join([
      innerJoin(terms, terms.id.equalsExp(collectionItems.termId)),
      leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
      leftOuterJoin(triagedTerms, triagedTerms.termId.equalsExp(collectionItems.termId)),
    ])
      ..where(collectionItems.collectionId.equals(collectionId))
      ..orderBy([OrderingTerm(expression: collectionItems.position)]);

    return query.watch().map((rows) => rows
        .map((r) => CollectionTermRow(
              term: r.readTable(terms),
              position: r.readTable(collectionItems).position,
              state: r.readTableOrNull(termProgress)?.state,
              triaged: r.readTableOrNull(triagedTerms) != null,
            ))
        .toList());
  }

  /// Every progress row — the input for the local stats derivation.
  Stream<List<TermProgressData>> watchAllProgress() => select(termProgress).watch();

  /// All synced term content — powers the deterministic client-side «Слово дня».
  /// Reactive, so the block appears once the first sync lands. No network.
  Stream<List<Term>> watchAllTerms() => select(terms).watch();

  /// One synced term by id (or null) — used by the exercise-session feedback to pull the photo,
  /// which the `/study/sessions` shape does not carry. One-shot: the image is already synced.
  Future<Term?> termById(String id) =>
      (select(terms)..where((t) => t.id.equals(id))).getSingleOrNull();

  /// One term's progress, reactive — the exercise feedback shows «увидишь снова через N дней»
  /// from the REAL server `due_at`, which lands here after the answer's upload + sync (never a
  /// client-computed interval — the server is the only scheduler). Null until progress exists.
  Stream<TermProgressData?> watchProgressFor(String termId) =>
      (select(termProgress)..where((p) => p.termId.equals(termId))).watchSingleOrNull();

  /// Every term's (state, intervalDays) — all terms left-joined with their progress, so untouched
  /// terms (no progress row) surface as null. Feeds the global density bar on the Progress screen
  /// («Все N слов»): each term folds into exactly one of confirmed/familiar/in-progress. Reactive.
  Stream<List<({String? state, int? intervalDays})>> watchTermStates() {
    final query = select(terms)
        .join([leftOuterJoin(termProgress, termProgress.termId.equalsExp(terms.id))]);
    return query.watch().map((rows) => rows.map((r) {
          final p = r.readTableOrNull(termProgress);
          return (state: p?.state, intervalDays: p?.intervalDays);
        }).toList());
  }

  // ---- Daily activity (client-only, not synced) -----------------------------

  /// Live per-day review counts — feeds the Progress-screen activity chart, week calendar and
  /// «за неделю»/«сегодня» counters. Newest first isn't needed; the screen indexes by day key.
  Stream<List<DailyActivityData>> watchDailyActivity() => select(dailyActivity).watch();

  /// Increment today's review tally by one (atomic upsert). Called once per graded answer from
  /// [ReviewSync.record] — session reviews and free practice, never triage (see [DailyActivity]).
  Future<void> bumpDailyActivity(String day) => customStatement(
        'INSERT INTO daily_activity (day, reviews) VALUES (?, 1) '
        'ON CONFLICT(day) DO UPDATE SET reviews = reviews + 1',
        [day],
      );

  /// Triage-eligible (never-studied AND never-triaged) term count per collection —
  /// powers the home «Разобрать N» CTA. Same rule as [triageEligible], reactive.
  Stream<Map<String, int>> watchUntriagedByCollection() {
    final query = select(collectionItems).join([
      leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
      leftOuterJoin(triagedTerms, triagedTerms.termId.equalsExp(collectionItems.termId)),
    ])
      ..where(termProgress.termId.isNull() & triagedTerms.termId.isNull());
    return query.watch().map((rows) {
      final map = <String, int>{};
      for (final r in rows) {
        final cid = r.readTable(collectionItems).collectionId;
        map[cid] = (map[cid] ?? 0) + 1;
      }
      return map;
    });
  }

  /// Learnable term count per collection — a collection's terms that are never-studied (no progress
  /// row) but ALREADY triaged (a local triage marker exists). These are the «не знаю» words that
  /// have left the triage deck yet were never introduced in a session — they have no progress row,
  /// so they are neither «due» nor «untriaged» and would otherwise be unreachable (device-batch F8).
  /// Powers the «Учить N» CTA (a non-practice session introduces them under the daily new-quota).
  /// (known/unsure verdicts get a server progress row that syncs down, so only «unknown» stays here.)
  Stream<Map<String, int>> watchLearnableByCollection() {
    final query = select(collectionItems).join([
      leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
      leftOuterJoin(triagedTerms, triagedTerms.termId.equalsExp(collectionItems.termId)),
    ])
      ..where(termProgress.termId.isNull() & triagedTerms.termId.isNotNull());
    return query.watch().map((rows) {
      final map = <String, int>{};
      for (final r in rows) {
        final cid = r.readTable(collectionItems).collectionId;
        map[cid] = (map[cid] ?? 0) + 1;
      }
      return map;
    });
  }

  /// The triage-eligible terms of a collection, in study order — mirrors the server's queue rule:
  /// a collection's terms that are never-studied (no progress row) AND never-triaged (not in the
  /// local marker), capped at [cap]. Returned in full (not sliced to the page) so the caller can
  /// compute `remaining` exactly like the backend. This is the single source for both the deck and
  /// its counter — deriving them separately is what caused BUG-1.
  Future<List<Term>> triageEligible(String collectionId, {int cap = 500}) {
    final query = select(collectionItems).join([
      innerJoin(terms, terms.id.equalsExp(collectionItems.termId)),
      leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
      leftOuterJoin(triagedTerms, triagedTerms.termId.equalsExp(collectionItems.termId)),
    ])
      ..where(collectionItems.collectionId.equals(collectionId) &
          termProgress.termId.isNull() & // never studied (no progress row)
          triagedTerms.termId.isNull()) // never triaged (local marker)
      ..orderBy([OrderingTerm(expression: collectionItems.position)])
      ..limit(cap);

    return query.map((r) => r.readTable(terms)).get();
  }

  /// Mark a term triaged so it leaves the deck and stays out (durable, not synced). Idempotent.
  Future<void> markTriaged(String termId, String? collectionId, DateTime at) => into(triagedTerms)
      .insertOnConflictUpdate(TriagedTermsCompanion.insert(
          termId: termId, collectionId: Value(collectionId), decidedAt: at));

  /// Undo a triage mark (used when the last swipe is undone before it leaves the screen).
  Future<void> unmarkTriaged(String termId) =>
      (delete(triagedTerms)..where((t) => t.termId.equals(termId))).go();

  /// Every (collection item, progress) pair — the input for per-collection progress.
  Stream<List<ItemProgressRow>> watchItemProgress() {
    final query = select(collectionItems).join([
      leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
    ]);
    return query.watch().map((rows) => rows.map((r) {
          final item = r.readTable(collectionItems);
          final prog = r.readTableOrNull(termProgress);
          return ItemProgressRow(
            collectionId: item.collectionId,
            termId: item.termId,
            state: prog?.state,
            intervalDays: prog?.intervalDays,
            dueAt: prog?.dueAt,
          );
        }).toList());
  }

  // ---- Pending generations (client-only, not synced) ------------------------

  /// Live list of in-flight / just-finished generations, newest first — feeds the pending cards.
  Stream<List<PendingGeneration>> watchPendingGenerations() {
    return (select(pendingGenerations)..orderBy([(t) => OrderingTerm.desc(t.createdAt)])).watch();
  }

  /// Snapshot for start-up reconciliation (poll each, then drop/keep/mark).
  Future<List<PendingGeneration>> allPendingGenerations() => select(pendingGenerations).get();

  Future<void> upsertPendingGeneration(PendingGenerationsCompanion row) =>
      into(pendingGenerations).insertOnConflictUpdate(row);

  /// Partial update by id — only the columns set in [patch] change (status transitions).
  Future<void> updatePendingGeneration(String id, PendingGenerationsCompanion patch) =>
      (update(pendingGenerations)..where((t) => t.id.equals(id))).write(patch);

  Future<void> deletePendingGeneration(String id) =>
      (delete(pendingGenerations)..where((t) => t.id.equals(id))).go();

  // ---- Meta -----------------------------------------------------------------

  Future<String?> getMeta(String key) async {
    final row = await (select(syncMeta)..where((t) => t.key.equals(key))).getSingleOrNull();
    return row?.value;
  }

  Future<void> setMeta(String key, String? value) async {
    await into(syncMeta).insertOnConflictUpdate(SyncMetaCompanion.insert(key: key, value: Value(value)));
  }

  /// Current row counts + the stored cursor — for the on-device sync diagnostics panel.
  Future<({int collections, int items, int terms, int progress, String? cursor})> debugCounts() async {
    Future<int> count(TableInfo<Table, dynamic> t) async {
      final c = countAll();
      final row = await (selectOnly(t)..addColumns([c])).getSingle();
      return row.read(c) ?? 0;
    }

    return (
      collections: await count(collections),
      items: await count(collectionItems),
      terms: await count(terms),
      progress: await count(termProgress),
      cursor: await getMeta('sync_cursor'),
    );
  }

  // ---- Writes (one sync page, atomically) -----------------------------------

  /// Apply one delta page inside a single transaction so a screen never sees a torn state.
  /// Upserts are last-write-wins by id (drift's insertOnConflictUpdate); deletes remove the row.
  /// Re-applying an already-present row (the inclusive `since` boundary re-send) is a no-op.
  Future<void> applyDelta({
    List<CollectionsCompanion> collectionUpserts = const [],
    List<String> collectionDeletes = const [],
    List<CollectionItemsCompanion> itemUpserts = const [],
    List<(String collectionId, String termId)> itemDeletes = const [],
    List<TermsCompanion> termUpserts = const [],
    List<TermProgressCompanion> progressUpserts = const [],
  }) async {
    await transaction(() async {
      if (collectionUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(collections, collectionUpserts));
      }
      if (collectionDeletes.isNotEmpty) {
        await (delete(collections)..where((t) => t.id.isIn(collectionDeletes))).go();
        // Orphaned membership rows for a gone collection would otherwise linger.
        await (delete(collectionItems)..where((t) => t.collectionId.isIn(collectionDeletes))).go();
      }
      if (termUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(terms, termUpserts));
      }
      if (itemUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(collectionItems, itemUpserts));
      }
      for (final (collectionId, termId) in itemDeletes) {
        await (delete(collectionItems)
              ..where((t) => t.collectionId.equals(collectionId) & t.termId.equals(termId)))
            .go();
      }
      if (progressUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(termProgress, progressUpserts));
      }
    });
  }

  /// Optimistically drop a collection (and its membership rows) locally the moment the server
  /// delete succeeds — the read streams update immediately instead of waiting for the next sync
  /// tombstone (which the delta feed doesn't reliably carry for collections). Idempotent.
  Future<void> deleteCollectionLocal(String id) async {
    await transaction(() async {
      await (delete(collections)..where((t) => t.id.equals(id))).go();
      await (delete(collectionItems)..where((t) => t.collectionId.equals(id))).go();
    });
  }

  /// Reconcile against a full snapshot (the authoritative live set): drop every local collection
  /// whose id is NOT in [keep], plus its membership rows. This clears "ghost" collections that
  /// were removed server-side while no tombstone reached us (e.g. a dev-DB reset, or a hard delete).
  /// Only safe from a FULL snapshot, which lists every live collection.
  ///
  /// ⚠️ Exclusion (A3.5): a collection referenced by a live `pending_generations` row is NEVER
  /// reaped, even when absent from the snapshot. A just-generated collection can be locally present
  /// but momentarily missing from a racing full snapshot (the generation commit vs. the snapshot
  /// read); its pending card keeps it alive until the user acknowledges it. Offline-queued
  /// generations have no collection yet — they live only in `pending_generations`, which this reaper
  /// never touches — so this same rule is what keeps offline-created, not-yet-synced generations
  /// from being dropped as ghosts.
  Future<void> reconcileCollections(Set<String> keep) async {
    await transaction(() async {
      final referenced = (await select(pendingGenerations).get())
          .map((r) => r.collectionId)
          .whereType<String>()
          .toSet();
      final all = await select(collections).get();
      final stale = all
          .where((c) => !keep.contains(c.id) && !referenced.contains(c.id))
          .map((c) => c.id)
          .toList();
      if (stale.isEmpty) return;
      await (delete(collections)..where((t) => t.id.isIn(stale))).go();
      await (delete(collectionItems)..where((t) => t.collectionId.isIn(stale))).go();
    });
  }

  /// Wipe every synced table and the cursor. Used on sign-out so a different account can't read
  /// the previous one's cache. (A reinstall wipes the file outright.)
  Future<void> clearAll() async {
    await transaction(() async {
      await delete(collectionItems).go();
      await delete(collections).go();
      await delete(terms).go();
      await delete(termProgress).go();
      await delete(triagedTerms).go();
      await delete(pendingGenerations).go();
      await delete(dailyActivity).go();
      await delete(syncMeta).go();
    });
  }
}

LazyDatabase _open() {
  return LazyDatabase(() async {
    final dir = await getApplicationDocumentsDirectory();
    final file = File(p.join(dir.path, 'wordtrainer.sqlite'));
    return NativeDatabase.createInBackground(file);
  });
}
