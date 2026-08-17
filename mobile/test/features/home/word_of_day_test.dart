import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/home/word_of_day.dart';
import 'package:flutter_test/flutter_test.dart';

Word _w(String id) => Word(termId: id, term: id, translation: '$id-ru', type: 'word');

void main() {
  final terms = [_w('c'), _w('a'), _w('b')];

  group('pickWordOfDay', () {
    test('empty → null (block hides)', () {
      expect(pickWordOfDay(const [], 42), isNull);
    });

    test('deterministic for a given day seed', () {
      final a = pickWordOfDay(terms, 100);
      final b = pickWordOfDay(terms, 100);
      expect(a!.termId, b!.termId);
    });

    test('stable regardless of input order (sorted by id)', () {
      final fromOne = pickWordOfDay([_w('c'), _w('a'), _w('b')], 7);
      final fromAnother = pickWordOfDay([_w('b'), _w('c'), _w('a')], 7);
      expect(fromOne!.termId, fromAnother!.termId);
    });

    test('index stays in range for any (including negative) seed', () {
      for (final seed in [-5, -1, 0, 1, 2, 3, 999]) {
        final picked = pickWordOfDay(terms, seed);
        expect(terms.map((t) => t.termId), contains(picked!.termId));
      }
    });

    test('different days can land on different terms', () {
      final picks = {for (var s = 0; s < 3; s++) pickWordOfDay(terms, s)!.termId};
      expect(picks.length, 3); // 3 terms, seeds 0/1/2 map to each
    });
  });

  group('dayNumber', () {
    test('same calendar day (UTC) → same seed, next day differs', () {
      final d1 = DateTime.utc(2026, 8, 6, 1);
      final d2 = DateTime.utc(2026, 8, 6, 23);
      final d3 = DateTime.utc(2026, 8, 7, 0);
      expect(dayNumber(d1), dayNumber(d2));
      expect(dayNumber(d3), dayNumber(d1) + 1);
    });
  });
}
