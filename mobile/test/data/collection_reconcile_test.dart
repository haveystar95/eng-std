import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:flutter_test/flutter_test.dart';

/// Ghost-collection cleanup (the "backend deleted it, front didn't" bug). Two paths:
/// optimistic single delete, and full-snapshot reconcile that reaps rows absent from the
/// authoritative live set. Pinned here — neither is visible from a screenshot.
void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 6, 9);

  Future<void> seed() => db.applyDelta(
        collectionUpserts: [
          CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('At the Gym')),
          CollectionsCompanion.insert(id: 'c2', updatedAt: t0, title: const Value('Calling IT Support')),
          CollectionsCompanion.insert(id: 'c3', updatedAt: t0, title: const Value('Common')),
        ],
        termUpserts: [TermsCompanion.insert(id: 't1', updatedAt: t0, termText: const Value('workout'))],
        itemUpserts: [
          CollectionItemsCompanion.insert(collectionId: 'c2', termId: 't1', updatedAt: t0),
        ],
      );

  Future<Set<String>> collectionIds() async =>
      (await db.watchCollections().first).map((c) => c.id).toSet();

  test('deleteCollectionLocal drops the collection and its items', () async {
    await seed();
    await db.deleteCollectionLocal('c2');
    expect(await collectionIds(), {'c1', 'c3'});
    // its membership row is gone too
    final items = await db.watchCollectionTerms('c2').first;
    expect(items, isEmpty);
  });

  test('reconcileCollections reaps every collection absent from the live set', () async {
    await seed();
    // Server's authoritative snapshot only still has c1 — c2 and c3 are ghosts.
    await db.reconcileCollections({'c1'});
    expect(await collectionIds(), {'c1'});
    expect(await db.watchCollectionTerms('c2').first, isEmpty);
  });

  test('reconcileCollections with the full set is a no-op', () async {
    await seed();
    await db.reconcileCollections({'c1', 'c2', 'c3'});
    expect(await collectionIds(), {'c1', 'c2', 'c3'});
  });

  test('reconcileCollections with an empty set clears all (signed-out-like)', () async {
    await seed();
    await db.reconcileCollections(<String>{});
    expect(await collectionIds(), isEmpty);
  });
}
