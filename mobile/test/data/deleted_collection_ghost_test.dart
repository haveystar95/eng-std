import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';

/// Ч.5б — a deleted generated collection must not leave a card behind.
///
/// The ready generation card has a branch for «the collection is not in the mirror yet»: a shimmer
/// reading «Готово — загружаю „X"…», written for the second between the generation landing and the
/// sync arriving. Delete the collection and that second never ends — the row still points at an id
/// nothing will ever bring back, and the ghost sits on the Collections tab until the app restarts.
///
/// Nothing else was going to clear it: `reconcileCollections` deliberately never touches
/// `pending_generations` (a just-generated collection is kept alive by exactly this reference).
final _t0 = DateTime.utc(2026, 8, 26, 9);

void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  Future<void> seedGeneratedCollection() async {
    await db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(
          id: 'c1',
          updatedAt: _t0,
          title: const Value('Аэропорт'),
        ),
      ],
    );
    await db.upsertPendingGeneration(
      PendingGenerationsCompanion.insert(
        id: 'g1',
        topic: 'Аэропорт',
        status: const Value('ready'),
        createdAt: _t0,
        updatedAt: _t0,
        collectionId: const Value('c1'),
      ),
    );
  }

  test('deleting the collection takes its generation card with it', () async {
    await seedGeneratedCollection();
    expect(await db.allPendingGenerations(), hasLength(1));

    await db.deleteCollectionLocal('c1');

    expect(
      await db.allPendingGenerations(),
      isEmpty,
      reason: 'the card would otherwise render «Готово — загружаю…» for a collection that is gone',
    );
  });

  test('another generation is untouched', () async {
    await seedGeneratedCollection();
    await db.upsertPendingGeneration(
      PendingGenerationsCompanion.insert(
        id: 'g2',
        topic: 'Банк',
        status: const Value('ready'),
        createdAt: _t0,
        updatedAt: _t0,
        collectionId: const Value('c2'),
      ),
    );

    await db.deleteCollectionLocal('c1');

    final left = await db.allPendingGenerations();
    expect(left.map((r) => r.id), ['g2']);
  });

  test('a generation with no collection yet (queued offline) survives', () async {
    // These rows live only in `pending_generations` until the server answers. Deleting some other
    // collection must not reap them — that is the same rule the ghost reaper obeys.
    await db.upsertPendingGeneration(
      PendingGenerationsCompanion.insert(
        id: 'g3',
        topic: 'Врач',
        status: const Value('queued'),
        createdAt: _t0,
        updatedAt: _t0,
      ),
    );

    await db.deleteCollectionLocal('c1');

    expect((await db.allPendingGenerations()).map((r) => r.id), ['g3']);
  });
}
