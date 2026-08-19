import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:flutter_test/flutter_test.dart';

/// QA-27 — which chips a wrong assembly marks.
///
/// The rule is an alignment (LCS over normalised tokens), not a position-by-position comparison:
/// everything that survives the alignment is a word that IS in the expected answer, in the right
/// order, so what is left over is the MINIMAL set of words standing in the wrong place. The
/// terracotta wave marks that set and nothing else — the verdict convention is unchanged.
void main() {
  /// The sentence from the phone report, and the way the learner assembled it.
  const target = "Can you turn on the light? It's too dark to read here.";
  const asBuilt = ['Can', 'you', 'turn', 'on', 'the', 'light?', "It's", 'too', 'dark', 'here', 'to', 'read'];

  group('misplacedWords marks the minimal set of misplaced chips', () {
    test('the phone case: only «here» is out of place, «It\'s» is not', () {
      // The bug: the chip "It's" canonicalises to two words while the expected side counts them as
      // two of its own, so it could never match and was marked wherever it stood — and its unmatched
      // pair dragged the alignment along and marked «here» for the wrong reason.
      final wrong = SessionGrader.misplacedWords(asBuilt, target);

      expect(wrong, {asBuilt.indexOf('here')});
      expect(wrong, isNot(contains(asBuilt.indexOf("It's"))));
    });

    test('a fully correct assembly marks nothing', () {
      const correct = ['Can', 'you', 'turn', 'on', 'the', 'light?', "It's", 'too', 'dark', 'to', 'read', 'here.'];
      expect(SessionGrader.misplacedWords(correct, target), isEmpty);
    });

    test('two neighbours swapped: one of them is marked, not both and not the tail', () {
      const swapped = ['Can', 'you', 'turn', 'on', 'the', 'light?', "It's", 'dark', 'too', 'to', 'read', 'here.'];
      final wrong = SessionGrader.misplacedWords(swapped, target);

      expect(wrong, hasLength(1));
      expect(wrong.single, anyOf(swapped.indexOf('too'), swapped.indexOf('dark')));
    });

    test('a word that travelled to the end is marked alone', () {
      const travelled = ['you', 'turn', 'on', 'the', 'light?', "It's", 'too', 'dark', 'to', 'read', 'here.', 'Can'];
      expect(SessionGrader.misplacedWords(travelled, target), {travelled.length - 1});
    });

    test('a completely scrambled order marks fewer chips than there are chips', () {
      // A mark on every word says nothing the verdict did not already say. The alignment keeps the
      // longest ordered run, so something always survives.
      const scrambled = ['here.', 'read', 'to', 'dark', 'too', "It's", 'light?', 'the', 'on', 'turn', 'you', 'Can'];
      final wrong = SessionGrader.misplacedWords(scrambled, target);

      expect(wrong, isNotEmpty);
      expect(wrong.length, lessThan(scrambled.length));
    });

    test('a contraction is one chip on screen and is marked, or not, as one', () {
      // Marked when it really is in the wrong place…
      const moved = ["It's", 'Can', 'you', 'turn', 'on', 'the', 'light?', 'too', 'dark', 'to', 'read', 'here.'];
      expect(SessionGrader.misplacedWords(moved, target), {0});

      // …and never split into a fragment of itself.
      expect(SessionGrader.misplacedWords(["don't", 'stop'], "don't stop"), isEmpty);
      expect(SessionGrader.misplacedWords(['stop', "don't"], "don't stop"), hasLength(1));
    });

    test('punctuation and case are not mistakes', () {
      expect(SessionGrader.misplacedWords(['can', 'you', 'turn', 'on', 'the', 'light'],
          'Can you turn on the light?'), isEmpty);
    });
  });
}
