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
}
