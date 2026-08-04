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

@DriftDatabase(tables: [Collections, CollectionItems, Terms, TermProgress, SyncMeta, TriagedTerms])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_open());
  AppDatabase.forTesting(super.e);

  @override
  int get schemaVersion => 4;

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

  /// Wipe every synced table and the cursor. Used on sign-out so a different account can't read
  /// the previous one's cache. (A reinstall wipes the file outright.)
  Future<void> clearAll() async {
    await transaction(() async {
      await delete(collectionItems).go();
      await delete(collections).go();
      await delete(terms).go();
      await delete(termProgress).go();
      await delete(triagedTerms).go();
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
