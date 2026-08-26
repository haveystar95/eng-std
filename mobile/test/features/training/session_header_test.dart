import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/features/training/session/session_grading.dart';

/// What the session header names (QA-OBS-4, then Ч.4).
///
/// The strings for the acquisition ladder's own steps were written and then never connected: the
/// header kept deriving itself from the exercise MODE, and a mode cannot tell rung 0 from rungs 1–2
/// — a freshly met word's recognition cards are `multiple_choice`, and that maps to the intro phase.
/// So the two recognition steps were headed «Знакомство» / «Getting to know», naming the step before
/// them.
///
/// Ч.4 finished the same correction upwards. Above the recognition rungs the header still named a
/// PHASE while every other screen named a RUNG, so «написание» and «диктант» existed everywhere
/// except in the header of the session dealing them. The rung wins wherever the card has one.
void main() {
  group('the ladder names the card it dealt', () {
    test('the intro card is «Знакомство»', () {
      expect(
        sessionHeaderFor(mode: ExerciseMode.intro, ladderStep: LearningLadder.stepIntro),
        SessionHeader.rungMeeting,
      );
    });

    test('both recognition rungs are «Узнавание», whatever the mode is', () {
      for (final step in [
        LearningLadder.stepRecognitionForward,
        LearningLadder.stepRecognitionReverse,
      ]) {
        expect(
          sessionHeaderFor(mode: ExerciseMode.multipleChoice, ladderStep: step),
          SessionHeader.rungRecognition,
          reason: 'rung $step',
        );
        expect(
          sessionHeaderFor(mode: ExerciseMode.listening, ladderStep: step),
          SessionHeader.rungRecognition,
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
        SessionHeader.rungMeeting,
      );
    });

    test('the upper rungs name themselves too — «Сборка», «Написание», «Диктант»', () {
      // The Ч.4 half. The mode no longer decides: a dictation card dealt at the assembly rung is
      // headed «Сборка», because the header answers «где я с этим словом» and not «какое задание».
      expect(
        sessionHeaderFor(mode: ExerciseMode.wordBank, ladderStep: LearningLadder.stepAssembly),
        SessionHeader.rungAssembly,
      );
      expect(
        sessionHeaderFor(mode: ExerciseMode.typing, ladderStep: LearningLadder.stepTyping),
        SessionHeader.rungWriting,
      );
      expect(
        sessionHeaderFor(mode: ExerciseMode.dictation, ladderStep: LearningLadder.stepDictation),
        SessionHeader.rungDictation,
      );
      // …and the same rung names the card whatever the trainer on it is.
      expect(
        sessionHeaderFor(mode: ExerciseMode.speaking, ladderStep: LearningLadder.stepTyping),
        SessionHeader.rungWriting,
      );
    });
  });

  group('a card with no rung keeps the phase it always had', () {
    test('a «знаю» verification (no rung) falls back to the phase', () {
      expect(
        sessionHeaderFor(mode: ExerciseMode.multipleChoice, ladderStep: null),
        SessionHeader.phaseIntro,
      );
      expect(
        sessionHeaderFor(mode: ExerciseMode.typing, ladderStep: null),
        SessionHeader.phaseReview,
      );
      expect(
        sessionHeaderFor(mode: ExerciseMode.wordBank, ladderStep: null),
        SessionHeader.phaseAssemble,
      );
    });
  });
}
