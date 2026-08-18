import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:eng_std/features/training/session/session_grading.dart';

/// The pure rules of the speaking trainer, on the client side of the mirror. Every number and every
/// branch here has a twin in `backend2/tests/Unit/Learning/SpeakingGateTest.php`; a divergence is
/// silent, which is why both sides are pinned.
void main() {
  group('which form the card asks for', () {
    test('word early, example from the dictation rung', () {
      expect(ExerciseMode.speaking.asksForExample(LearningLadder.stepAssembly), isFalse);
      expect(ExerciseMode.speaking.asksForExample(LearningLadder.stepTyping), isFalse);
      expect(ExerciseMode.speaking.asksForExample(LearningLadder.stepDictation), isTrue);
      // A threshold, not an equality — a rung above the top keeps the later form.
      expect(ExerciseMode.speaking.asksForExample(LearningLadder.stepDictation + 1), isTrue);
    });

    test('the word form when the ladder does not apply', () {
      // Free practice off the ladder, a `known` verification — nothing there has earned the later
      // form, and the word is what practice drills.
      expect(ExerciseMode.speaking.asksForExample(null), isFalse);
    });

    test('no other mode changes its question with the rung', () {
      for (final step in [LearningLadder.stepAssembly, LearningLadder.stepDictation, null]) {
        expect(ExerciseMode.typing.asksForExample(step), isFalse);
        expect(ExerciseMode.scramble.asksForExample(step), isTrue);
        expect(ExerciseMode.dictation.asksForExample(step), isTrue);
        expect(ExerciseMode.pickCorrect.asksForExample(step), isTrue);
      }
    });

    test('a card at the late rung with NO example degrades to the word form', () {
      final card = SessionCard(
        termId: 'T1',
        mode: ExerciseMode.speaking,
        type: 'word',
        prompt: 'бронь',
        answer: 'reservation',
        ladderStep: LearningLadder.stepDictation,
      );

      // The mode asks for the example; the card has none to ask for. Both halves live in
      // SessionCard.asksForExample so the prompt, the check and the feedback cannot disagree.
      expect(ExerciseMode.speaking.asksForExample(card.ladderStep), isTrue);
      expect(card.asksForExample, isFalse);
    });
  });

  group('admission', () {
    test('opens on the assembly rung, and not before', () {
      const matrix = ModeAdmission.shipped;

      expect(matrix.allows(ExerciseMode.speaking, LearningLadder.stepRecognitionForward), isFalse);
      expect(matrix.allows(ExerciseMode.speaking, LearningLadder.stepRecognitionReverse), isFalse);
      expect(matrix.allows(ExerciseMode.speaking, LearningLadder.stepAssembly), isTrue);
      expect(matrix.allows(ExerciseMode.speaking, LearningLadder.stepDictation), isTrue);
    });

    test('fits every term — a word with no example is still a word you can say', () {
      final bare = TermPlayability.of(answer: 'reservation');
      final full = TermPlayability.of(
        answer: 'reservation',
        example: 'I have a reservation for tonight.',
        exampleTranslation: 'У меня бронь на сегодня.',
      );

      expect(bare.supports(ExerciseMode.speaking), isTrue);
      expect(full.supports(ExerciseMode.speaking), isTrue);
    });
  });

  group('coverage — the read-aloud check', () {
    const sentence = 'Could you take a photo of us?';

    test('passes the readings a recogniser actually returns', () {
      // Dropped article, no punctuation, a swapped unstressed word: all three are a learner who
      // read the sentence correctly into a microphone in a real room.
      expect(SessionGrader.covers('could you take a photo of us', sentence), isTrue);
      expect(SessionGrader.covers('could you take photo of us', sentence), isTrue);
      expect(SessionGrader.covers('could you take a photo of as', sentence), isTrue);
    });

    test('fails a reading that stopped halfway, and an empty one', () {
      expect(SessionGrader.covers('could you take', sentence), isFalse);
      expect(SessionGrader.covers('', sentence), isFalse);
      expect(SessionGrader.covers('completely different words entirely', sentence), isFalse);
    });

    test('counts by multiset and never passes vacuously', () {
      expect(SessionGrader.coverageOf('it is very very cold', 'It is very very cold.'), 1.0);
      expect(SessionGrader.coverageOf('very', 'very very'), 0.5);
      expect(SessionGrader.coverageOf('anything at all', ''), 0.0);
    });

    test('agrees with the server on the threshold', () {
      // The one number both runtimes read. Moving it here without moving `SpokenCoverage` there
      // makes the phone show a verdict the server then contradicts.
      expect(SpokenAnswer.minCoverage, 0.7);
    });
  });

  group('suffix tolerance (QA-20)', () {
    // Live device finding: target "salary expectations" spoken correctly, but the on-device
    // recogniser transcribed "salary expectation" — the channel ate the trailing -s — and the app
    // showed a false "Not quite" for an answer the learner actually got right. Mirror of
    // `SpeakingGateTest.php`'s "suffix tolerance (QA-20)" group; every case here has a twin there.

    test('forgives a dropped trailing -s on the word form, in either direction', () {
      expect(
        SessionGrader.check('salary expectation', 'salary expectations', spokenSuffixTolerance: true),
        LocalCheck.correct,
      );
      expect(
        SessionGrader.check('expectations', 'expectation', spokenSuffixTolerance: true),
        LocalCheck.correct,
      );
    });

    test('does not stretch the tolerance past one tolerated tail, or to a same-length word', () {
      // "expect" vs "expectations" differs by "ations", not by -s/-es/-'s.
      expect(
        SessionGrader.check('expect', 'expectations', spokenSuffixTolerance: true),
        LocalCheck.wrong,
      );
      // Same length, not a suffix relation at all — the existing no-typos behaviour for speaking.
      expect(
        SessionGrader.check('bear', 'bare', spokenSuffixTolerance: true, forgiveTypos: false),
        LocalCheck.wrong,
      );
    });

    test('is off by default — every non-speaking call site keeps its own (typo) leniency only', () {
      // Same input as the first case, but without the flag speaking passes: it still gets the
      // ordinary typo leniency (capped at «Почти»), never the full «Верно» the flag grants.
      expect(SessionGrader.check('salary expectation', 'salary expectations'), LocalCheck.typo);
      expect(
        SessionGrader.check('salary expectation', 'salary expectations', forgiveTypos: false),
        LocalCheck.wrong,
      );
    });

    test('forgives the same dropped -s inside a spoken example sentence too', () {
      // Short sentence on purpose: without the per-word tolerance, "expectation" for
      // "expectations" is a plain miss and 2 of 3 words covered (67%) already falls under the 70%
      // floor — this only passes because the tolerant match keeps the word "found".
      expect(SessionGrader.covers('Salary expectation rise', 'Salary expectations rise'), isTrue);
    });
  });

  group('uncovered words — the verdict highlight (QA-20)', () {
    const sentence = 'Could you take a photo of us?';

    test('marks the tail a cut-off reading never reached', () {
      expect(SessionGrader.uncoveredWords('could you take a photo', sentence), {5, 6});
    });

    test('marks just the one word a recogniser typically eats, not the whole sentence', () {
      // The dropped article — exactly the kind of miss `covers()` still forgives at the ratio
      // level (6 of 7 = 86%), but the highlight is about WHICH word, not the pass/fail bar.
      expect(SessionGrader.uncoveredWords('could you take photo of us', sentence), {3});
    });

    test('marks nothing when every word was heard', () {
      expect(SessionGrader.uncoveredWords('could you take a photo of us', sentence), isEmpty);
    });

    test('is suffix-tolerant, like coverage itself', () {
      expect(
        SessionGrader.uncoveredWords('salary expectation rise', 'Salary expectations rise'),
        isEmpty,
      );
    });

    test('marks every word when nothing was heard', () {
      expect(SessionGrader.uncoveredWords('', sentence), {0, 1, 2, 3, 4, 5, 6});
    });
  });
}
