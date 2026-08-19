import 'package:eng_std/features/home/home_cta.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('computeHomeCta — due → learn/limit → triage → none', () {
    test('due terms win, whatever else exists — and are never gated by the new quota (F13 rule 1)', () {
      final cta = computeHomeCta(
        due: 12,
        learnable: 4,
        untriagedByCollection: {'a': 5},
        remainingNewQuota: 0, // quota spent, but reviews are still offered
      );
      expect(cta.kind, HomeCtaKind.review);
      expect(cta.count, 12);
    });

    test('no due but learnable, quota available → learn, count = min(sum, quota) (F13 rule 2)', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 18,
        untriagedByCollection: {'c': 5},
        remainingNewQuota: 20,
      );
      expect(cta.kind, HomeCtaKind.learn);
      expect(cta.count, 18);
    });

    test('learn count is capped at the remaining new quota (F13 rule 2)', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 18,
        untriagedByCollection: const {},
        remainingNewQuota: 5, // only 5 new left today
      );
      expect(cta.kind, HomeCtaKind.learn);
      expect(cta.count, 5);
    });

    test('quota spent but learnable words remain → limitReached, not a blocked session (F13 rule 3)', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 5,
        untriagedByCollection: const {},
        remainingNewQuota: 0,
      );
      expect(cta.kind, HomeCtaKind.limitReached);
    });

    test('transition within a day: learn while quota > 0, then limitReached once it is spent', () {
      final before = computeHomeCta(
        due: 0,
        learnable: 5,
        untriagedByCollection: const {},
        remainingNewQuota: 2,
      );
      expect(before.kind, HomeCtaKind.learn);
      expect(before.count, 2);

      // Same learnable words; the day's new quota is now exhausted.
      final after = computeHomeCta(
        due: 0,
        learnable: 5,
        untriagedByCollection: const {},
        remainingNewQuota: 0,
      );
      expect(after.kind, HomeCtaKind.limitReached);
    });

    test('an empty pool falls through to triage', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 0,
        untriagedByCollection: {'b': 7},
        remainingNewQuota: 20,
      );
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.count, 7);
    });

    test('no learnable words + quota 0 → triage, NOT limitReached (limit is about pending new words)', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 0,
        untriagedByCollection: {'a': 3, 'b': 7, 'c': 2},
        remainingNewQuota: 0,
      );
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.count, 12);
      expect(cta.collectionId, 'b');
    });

    test('triage ties broken by collection id (stable)', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 0,
        untriagedByCollection: {'z': 4, 'a': 4},
        remainingNewQuota: 20,
      );
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.collectionId, 'a');
    });

    test('nothing eligible → none (F13 rule 4: all learned, due 0, no new — screen shows all-done)', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 0,
        untriagedByCollection: {'a': 0, 'b': 0},
        remainingNewQuota: 20,
      );
      expect(cta.kind, HomeCtaKind.none);
    });

    test('empty everything → none (new user)', () {
      final cta = computeHomeCta(
        due: 0,
        learnable: 0,
        untriagedByCollection: const {},
        remainingNewQuota: 20,
      );
      expect(cta.kind, HomeCtaKind.none);
    });
  });
}
