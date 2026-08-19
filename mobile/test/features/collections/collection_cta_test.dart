import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/collection_cta.dart';
import 'package:eng_std/features/home/home_cta.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('computeCollectionCta — due → learn/limit → triage → none (F8/F17/F13b)', () {
    test('due wins over everything, even with the new quota spent (reviews never gated)', () {
      final cta = computeCollectionCta(untriaged: 18, learnable: 5, due: 12, remainingNewQuota: 0);
      expect(cta.kind, HomeCtaKind.review);
      expect(cta.count, 12);
    });

    test('no due but learnable, quota available → learn (above triage) — the F8 fix', () {
      final cta = computeCollectionCta(untriaged: 6, learnable: 18, due: 0, remainingNewQuota: 20);
      expect(cta.kind, HomeCtaKind.learn);
      expect(cta.count, 18);
    });

    test('learn count is capped at the remaining new quota (F13b, same gate as home)', () {
      final cta = computeCollectionCta(untriaged: 0, learnable: 18, due: 0, remainingNewQuota: 4);
      expect(cta.kind, HomeCtaKind.learn);
      expect(cta.count, 4);
    });

    test('quota spent but learnable words remain → limitReached (F13b)', () {
      final cta = computeCollectionCta(untriaged: 0, learnable: 18, due: 0, remainingNewQuota: 0);
      expect(cta.kind, HomeCtaKind.limitReached);
    });

    test('no due, no learnable but untriaged → triage (limit is only about pending new words)', () {
      final cta = computeCollectionCta(untriaged: 18, learnable: 0, due: 0, remainingNewQuota: 0);
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.count, 18);
    });

    test('everything in SRS, nothing due/learnable/untriaged → none', () {
      final cta = computeCollectionCta(untriaged: 0, learnable: 0, due: 0, remainingNewQuota: 20);
      expect(cta.kind, HomeCtaKind.none);
    });
  });

  group('showsSecondaryTriage — the pass stays reachable until it is finished (QA-25)', () {
    test('partially triaged: «Учить» takes the primary slot, the pass keeps its own button', () {
      // The phone repro: 3 of 40 swiped, the swipes enrolled them, «Учить 3» outranked triage — and
      // the other 37 words had nothing left that reached them.
      final cta = computeCollectionCta(untriaged: 37, learnable: 3, due: 0, remainingNewQuota: 20);
      expect(cta.kind, HomeCtaKind.learn);
      expect(showsSecondaryTriage(cta, 37), isTrue);
    });

    test('a due count does not take it away either — reviews outrank triage, they do not end it', () {
      final cta = computeCollectionCta(untriaged: 37, learnable: 3, due: 12, remainingNewQuota: 20);
      expect(cta.kind, HomeCtaKind.review);
      expect(showsSecondaryTriage(cta, 37), isTrue);
    });

    test('nor does the daily new quota being spent — the pass never spends it', () {
      final cta = computeCollectionCta(untriaged: 37, learnable: 3, due: 0, remainingNewQuota: 0);
      expect(cta.kind, HomeCtaKind.limitReached);
      expect(showsSecondaryTriage(cta, 37), isTrue);
    });

    test('the last word swiped → the button goes away', () {
      final cta = computeCollectionCta(untriaged: 0, learnable: 40, due: 0, remainingNewQuota: 20);
      expect(showsSecondaryTriage(cta, 0), isFalse);
    });

    test('never doubled: when triage IS the primary CTA there is no second copy of it', () {
      final cta = computeCollectionCta(untriaged: 40, learnable: 0, due: 0, remainingNewQuota: 20);
      expect(cta.kind, HomeCtaKind.triage);
      expect(showsSecondaryTriage(cta, 40), isFalse);
    });
  });

  group('homeGoalRing — new_today vs new_goal (F13b)', () {
    Stats stats({required int newGoal, required int newRemaining, int reviews = 0}) => Stats(
          totalWords: 0, learned: 0, mastered: 0, dueToday: 0,
          reviewsTotal: reviews, streakDays: 0, newGoal: newGoal, newRemaining: newRemaining,
        );

    test('a day of 10 reviews + 3 new against goal 30 → ring shows 3/30 (reviews do not inflate it)', () {
      // 10 repeats + 3 new introductions = 13 reviews_today; new_today = 30 − 27 = 3.
      final ring = homeGoalRing(stats(newGoal: 30, newRemaining: 27, reviews: 13));
      expect(ring.done, 3);
      expect(ring.goal, 30);
    });

    test('done is clamped into [0, goal]', () {
      expect(homeGoalRing(stats(newGoal: 5, newRemaining: 0)).done, 5); // quota fully spent
      expect(homeGoalRing(stats(newGoal: 5, newRemaining: 9)).done, 0); // never negative
    });
  });

  group('classifyDensity — partitions every term into one bucket (§4)', () {
    test('mastered → confirmed (known, or review with long interval)', () {
      expect(classifyDensity('known', 0), DensityBucket.confirmed);
      expect(classifyDensity('review', 21), DensityBucket.confirmed);
    });

    test('in SRS but not mastered → familiar', () {
      expect(classifyDensity('review', 5), DensityBucket.familiar);
      expect(classifyDensity('learning', 0), DensityBucket.familiar);
      expect(classifyDensity('relearning', 0), DensityBucket.familiar);
    });

    test('new / untouched / triaged-unknown → in-progress', () {
      expect(classifyDensity('new', 0), DensityBucket.inProgress);
      expect(classifyDensity(null, 0), DensityBucket.inProgress);
    });
  });
}
