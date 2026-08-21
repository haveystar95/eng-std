import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/search/suggestions.dart';
import 'package:eng_std/data/search/word_list.dart';

SearchHit _hit(String text, {String? translation = 'счёт'}) => SearchHit(
      termId: 'term-$text',
      text: text,
      type: 'word',
      translation: translation,
    );

void main() {
  group('merge order', () {
    test('words we already know come FIRST, whatever the dictionary says', () {
      final merged = mergeSuggestions(
        known: [_hit('invoice')],
        dictionary: const ['income', 'increase', 'indeed'],
      );

      // Not a spelling contest: a catalogue word can show what it means and be saved on the spot,
      // a dictionary word still has to be looked up. Different kinds of answer, and the one the
      // learner can act on goes on top.
      expect(merged.first.word, 'invoice');
      expect(merged.first.isKnown, isTrue);
      expect(merged.first.translation, 'счёт');
    });

    test('a dictionary word carries no translation — that IS the distinction', () {
      final merged = mergeSuggestions(known: const [], dictionary: const ['income']);

      expect(merged.single.isKnown, isFalse);
      expect(merged.single.translation, isNull);
      expect(merged.single.termId, isNull);
    });

    test('the same word is never offered twice', () {
      // The catalogue and the dictionary overlap constantly — every saved word is also an English
      // word. Showing it once with a translation and once without would read as a bug.
      final merged = mergeSuggestions(
        known: [_hit('income')],
        dictionary: const ['income', 'increase'],
      );

      expect(merged.map((s) => s.word), ['income', 'increase']);
    });

    test('deduplicates case-insensitively', () {
      final merged = mergeSuggestions(
        known: [_hit('Invoice')],
        dictionary: const ['invoice', 'income'],
      );

      expect(merged.map((s) => s.word), ['Invoice', 'income']);
    });

    test('honours the limit, and known words may fill it entirely', () {
      final merged = mergeSuggestions(
        known: [_hit('a'), _hit('b'), _hit('c'), _hit('d'), _hit('e'), _hit('f')],
        dictionary: const ['income'],
        limit: 5,
      );

      expect(merged, hasLength(5));
      expect(merged.every((s) => s.isKnown), isTrue);
    });

    test('drops blank entries rather than rendering empty rows', () {
      final merged = mergeSuggestions(known: [_hit('   ')], dictionary: const ['', '  ', 'income']);

      expect(merged.map((s) => s.word), ['income']);
    });
  });

  group('lazy loading', () {
    test('reads the asset at most once, even under concurrent callers', () async {
      var reads = 0;
      final loader = WordListLoader(read: () async {
        reads++;

        return WordList.parse('income\nincrease\n');
      });

      final results = await Future.wait([loader.ensureLoaded(), loader.ensureLoaded()]);
      await loader.ensureLoaded();

      expect(reads, 1, reason: 'two screens opening at once must share one parse');
      expect(results.first.startingWith('inc'), ['income', 'increase']);
    });

    test('exposes nothing until it is read, then exposes it synchronously', () async {
      final loader = WordListLoader(read: () async => WordList.parse('income\n'));

      expect(loader.loaded, isNull);
      await loader.ensureLoaded();
      // A synchronous handle is what lets the next keystroke paint suggestions without a frame of
      // flicker while a future resolves.
      expect(loader.loaded?.startingWith('inc'), ['income']);
    });

    test('a missing asset yields no suggestions instead of breaking search', () async {
      final loader = WordListLoader(read: () async => throw Exception('asset not found'));

      final list = await loader.ensureLoaded();

      expect(list.startingWith('inc'), isEmpty);
    });
  });
}
