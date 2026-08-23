import 'dart:io';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/day_key.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/home/home_cta.dart';
import 'package:eng_std/features/home/home_providers.dart';

/// THE DAILY GOAL, counted once (QA-BUG-2).
///
/// The device showed «8 / 20» on the session summary and «3 / 20» on the home screen at the same
/// moment: the summary printed the day's ANSWERS under the label «Дневная цель», the home screen
/// printed the day's NEW WORDS. This file pins the settled definition — the goal is NEW WORDS TAKEN
/// INTO THE POOL today — and pins it where it is counted, so neither screen can drift again.
///
/// Every door into the pool writes exactly one column, `term_progress.enrolled_at`, and the tests
/// below reproduce each door as its own call site makes it:
///
///   * «не знаю»    — TriageSync.record: markTriaged + enrollLocally(rung 0)
///   * «не уверен»  — TriageSync.record: markTriaged + enrollLocally(rung 1)
///   * «знаю»       — TriageSync.record: markTriaged and NOTHING else, deliberately
///   * «Учить это слово» — PoolSync.enroll: enrollLocally(now)
///   * слово из поиска  — the server enrols it (`POST /search/add`) and its `enrolled_at` arrives
///                        through the `/sync` delta
void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  // A fixed «now», so the test never depends on when it runs. Local — the goal rolls over at the
  // learner's midnight.
  final now = DateTime(2026, 8, 23, 10, 30);
  final yesterday = now.subtract(const Duration(days: 1));

  Future<void> seed() => db.applyDelta(
        collectionUpserts: [
          CollectionsCompanion.insert(id: 'c1', updatedAt: yesterday, title: const Value('Аптека')),
        ],
        termUpserts: [
          for (final id in ['t1', 't2', 't3'])
            TermsCompanion.insert(id: id, updatedAt: yesterday, termText: Value(id)),
        ],
        itemUpserts: [
          for (final (i, id) in ['t1', 't2', 't3'].indexed)
            CollectionItemsCompanion.insert(
                collectionId: 'c1', termId: id, updatedAt: yesterday, position: Value(i)),
        ],
      );

  /// The goal's left-hand number, straight off the column the doors write.
  Future<int> goalDone() async => newWordsToday(await db.watchEnrolledAt().first, now);

  group('the four doors into the pool', () {
    test('«не знаю» is one new word', () async {
      await seed();
      await db.markTriaged('t1', 'c1', now);
      await db.enrollLocally('t1', now); // rung 0 — never shown

      expect(await goalDone(), 1);
    });

    test('«не уверен» is one new word too — it is the same decision, one rung up', () async {
      await seed();
      await db.markTriaged('t1', 'c1', now);
      await db.enrollLocally('t1', now,
          acquisition: 'learning', learningStep: LearningLadder.firstLadderStep);

      expect(await goalDone(), 1);
    });

    test('«знаю» is NOT a new word — it means the opposite', () async {
      await seed();
      await db.markTriaged('t1', 'c1', now); // all TriageSync does for this verdict

      expect(await goalDone(), 0);
      expect(await db.watchPool().first, isEmpty);
    });

    test('«Учить это слово» is one new word, and a second tap does not add a second', () async {
      await seed();
      await db.enrollLocally('t2', now);
      await db.enrollLocally('t2', now.add(const Duration(minutes: 5)));

      expect(await goalDone(), 1, reason: 'enrolled_at keeps the FIRST moment');
    });

    test('a word saved from search counts on the day the SERVER enrolled it', () async {
      await seed();
      // What `/sync` brings back after POST /search/add — the row carries the server's own
      // enrolled_at, so the client counts it without a second source of truth.
      await db.applyDelta(progressUpserts: [
        TermProgressCompanion.insert(
          termId: 't3',
          updatedAt: now,
          enrolledAt: Value(now.subtract(const Duration(hours: 2)).toUtc()),
        ),
      ]);

      expect(await goalDone(), 1);
    });
  });

  group('what the goal counts, and what it does not', () {
    test('a word that passed two trainers today is +1, not +2', () async {
      await seed();
      await db.enrollLocally('t1', now);
      // The day it then had: an intro card and two graded answers. None of them is a new word.
      await db.markIntroduced('t1', now);
      await db.bumpDailyActivity(localDayKey(now));
      await db.bumpDailyActivity(localDayKey(now));

      expect(await goalDone(), 1);
      final activity = await db.watchDailyActivity().first;
      expect(activity.single.reviews, 2, reason: 'the answers are a separate fact, and still true');
    });

    test('a day of pure repeats moves the goal not at all', () async {
      await seed();
      await db.enrollLocally('t1', yesterday); // taken into study YESTERDAY
      await db.bumpDailyActivity(localDayKey(now));
      await db.bumpDailyActivity(localDayKey(now));

      expect(await goalDone(), 0);
    });

    test('three words in, one of them known → 2', () async {
      await seed();
      await db.enrollLocally('t1', now);
      await db.enrollLocally('t2', now);
      await db.markTriaged('t3', 'c1', now); // «знаю»

      expect(await goalDone(), 2);
    });

    test('yesterday is not today', () async {
      await seed();
      await db.enrollLocally('t1', yesterday);
      await db.enrollLocally('t2', now);

      expect(await goalDone(), 1);
    });
  });

  group('the day is the DEVICE\'s local day', () {
    test('an instant stored in UTC is counted on its local calendar day', () {
      // Just past local midnight: in a positive-offset zone this instant is still «yesterday» in
      // UTC, and counting the raw column would lose the word the learner just took.
      final justAfterMidnight = DateTime(2026, 8, 23, 0, 20);
      expect(newWordsToday([justAfterMidnight.toUtc()], justAfterMidnight), 1);
    });

    test('an enrolment 20 minutes before local midnight belongs to the day that is ending', () {
      final beforeMidnight = DateTime(2026, 8, 22, 23, 40);
      final afterMidnight = DateTime(2026, 8, 23, 0, 20);
      expect(newWordsToday([beforeMidnight.toUtc()], beforeMidnight), 1);
      expect(newWordsToday([beforeMidnight.toUtc()], afterMidnight), 0);
    });
  });

  group('one counter, two screens', () {
    test('dailyGoalProvider reads the pool and the day target together', () async {
      await seed();
      await db.enrollLocally('t1', DateTime.now());
      await db.enrollLocally('t2', DateTime.now());

      final container = ProviderContainer(overrides: [
        appDatabaseProvider.overrideWithValue(db),
      ]);
      addTearDown(container.dispose);

      // A listener first: providers are auto-dispose by default in Riverpod 3, and a bare read of
      // the future tears the stream down before it can deliver. The stream then has to deliver
      // before the plain Provider composing it can be read.
      container.listen(newWordsTodayProvider, (_, _) {});
      await container.read(newWordsTodayProvider.future);
      final ring = container.read(dailyGoalProvider);

      expect(ring.done, 2);
      expect(ring.goal, kDefaultDailyGoal, reason: 'no /stats and no profile yet → the default');
    });

    // A source guard, in the family of test/data/dev_login_release_guard_test.dart: the two screens
    // agreeing is not a property any single widget test can observe, but it IS a property of where
    // they read the number from. The summary used to compute its own from `reviews_today`.
    test('the home screen and the session summary read the SAME counter', () {
      final home = File('lib/features/training/training_home_screen.dart').readAsStringSync();
      final session = File('lib/features/training/session_screen.dart').readAsStringSync();

      expect(home.contains('dailyGoalProvider'), isTrue);
      expect(session.contains('dailyGoalProvider'), isTrue);

      // …and neither builds a goal out of answers any more.
      for (final src in [home, session]) {
        expect(src.contains('todayReviewCount'), isFalse);
        expect(src.contains('dailyActivityProvider'), isFalse);
      }
    });
  });
}
