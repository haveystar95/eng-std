import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/features/training/session/session_grading.dart';

/// What the session header names (QA-OBS-4).
///
/// The strings for the acquisition ladder's own steps were written and then never connected: the
/// header kept deriving itself from the exercise MODE, and a mode cannot tell rung 0 from rungs 1–2
/// — a freshly met word's recognition cards are `multiple_choice`, and that maps to the intro phase.
/// So the two recognition steps were headed «Знакомство» / «Getting to know», naming the step before
/// them. Everything above the recognition rungs is unchanged — the device pass confirmed it right.
void main() {
  group('the ladder names the card it dealt', () {
    test('the intro card is «Знакомство»', () {
      expect(
        sessionHeaderFor(mode: ExerciseMode.intro, ladderStep: LearningLadder.stepIntro),
        SessionHeader.intro,
      );
    });

    test('both recognition rungs are «Узнавание», whatever the mode is', () {
      for (final step in [
        LearningLadder.stepRecognitionForward,
        LearningLadder.stepRecognitionReverse,
      ]) {
        expect(
          sessionHeaderFor(mode: ExerciseMode.multipleChoice, ladderStep: step),
          SessionHeader.recognition,
          reason: 'rung $step',
        );
        expect(
          sessionHeaderFor(mode: ExerciseMode.listening, ladderStep: step),
          SessionHeader.recognition,
          reason: 'rung $step, heard',
        );
      }
    });

    test('an intro card stays «Знакомство» even if the rung says otherwise', () {
      // The intro IS rung 0 by construction; the mode is checked first so a mislabelled rung can
      // never head the intro card «Узнавание».
      expect(
        sessionHeaderFor(
          mode: ExerciseMode.intro,
          ladderStep: LearningLadder.stepRecognitionForward,
        ),
        SessionHeader.intro,
      );
    });
  });

  group('a graduated word keeps the phase it always had', () {
    test('assembly reads as «Сборка»', () {
      expect(
        sessionHeaderFor(mode: ExerciseMode.wordBank, ladderStep: LearningLadder.stepAssembly),
        SessionHeader.phaseAssemble,
      );
    });

    test('typing, dictation, speaking and the rest read as «Повторение»', () {
      for (final mode in [
        ExerciseMode.typing,
        ExerciseMode.dictation,
        ExerciseMode.speaking,
        ExerciseMode.scramble,
        ExerciseMode.pickCorrect,
        ExerciseMode.descriptionMatch,
      ]) {
        expect(
          sessionHeaderFor(mode: mode, ladderStep: LearningLadder.stepTyping),
          SessionHeader.phaseReview,
          reason: '$mode',
        );
      }
    });

    test('a card off the ladder (a «знаю» verification: no rung) falls back to the phase', () {
      expect(
        sessionHeaderFor(mode: ExerciseMode.multipleChoice, ladderStep: null),
        SessionHeader.phaseIntro,
      );
    });
  });
}
