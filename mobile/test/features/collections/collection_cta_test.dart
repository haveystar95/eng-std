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
