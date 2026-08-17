import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:flutter_test/flutter_test.dart';

/// The triage deck is now built from the local DB. Its eligibility rule must mirror the server's
/// queue (never-studied AND never-triaged, in position order) and — critically — an `unknown`
/// swipe (which writes NO progress row) must stay out via the local marker, or it resurrects after
/// a sync (BUG-1). Pinned here since none of this is visible from a screenshot.
void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 4, 9);

  Future<void> seed() => db.applyDelta(
        collectionUpserts: [CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('At the Gym'))],
        termUpserts: [
          TermsCompanion.insert(id: 't1', updatedAt: t0, termText: const Value('workout')),
          TermsCompanion.insert(id: 't2', updatedAt: t0, termText: const Value('exercise')),
          TermsCompanion.insert(id: 't3', updatedAt: t0, termText: const Value('ticket')),
        ],
        // Inserted out of position order on purpose, to prove the ORDER BY.
        itemUpserts: [
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't3', updatedAt: t0, position: const Value(2)),
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0, position: const Value(0)),
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't2', updatedAt: t0, position: const Value(1)),
        ],
      );

  test('eligible = all collection terms in position order when nothing is studied/triaged', () async {
    await seed();
    expect((await db.triageEligible('c1')).map((t) => t.id), ['t1', 't2', 't3']);
  });

  test('a studied term (has a progress row) is excluded', () async {
    await seed();
    await db.applyDelta(progressUpserts: [
      TermProgressCompanion.insert(termId: 't2', updatedAt: t0, state: const Value('review')),
    ]);
    expect((await db.triageEligible('c1')).map((t) => t.id), ['t1', 't3']);
  });

  test('a triaged term (local marker) is excluded; undo (unmark) restores it', () async {
    await seed();
    await db.markTriaged('t1', 'c1', t0);
    expect((await db.triageEligible('c1')).map((t) => t.id), ['t2', 't3']);
    await db.unmarkTriaged('t1');
    expect((await db.triageEligible('c1')).map((t) => t.id), ['t1', 't2', 't3']);
  });

  test('cap bounds the candidate count', () async {
    await seed();
    expect((await db.triageEligible('c1', cap: 2)).length, 2);
  });

  test('BUG-1: an unknown swipe (marked, no progress row) stays out across a later sync', () async {
    await seed();
    await db.markTriaged('t1', 'c1', t0); // unknown swipe: marked, but NO progress row for t1
    // A later sync brings other progress; t1 still has no progress row of its own.
    await db.applyDelta(progressUpserts: [
      TermProgressCompanion.insert(termId: 't2', updatedAt: t0, state: const Value('known')),
    ]);
    // t1 excluded by the marker, t2 by its progress row → only t3 remains. t1 did not resurrect.
    expect((await db.triageEligible('c1')).map((t) => t.id), ['t3']);
  });

  test('clearAll wipes local triage marks (the marker is restored by the next sync — see below)', () async {
    await seed();
    await db.markTriaged('t1', 'c1', t0);
    await db.clearAll();
    await seed(); // a resync WITHOUT triage rows (e.g. pre-fix / offline) → the mark is gone
    expect((await db.triageEligible('c1')).map((t) => t.id), contains('t1'));
  });

  test('re-login: an unknown swipe restored by the sync delta does NOT resurrect in the deck', () async {
    // 1. Swipe t1 «не знаю»: local marker, NO progress row (server leaves the term new).
    await seed();
    await db.markTriaged('t1', 'c1', t0);
    // 2. Sign out → the whole local DB (incl. the marker) is wiped.
    await db.clearAll();
    // 3. Sign back in → full resync. The delta now carries the triage verdict (backend fix), which
    //    applyDelta upserts into the marker — alongside collections/terms/items, but no progress row
    //    for t1 (unknown never made one).
    await seed();
    await db.applyDelta(triageUpserts: [
      TriagedTermsCompanion.insert(termId: 't1', collectionId: const Value('c1'), decidedAt: t0),
    ]);
    // 4. The deck excludes t1 again — it did not come back to be re-triaged. t2/t3 are still eligible.
    expect((await db.triageEligible('c1')).map((t) => t.id), ['t2', 't3']);
    // And it counts as learnable (marked, no progress) so «Учить N» can introduce it.
    expect((await db.watchLearnableByCollection().first)['c1'], 1);
  });
}
