import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:flutter_test/flutter_test.dart';

/// The delta-application logic can't be exercised on-device from a unit test, but its correctness
/// (upsert = last-write-wins, tombstone = row delete, inclusive-boundary re-send = no-op) is the
/// riskiest part of the offline path, so it's pinned here against an in-memory SQLite.
void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 3, 12);

  Future<void> upsertOne() => db.applyDelta(
        collectionUpserts: [
          CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Метро'), itemsCount: const Value(1)),
        ],
        termUpserts: [
          TermsCompanion.insert(id: 't1', updatedAt: t0, termText: const Value('ticket'), translation: const Value('билет')),
        ],
        itemUpserts: [
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0, position: const Value(0)),
        ],
        progressUpserts: [
          TermProgressCompanion.insert(termId: 't1', updatedAt: t0, state: const Value('review'), intervalDays: const Value(30)),
        ],
      );

  test('upsert lands a collection, its term, and the joined status', () async {
    await upsertOne();

    final cols = await db.watchCollections().first;
    expect(cols.map((c) => c.id), ['c1']);
    expect(cols.single.title, 'Метро');

    final terms = await db.watchCollectionTerms('c1').first;
    expect(terms.single.term.termText, 'ticket');
    expect(terms.single.term.translation, 'билет');
    expect(terms.single.state, 'review'); // per-word status is joined in
  });

  test('re-applying the inclusive boundary row is a no-op (LWW by id)', () async {
    await upsertOne();
    await upsertOne(); // the same second re-sent
    expect((await db.watchCollections().first).length, 1);
    expect((await db.watchCollectionTerms('c1').first).length, 1);
  });

  test('an item tombstone removes the local row (no ghost)', () async {
    await upsertOne();
    await db.applyDelta(itemDeletes: [('c1', 't1')]);
    expect(await db.watchCollectionTerms('c1').first, isEmpty);
    // The term row itself survives (terms are global); only the membership is gone.
    expect((await db.watchAllProgress().first).length, 1);
  });

  test('a collection tombstone removes the collection and its membership rows', () async {
    await upsertOne();
    await db.applyDelta(collectionDeletes: ['c1']);
    expect(await db.watchCollections().first, isEmpty);
    expect(await db.watchCollectionTerms('c1').first, isEmpty);
  });

  test('a later upsert overwrites earlier fields (last write wins)', () async {
    await upsertOne();
    await db.applyDelta(collectionUpserts: [
      CollectionsCompanion.insert(id: 'c1', updatedAt: t0.add(const Duration(days: 1)), title: const Value('Метро 2')),
    ]);
    expect((await db.watchCollections().first).single.title, 'Метро 2');
  });

  test('sync meta round-trips (cursor storage)', () async {
    expect(await db.getMeta('sync_cursor'), isNull);
    await db.setMeta('sync_cursor', '2026-08-03T12:00:00Z');
    expect(await db.getMeta('sync_cursor'), '2026-08-03T12:00:00Z');
    await db.setMeta('sync_cursor', '2026-08-04T00:00:00Z');
    expect(await db.getMeta('sync_cursor'), '2026-08-04T00:00:00Z');
  });

  test('clearAll wipes every table and the cursor (sign-out / reinstall symmetry)', () async {
    await upsertOne();
    await db.setMeta('sync_cursor', 'x');
    await db.clearAll();
    expect(await db.watchCollections().first, isEmpty);
    expect(await db.watchAllProgress().first, isEmpty);
    expect(await db.getMeta('sync_cursor'), isNull);
  });
}
