import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/search/word_list.dart';

/// A miniature asset in the real format: one word per line, MOST FREQUENT FIRST. Deliberately not
/// alphabetical — the whole design rests on the file being ranked and the index being sorted.
const _blob = '''
the
you
under
understand
up
a
unusual
apple
appointment
i
undo
''';

void main() {
  final list = WordList.parse(_blob);

  group('parsing', () {
    test('reads every line, and keeps the file order as the frequency rank', () {
      expect(list.length, 11);
      // «the» is rank 0 and «you» rank 1, so a prefix matching both must offer «the» first —
      // asserted in the ranking group below.
    });

    test('survives an asset with no trailing newline', () {
      expect(WordList.parse('one\ntwo').length, 2);
      expect(WordList.parse('one\ntwo').startingWith('tw'), ['two']);
    });

    test('an empty asset is empty, not a crash', () {
      expect(WordList.parse('').startingWith('un'), isEmpty);
    });
  });

  group('prefix search', () {
    test('finds every word with the prefix', () {
      expect(list.startingWith('und').toSet(), {'under', 'understand', 'undo'});
    });

    test('ranks the hits by FREQUENCY, not alphabetically', () {
      // Alphabetically it would be under / understand / undo; by frequency (file order) it is
      // under(2) / understand(3) / undo(10). The first two coincide, «undo» is the tell.
      expect(list.startingWith('und'), ['under', 'understand', 'undo']);

      // A sharper one: «u» words interleaved with «a» words in the file.
      expect(list.startingWith('ap'), ['apple', 'appointment']);
    });

    test('respects the limit, keeping the most frequent', () {
      expect(list.startingWith('un', limit: 2), ['under', 'understand']);
    });

    test('a word is a prefix of itself', () {
      expect(list.startingWith('apple'), ['apple']);
    });

    test('is case-insensitive and trims', () {
      expect(list.startingWith('  UNDer '), ['under', 'understand']);
    });

    test('says nothing for a prefix shorter than the floor', () {
      // One character matches thousands of words and ranks them by nothing useful — the learner
      // has not said enough yet for a suggestion to BE a suggestion.
      expect(list.startingWith('u'), isEmpty);
      expect(list.startingWith(''), isEmpty);
    });

    test('an unknown prefix is empty, not the nearest neighbour', () {
      // The binary search lands on a real position; the walk must reject it. Offering «apple» to
      // somebody typing «zz» would be worse than offering nothing.
      expect(list.startingWith('zz'), isEmpty);
      expect(list.startingWith('applesauce'), isEmpty);
    });

    test('a non-latin query is empty rather than an error', () {
      // Typing Cyrillic means searching by translation — the server handles that, this cannot.
      expect(list.startingWith('счёт'), isEmpty);
    });

    test('a prefix at the very end of the alphabet does not run off the array', () {
      expect(WordList.parse('zebra\nzoo').startingWith('zo'), ['zoo']);
      expect(WordList.parse('zebra\nzoo').startingWith('zz'), isEmpty);
    });
  });

  group('the real asset', () {
    // Guards the contract between the build script and the loader: the file is lowercase, one word
    // per line, letters only, and ordered by frequency. A regenerated list that broke any of those
    // would still load and would quietly rank suggestions by nothing.
    testWidgets('is present, well-formed and frequency-ordered', (tester) async {
      final real = await WordList.load();

      expect(real.length, greaterThan(30000));
      expect(real.length, lessThan(60000));

      // «the» and «you» are the two most frequent words in any English corpus; if the file were
      // alphabetical, «th…» would rank «thank» or «that» above «the».
      expect(real.startingWith('the', limit: 1), ['the']);
      expect(real.startingWith('you', limit: 1), ['you']);

      // Letters only — the filter in build_wordlist.sh, asserted from this side.
      for (final word in real.startingWith('un', limit: 5)) {
        expect(word, matches(RegExp(r'^[a-z]+$')), reason: '«$word» is not a plain lowercase word');
      }
    });
  });
}
