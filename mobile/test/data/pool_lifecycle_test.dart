import 'dart:math';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';

/// The POOL, end to end on the device: a word is in a collection, the learner takes it into study,
/// the trainer starts dealing it, the learner pauses it, and it comes back exactly where it was.
///
/// Every screen in this app reads the local mirror, so this is the file that pins «что я учу» rather
/// than «что есть в коллекции» — the whole point of splitting the library from the queue. The
/// server's half of the same story is `backend2/tests/Feature/Learning/PoolApiTest.php`.
void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 19, 9);

  Future<void> seed() => db.applyDelta(
        collectionUpserts: [
          CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Аптека')),
          CollectionsCompanion.insert(id: 'c2', updatedAt: t0, title: const Value('Аэропорт')),
        ],
        termUpserts: [
          TermsCompanion.insert(
              id: 't1', updatedAt: t0, termText: const Value('antipyretic'), translation: const Value('жаропонижающее')),
          TermsCompanion.insert(
              id: 't2', updatedAt: t0, termText: const Value('painkiller'), translation: const Value('обезболивающее')),
          TermsCompanion.insert(
              id: 't3', updatedAt: t0, termText: const Value('boarding pass'), translation: const Value('посадочный талон')),
        ],
        itemUpserts: [
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0, position: const Value(0)),
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't2', updatedAt: t0, position: const Value(1)),
          CollectionItemsCompanion.insert(collectionId: 'c2', termId: 't3', updatedAt: t0, position: const Value(0)),
        ],
      );

  Future<List<Term>> allTerms() => db.watchAllTerms().first;

  /// Which POOL words a practice session over the whole mirror draws, with the pool as the device
  /// actually holds it. Distinct: a one-word pool is fanned into a card per trainer (QA-14/QA-26),
  /// and this asks which words practice reaches, never how many cards each of them got.
  ///
  /// Filtered to the pool ON PURPOSE, and that filter belongs to this test rather than to the
  /// builder: free practice is a drill over the whole COLLECTION now, catalogue included
  /// (`practice/catalogue_practice_test.dart` owns that half), so the raw card list would answer a
  /// different question than the one this file asks — «что я учу».
  Future<List<String>> practiceTermIds() async {
    final terms = await allTerms();
    final ladder = await db.ladderPositions([for (final t in terms) t.id]);
    final session = LocalPracticeSessionBuilder.build(
      terms: terms,
      limit: 20,
      random: Random(7),
      sessionId: 'S',
      ladder: ladder,
    );
    return {
      for (final c in session.cards)
        if (ladder[c.termId]?.enrolled ?? false) c.termId,
    }.toList();
  }

  test('a collection full of words is not a queue: nothing is studied until it is chosen', () async {
    await seed();

    expect(await db.watchPool().first, isEmpty);
    expect(await db.watchLearnableCount().first, 0);
    expect(await practiceTermIds(), isEmpty, reason: 'no word is being STUDIED yet');
  });

  test('a «не знаю» swipe puts the word in the pool at rung 0, and «Учить N» counts it', () async {
    await seed();
    await db.enrollLocally('t1', t0); // what TriageSync.record does for «не знаю»

    final pool = await db.watchPool().first;
    expect([for (final r in pool) r.term.id], ['t1']);
    expect(pool.single.position.acquisition, Acquisition.isNew);
    expect(pool.single.position.step, LearningLadder.stepIntro);
    expect(pool.single.collectionIds, ['c1']);
    expect(await db.watchLearnableCount().first, 1);
    expect((await db.watchLearnableByCollection().first)['c1'], 1);

    // Still not drilled: practice introduces nothing, so rung 0 has nothing for it to do. The word
    // reaches the trainer through a STUDY session, which is the daily-quota path.
    expect(await practiceTermIds(), isEmpty);
  });

  test('a «не уверен» swipe skips the intro — it lands on the first recognition rung', () async {
    await seed();
    await db.enrollLocally('t1', t0, acquisition: 'learning', learningStep: LearningLadder.firstLadderStep);
    await db.enrollLocally('t2', t0, acquisition: 'learning', learningStep: LearningLadder.firstLadderStep);

    final pool = await db.watchPool().first;
    expect(pool.first.position.step, LearningLadder.stepRecognitionForward);
    // …and now practice has something to drill: the word has been seen.
    expect(await practiceTermIds(), containsAll(['t1', 't2']));
  });

  test('enrolment is idempotent — a second swipe does not rewrite the day it started', () async {
    await seed();
    await db.enrollLocally('t1', t0);
    await db.enrollLocally('t1', t0.add(const Duration(days: 3)));

    expect((await db.watchPool().first).single.enrolledAt.isAtSameMomentAs(t0), isTrue);
  });

  test('«Убрать из изучения» is a pause: the word leaves the queue and loses nothing', () async {
    await seed();
    // A word with a real history: graduated, reviewed, scheduled.
    await db.applyDelta(progressUpserts: [
      TermProgressCompanion.insert(
        termId: 't1',
        updatedAt: t0,
        state: const Value('review'),
        acquisition: const Value('graduated'),
        successfulReviews: const Value(5),
        reps: const Value(9),
        intervalDays: const Value(12),
        dueAt: Value(t0),
        enrolledAt: Value(t0),
      ),
    ]);
    await db.enrollLocally('t2', t0, acquisition: 'learning', learningStep: 1); // a neighbour to drill

    await db.unenrollLocally('t1', t0.add(const Duration(days: 1)));

    expect([for (final r in await db.watchPool().first) r.term.id], ['t2'],
        reason: 'it is out of «Мои слова»');
    expect(await practiceTermIds(), ['t2'], reason: 'and out of the sessions');

    final row = await (db.select(db.termProgress)..where((p) => p.termId.equals('t1'))).getSingle();
    expect(row.enrolledAt, isNull);
    // …and everything the learner earned still stands. This is the whole promise of the wording
    // «слово можно вернуть в любой момент».
    expect(row.state, 'review');
    expect(row.acquisition, 'graduated');
    expect(row.successfulReviews, 5);
    expect(row.reps, 9);
    expect(row.intervalDays, 12);
    expect(row.dueAt!.isAtSameMomentAs(t0), isTrue);
  });

  test('a returned word resumes at the rung it left with, not at an intro', () async {
    await seed();
    await db.applyDelta(progressUpserts: [
      TermProgressCompanion.insert(
        termId: 't1',
        updatedAt: t0,
        state: const Value('review'),
        acquisition: const Value('graduated'),
        successfulReviews: const Value(LearningLadder.typingMinSuccesses),
        enrolledAt: Value(t0),
      ),
    ]);

    await db.unenrollLocally('t1', t0);
    await db.enrollLocally('t1', t0.add(const Duration(days: 30)));

    final back = (await db.watchPool().first).single;
    expect(back.position.step, LearningLadder.stepTyping);
    expect(back.position.acquisition, Acquisition.graduated);
  });

  test('an undone swipe takes its enrolment back — but never one the learner had already earned', () async {
    await seed();
    // t1: enrolled by the swipe being undone, never shown → the enrolment goes back with it.
    await db.enrollLocally('t1', t0);
    // t2: already studied, and a stray swipe on it must not pause a word mid-ladder.
    await db.applyDelta(progressUpserts: [
      TermProgressCompanion.insert(
        termId: 't2',
        updatedAt: t0,
        acquisition: const Value('learning'),
        learningStep: const Value(1),
        enrolledAt: Value(t0),
      ),
    ]);

    await db.unenrollLocallyIfUnshown('t1', t0);
    await db.unenrollLocallyIfUnshown('t2', t0);

    expect([for (final r in await db.watchPool().first) r.term.id], ['t2']);
  });

  test('a pool word outlives its collection — and is still findable under «без коллекции»', () async {
    await seed();
    await db.enrollLocally('t1', t0, acquisition: 'learning', learningStep: 1);
    await db.enrollLocally('t3', t0, acquisition: 'learning', learningStep: 1);

    // The collection goes back on the shelf; the words the learner chose stay theirs.
    await db.deleteCollectionLocal('c1');

    final pool = await db.watchPool().first;
    expect([for (final r in pool) r.term.id], containsAll(['t1', 't3']));
    expect(pool.firstWhere((r) => r.term.id == 't1').collectionIds, isEmpty);
    expect(pool.firstWhere((r) => r.term.id == 't3').collectionIds, ['c2']);
    // …and the trainer still deals them, which is the point of not filtering the pool by collection.
    expect(await practiceTermIds(), containsAll(['t1', 't3']));
  });

  test('the pool queue holds the last intent per word, not a log of taps', () async {
    await seed();
    await db.enqueuePoolChange('t1', enrolled: true, at: t0);
    await db.enqueuePoolChange('t1', enrolled: false, at: t0.add(const Duration(minutes: 1)));

    final queue = await db.poolQueue();
    expect(queue, hasLength(1), reason: 'membership is a set — enrol-then-remove is one decision');
    expect(queue.single.enrolled, isFalse);

    await db.dequeuePoolChanges(['t1']);
    expect(await db.poolQueue(), isEmpty);
  });
}
