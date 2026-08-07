import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/collection_cta.dart';
import 'package:eng_std/features/home/home_cta.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('computeCollectionCta — priority due → learn → triage → practice → none (F8)', () {
    test('due wins over everything', () {
      final cta = computeCollectionCta(untriaged: 18, learnable: 5, due: 12, total: 24);
      expect(cta.kind, HomeCtaKind.review);
      expect(cta.count, 12);
    });

    test('no due but learnable → learn (above triage) — the F8 fix', () {
      final cta = computeCollectionCta(untriaged: 6, learnable: 18, due: 0, total: 24);
      expect(cta.kind, HomeCtaKind.learn);
      expect(cta.count, 18);
    });

    test('all triaged-unknown, none studied → learn (was the empty-practice dead-end)', () {
      final cta = computeCollectionCta(untriaged: 0, learnable: 18, due: 0, total: 18);
      expect(cta.kind, HomeCtaKind.learn);
      expect(cta.count, 18);
    });

    test('no due, no learnable but untriaged → triage', () {
      final cta = computeCollectionCta(untriaged: 18, learnable: 0, due: 0, total: 24);
      expect(cta.kind, HomeCtaKind.triage);
      expect(cta.count, 18);
    });

    test('everything in SRS, nothing due/learnable/untriaged → practice (quiet)', () {
      final cta = computeCollectionCta(untriaged: 0, learnable: 0, due: 0, total: 24);
      expect(cta.kind, HomeCtaKind.practice);
    });

    test('empty collection → none', () {
      final cta = computeCollectionCta(untriaged: 0, learnable: 0, due: 0, total: 0);
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
