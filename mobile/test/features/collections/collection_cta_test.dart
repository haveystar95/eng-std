import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/collection_cta.dart';
import 'package:eng_std/features/home/home_cta.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('computeCollectionCta — triage is priority #1 (кадр 2.3)', () {
    test('untriaged wins over due', () {
      final cta = computeCollectionCta(untriaged: 18, due: 12, total: 24);
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.count, 18);
    });

    test('no untriaged → review', () {
      final cta = computeCollectionCta(untriaged: 0, due: 12, total: 24);
      expect(cta.kind, HomeCtaKind.review);
      expect(cta.count, 12);
    });

    test('debt cleared but words exist → practice (quiet)', () {
      final cta = computeCollectionCta(untriaged: 0, due: 0, total: 24);
      expect(cta.kind, HomeCtaKind.practice);
    });

    test('empty collection → none', () {
      final cta = computeCollectionCta(untriaged: 0, due: 0, total: 0);
      expect(cta.kind, HomeCtaKind.none);
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
