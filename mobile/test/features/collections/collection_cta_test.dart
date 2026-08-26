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

    test(
      'a due count does not take it away either — reviews outrank triage, they do not end it',
      () {
        final cta = computeCollectionCta(
          untriaged: 37,
          learnable: 3,
          due: 12,
          remainingNewQuota: 20,
        );
        expect(cta.kind, HomeCtaKind.review);
        expect(showsSecondaryTriage(cta, 37), isTrue);
      },
    );

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

  group('dailyGoalTarget — whose number the goal is measured against', () {
    test('the server quota once /stats has arrived', () {
      expect(dailyGoalTarget(newGoal: 30, profileGoal: 20), 30);
    });

    test('the profile goal until it has (offline first run)', () {
      expect(dailyGoalTarget(newGoal: 0, profileGoal: 15), 15);
    });

    test('never zero — nothing on either screen divides by it', () {
      expect(dailyGoalTarget(newGoal: 0, profileGoal: 0), kDefaultDailyGoal);
    });
  });

  /// Ч.4 — the collection bar in the STATUS VOCABULARY: освоено · в работе · разобрать.
  ///
  /// It reads the POOL and not the scheduler, which is the whole correction. `state` says WHEN a
  /// word comes back; the bar is asking WHETHER the learner is studying it, and those are different
  /// questions about a word that has been taken in and not yet dealt.
  group('classifyDensity — partitions every term into one bucket (§4)', () {
    DensityBucket row({String? state, int interval = 0, bool enrolled = false}) =>
        classifyDensity(state: state, intervalDays: interval, enrolled: enrolled);

    test('mastered wins over everything — «Освоено»', () {
      expect(row(state: 'known', enrolled: true), DensityBucket.mastered);
      expect(row(state: 'review', interval: 21, enrolled: true), DensityBucket.mastered);
    });

    test('in the queue and not mastered → «В работе», whatever the scheduler says', () {
      expect(row(state: 'review', interval: 5, enrolled: true), DensityBucket.inWork);
      expect(row(state: 'learning', enrolled: true), DensityBucket.inWork);
      expect(row(state: 'relearning', enrolled: true), DensityBucket.inWork);
      // The case the old rule got backwards: taken into study, never dealt. `state` is still `new`,
      // and the old bar drew it in the segment its own legend called «в работе» while meaning
      // «ещё не тронуто» — one phrase, two opposite meanings, one screen apart.
      expect(row(state: 'new', enrolled: true), DensityBucket.inWork);
    });

    test('not in the queue → «Разобрать»', () {
      expect(row(state: 'new'), DensityBucket.toSort);
      expect(row(), DensityBucket.toSort);
      // The stated limit: a PAUSED word lands here too. From the shelf's point of view it is again
      // a word nobody has decided to study, and the mirror does not keep «was this ever enrolled».
      expect(row(state: 'review', interval: 5), DensityBucket.toSort);
    });
  });
}
