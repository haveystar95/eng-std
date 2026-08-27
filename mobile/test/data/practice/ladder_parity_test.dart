import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:flutter_test/flutter_test.dart';

/// PARITY with the server's acquisition ladder.
///
/// [LearningLadder.stepFor] exists twice — here and in PHP — because the device builds sessions
/// offline. Two implementations of one rule drift, and this one drifts SILENTLY: nothing fails, the
/// phone simply deals a card the server would not have dealt.
///
/// So the whole table is walked, not a sample of it. The expectations below are transcribed from
/// `backend2/tests/Unit/Learning/ExerciseSelectorTest.php` («derives the rung from (acquisition,
/// successful_reviews, learning_step)») — same rows, same order, same answers — plus the admission matrix from
/// `ModeAdmission::shipped()`, which the server seeds `learning_mode_settings` with and sends down
/// `/sync`.
void main() {
  group('the rung, for every row of the server table', () {
    // (acquisition, successful_reviews, learning_step, isKnown) → rung. The server's dataset,
    // verbatim. The counter is SUCCESSES, not the scheduler's `reps` — see QA-18 in the ladder.
    const rows = <(String, Acquisition, int, int, bool, int?)>[
      ('never shown → intro', Acquisition.isNew, 0, 0, false, 0),
      ('never shown, a counter survived a known undo', Acquisition.isNew, 9, 0, false, 0),
      ('introduced → recognition forward', Acquisition.learning, 0, 1, false, 1),
      ('forward passed → recognition reverse', Acquisition.learning, 0, 2, false, 2),
      ('graduated, no SRS review yet', Acquisition.graduated, 0, 0, false, 3),
      ('graduated, three successes in', Acquisition.graduated, 3, 0, false, 3),
      ('graduated, typing unlocked', Acquisition.graduated, 4, 0, false, 4),
      ('graduated, still rung 4', Acquisition.graduated, 5, 0, false, 4),
      ('graduated, dictation unlocked', Acquisition.graduated, 6, 0, false, 5),
      ('a long-established pair stays at 5', Acquisition.graduated, 40, 0, false, 5),
      ('known is outside the ladder', Acquisition.graduated, 0, 0, true, null),
      ('a known pair mid-ladder is still out', Acquisition.learning, 0, 1, true, null),
      ('a step from a newer build is clamped', Acquisition.learning, 0, 7, false, 2),
    ];

    for (final (name, acquisition, successes, learningStep, isKnown, expected) in rows) {
      test(name, () {
        expect(
          LearningLadder.stepFor(
            acquisition: acquisition,
            successfulReviews: successes,
            learningStep: learningStep,
            isKnown: isKnown,
          ),
          expected,
        );
      });
    }
  });

  test('the rung counts SUCCESSES — the `antipyretic` case, on both runtimes', () {
    // The live bug (QA-18): four misses and two hits. The scheduler was called six times, so the
    // old ladder read 6 and dealt dictation. The pair has been recalled twice, and stands at
    // assembly. The phone must reach the same verdict as the server's SuccessfulReviewsTest.
    expect(
      LearningLadder.stepFor(
        acquisition: Acquisition.graduated,
        successfulReviews: 2,
        learningStep: 0,
      ),
      LearningLadder.stepAssembly,
    );
  });

  test('the rung is a pure function — the same inputs always answer the same', () {
    for (var successes = 0; successes <= 12; successes++) {
      for (final a in Acquisition.values) {
        for (var s = 0; s <= 2; s++) {
          final first = LearningLadder.stepFor(
            acquisition: a,
            successfulReviews: successes,
            learningStep: s,
          );
          final second = LearningLadder.stepFor(
            acquisition: a,
            successfulReviews: successes,
            learningStep: s,
          );
          expect(first, second);
        }
      }
    }
  });

  test('every rung the ladder can produce is one this build knows how to deal', () {
    // A rung with no admitted trainer would be a card nobody can play. `known` (null) is the one
    // legitimate answer with no rung, and it is handled before the matrix is ever consulted.
    for (var successes = 0; successes <= 40; successes++) {
      for (final a in Acquisition.values) {
        for (var s = 0; s <= 2; s++) {
          final step = LearningLadder.stepFor(
            acquisition: a,
            successfulReviews: successes,
            learningStep: s,
          );
          expect(step, isNotNull);
          expect(step, inInclusiveRange(LearningLadder.stepIntro, LearningLadder.stepDictation));
        }
      }
    }
  });

  group('the admission matrix, for every mode of the server table', () {
    // mode → the rungs that admit it. Transcribed from the server's `admission` dataset.
    const table = <(ExerciseMode, List<int>)>[
      (ExerciseMode.intro, [0]),
      (ExerciseMode.multipleChoice, [1, 2, 3, 4, 5]),
      (ExerciseMode.wordBank, [3, 4, 5]),
      (ExerciseMode.cloze, [3, 4, 5]),
      (ExerciseMode.scramble, [3, 4, 5]),
      (ExerciseMode.pickCorrect, [3, 4, 5]),
      (ExerciseMode.typing, [4, 5]),
      (ExerciseMode.listening, [4, 5]),
      (ExerciseMode.dictation, [5]),
    ];

    for (final (mode, rungs) in table) {
      test('${mode.wire} is admitted exactly at ${rungs.join(", ")}', () {
        for (var rung = 0; rung <= 5; rung++) {
          expect(
            ModeAdmission.shipped.allows(mode, rung),
            rungs.contains(rung),
            reason: '${mode.wire} at rung $rung',
          );
        }
      });
    }
  });

  test('a mode the matrix does not mention is admitted nowhere', () {
    // Fail-closed, matching the server: a trainer nobody has placed on the ladder is undealable,
    // not universally available — which is the difference between a missing row and a free pass.
    const partial = ModeAdmission({
      ExerciseMode.typing: ModeRule(mode: ExerciseMode.typing, minStep: 0),
    });

    for (var rung = 0; rung <= 5; rung++) {
      expect(partial.allows(ExerciseMode.dictation, rung), isFalse);
    }
  });

  test(
    'the wire matrix is read as sent, and an unreadable one falls back to the shipped default',
    () {
      final wire = ModeAdmission.fromWire([
        {'mode': 'typing', 'min_step': 2, 'options_policy': 'standard'},
        {'mode': 'multiple_choice', 'min_step': 1, 'options_policy': 'distant'},
        // A trainer a newer server has and this build does not: dropped, never guessed at.
        {'mode': 'time_travel', 'min_step': 0, 'options_policy': 'distant'},
      ]);

      expect(wire.allows(ExerciseMode.typing, 2), isTrue, reason: 'the server moved typing down');
      expect(wire.allows(ExerciseMode.typing, 1), isFalse);
      expect(wire.rules.length, 2, reason: 'the unknown mode is dropped');

      expect(ModeAdmission.fromWire(null).toString(), ModeAdmission.shipped.toString());
      expect(ModeAdmission.fromWire(const []).rules, ModeAdmission.shipped.rules);
    },
  );

  test('distant options are a ladder concession — a graduated pair always gets standard', () {
    // `distant` exists to make a FIRST meeting winnable. A pair off the ladder is past that by
    // definition, so the stored policy stops applying — the same rule the server applies.
    expect(
      ModeAdmission.shipped.optionsPolicyFor(ExerciseMode.multipleChoice, Acquisition.isNew),
      OptionsPolicy.distant,
    );
    expect(
      ModeAdmission.shipped.optionsPolicyFor(ExerciseMode.multipleChoice, Acquisition.learning),
      OptionsPolicy.distant,
    );
    expect(
      ModeAdmission.shipped.optionsPolicyFor(ExerciseMode.multipleChoice, Acquisition.graduated),
      OptionsPolicy.standard,
    );
  });

  group('WHICH RUNG A DRILL IS DEALT AT, in parity with the server', () {
    // Both sides answer ONE question about a practice card — «does this pair have a rung of its
    // own?» — and both answer it the same way: the client's `LadderPosition.drillsAtOwnRung`, the
    // server's `StudyCardAssembler::drillsAtOwnRung()`. Nobody is refused a drill any more, on
    // either side. The device builds its own practice sessions offline, so the rule exists twice
    // and, like the rung itself, it drifts silently: the phone would simply deal a card the server
    // would not have dealt.
    test('a word nobody took into study IS drilled — free practice is over the collection', () {
      for (final acquisition in Acquisition.values) {
        final position = LadderPosition(acquisition: acquisition, successfulReviews: 12);
        expect(position.drillsAtOwnRung, isFalse, reason: '$acquisition, out of the pool');
        // …and always at the same fixed rung: it has no rung of its own to be dealt at.
        expect(position.practiceCardStep, LearningLadder.stepUnenrolledPractice);
      }
      // …including a «знаю» pair, which is out of the pool BY the verdict. The drill claims nothing
      // about that verdict: practice never resolves the verification, on either side.
      expect(
        const LadderPosition(
          acquisition: Acquisition.graduated,
          isKnown: true,
        ).practiceCardStep,
        LearningLadder.stepUnenrolledPractice,
      );
    });

    test('the easy half of the matrix is what a word with no rung of its own may be asked', () {
      // «без диктанта/печати/аудирования» — the owner's rule for a word nothing has been earned on.
      // The matrix is the single place that is decided, and this is the row it resolves to.
      const graded = [
        ExerciseMode.multipleChoice,
        ExerciseMode.wordBank,
        ExerciseMode.cloze,
        ExerciseMode.scramble,
        ExerciseMode.pickCorrect,
        ExerciseMode.speaking,
        ExerciseMode.typing,
        ExerciseMode.listening,
        ExerciseMode.dictation,
      ];
      final opened = ModeAdmission.shipped.only(graded, LearningLadder.stepUnenrolledPractice);

      expect(opened, contains(ExerciseMode.multipleChoice));
      expect(
        opened,
        containsAll([ExerciseMode.wordBank, ExerciseMode.cloze, ExerciseMode.scramble]),
      );
      expect(opened, isNot(contains(ExerciseMode.typing)));
      expect(opened, isNot(contains(ExerciseMode.listening)));
      expect(opened, isNot(contains(ExerciseMode.dictation)));
    });

    test('in the pool, the rung decides the CARD — rung 0 is dealt the easy corner', () {
      // Not refused: refusing it was the client-only rule the owner overturned (BUG-2), and the
      // server had always drilled such a pair.
      expect(
        const LadderPosition(acquisition: Acquisition.isNew, enrolled: true).drillsAtOwnRung,
        isFalse,
        reason: 'a first meeting has not happened, so no rung has been earned',
      );
      expect(
        const LadderPosition(
          acquisition: Acquisition.isNew,
          enrolled: true,
        ).practiceCardStep,
        LearningLadder.stepUnenrolledPractice,
      );
      expect(
        const LadderPosition(
          acquisition: Acquisition.learning,
          learningStep: 1,
          enrolled: true,
        ).drillsAtOwnRung,
        isTrue,
      );
      expect(
        const LadderPosition(
          acquisition: Acquisition.graduated,
          successfulReviews: 12,
          enrolled: true,
        ).drillsAtOwnRung,
        isTrue,
      );
    });

    test('a term with no progress row at all is in the catalogue, not in the queue', () {
      expect(LadderPosition.untouched.enrolled, isFalse);
      // Drillable by «Тренировка по теме» — and still not in the queue: nothing about a practice
      // answer enrols it, and the STUDY selection on both sides is read strictly from the pool.
      expect(LadderPosition.untouched.drillsAtOwnRung, isFalse);
      expect(LadderPosition.untouched.practiceCardStep, LearningLadder.stepUnenrolledPractice);
    });
  });

  test('a pair outside the ladder is not held back by it', () {
    // `known` has no rung. Reading that as rung 0 would gate its verification down to the intro —
    // the one card that proves nothing about a claim.
    const known = LadderPosition(acquisition: Acquisition.graduated, isKnown: true, enrolled: true);

    expect(known.step, isNull);
    expect(known.admissionStep, LearningLadder.stepDictation);
    expect(ModeAdmission.shipped.allows(ExerciseMode.typing, known.admissionStep), isTrue);
  });
}
