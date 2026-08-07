import 'package:eng_std/features/home/home_cta.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('computeHomeCta — priority due → learn → triage → practice → none', () {
    test('due terms win, whatever else exists', () {
      final cta = computeHomeCta(
        due: 12,
        learnableByCollection: {'a': 4},
        untriagedByCollection: {'a': 5},
        totalWords: 40,
      );
      expect(cta.kind, HomeCtaKind.review);
      expect(cta.count, 12);
    });

    test('no due but learnable → learn, count = sum (above triage) — F8', () {
      final cta = computeHomeCta(
        due: 0,
        learnableByCollection: {'a': 8, 'b': 10},
        untriagedByCollection: {'c': 5},
        totalWords: 40,
      );
      expect(cta.kind, HomeCtaKind.learn);
      expect(cta.count, 18);
    });

    test('learn zero-count entries ignored → falls through to triage', () {
      final cta = computeHomeCta(
        due: 0,
        learnableByCollection: {'a': 0, 'b': 0},
        untriagedByCollection: {'b': 7},
        totalWords: 40,
      );
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.count, 7);
    });

    test('no due, no learnable but untriaged → triage, count = sum, target = most-eligible', () {
      final cta = computeHomeCta(
        due: 0,
        learnableByCollection: const {},
        untriagedByCollection: {'a': 3, 'b': 7, 'c': 2},
        totalWords: 40,
      );
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.count, 12);
      expect(cta.collectionId, 'b'); // 7 is the most
    });

    test('triage ties broken by collection id (stable)', () {
      final cta = computeHomeCta(
        due: 0,
        learnableByCollection: const {},
        untriagedByCollection: {'z': 4, 'a': 4},
        totalWords: 8,
      );
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.collectionId, 'a');
    });

    test('zero-count triage entries are ignored', () {
      final cta = computeHomeCta(
        due: 0,
        learnableByCollection: const {},
        untriagedByCollection: {'a': 0, 'b': 0},
        totalWords: 5,
      );
      expect(cta.kind, HomeCtaKind.practice); // nothing eligible → practice
    });

    test('no due, no learnable, no untriaged, but words exist → practice', () {
      final cta = computeHomeCta(
        due: 0,
        learnableByCollection: const {},
        untriagedByCollection: const {},
        totalWords: 30,
      );
      expect(cta.kind, HomeCtaKind.practice);
    });

    test('empty everything → none (new user)', () {
      final cta = computeHomeCta(
        due: 0,
        learnableByCollection: const {},
        untriagedByCollection: const {},
        totalWords: 0,
      );
      expect(cta.kind, HomeCtaKind.none);
    });
  });
}
