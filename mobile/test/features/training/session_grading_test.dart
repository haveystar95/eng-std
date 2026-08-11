import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:flutter_test/flutter_test.dart';

/// Locks the client's instant check to the server `AnswerGrader`'s normalisation + single-typo
/// rule, so the feedback is never stricter than the server (invariant). Anything the server would
/// accept, [SessionGrader.check] must also accept (correct or typo); it may be more lenient, but
/// never harsher.
void main() {
  group('SessionGrader.check — accepted after normalisation (full grade on the server)', () {
    test('exact match', () {
      expect(SessionGrader.check('withdraw', 'withdraw'), LocalCheck.correct);
    });
    test('case-insensitive', () {
      expect(SessionGrader.check('Withdraw', 'withdraw'), LocalCheck.correct);
    });
    test('collapses spacing and trims', () {
      expect(SessionGrader.check('  wire   transfer ', 'wire transfer'), LocalCheck.correct);
    });
    test('ignores trailing punctuation', () {
      expect(SessionGrader.check('make ends meet.', 'make ends meet'), LocalCheck.correct);
    });
    test('leading article optional in both directions', () {
      expect(SessionGrader.check('the bank', 'bank'), LocalCheck.correct);
      expect(SessionGrader.check('account', 'an account'), LocalCheck.correct);
    });
    test('expands common contractions', () {
      expect(
        SessionGrader.check("I'd like to withdraw", 'I would like to withdraw'),
        LocalCheck.correct,
      );
    });
  });

  group('SessionGrader.check — one-character typo (server caps at hard, still accepted)', () {
    test('single-letter typo on a long answer is a typo, not wrong', () {
      expect(SessionGrader.check('withdrow', 'withdraw'), LocalCheck.typo);
    });
    test('typo tolerance ignores the optional article', () {
      expect(SessionGrader.check('a withdrow', 'withdraw'), LocalCheck.typo);
    });
  });

  group('SessionGrader.check — rejected (server would also reject)', () {
    test('a real different word is wrong', () {
      expect(SessionGrader.check('deposit', 'withdraw'), LocalCheck.wrong);
    });
    test('two edits is wrong', () {
      expect(SessionGrader.check('witdrow', 'withdraw'), LocalCheck.wrong);
    });
    test('typo leniency does NOT apply to short answers', () {
      expect(SessionGrader.check('cet', 'cat'), LocalCheck.wrong); // len < 5
    });
    test('empty response (Не помню / blank) is wrong', () {
      expect(SessionGrader.check('', 'withdraw'), LocalCheck.wrong);
      expect(SessionGrader.check('   ', 'withdraw'), LocalCheck.wrong);
    });
  });

  group('phaseFor — mode → header phase', () {
    test('multiple_choice is intro', () {
      expect(phaseFor(ExerciseMode.multipleChoice), SessionPhase.intro);
    });
    test('word_bank is assemble', () {
      expect(phaseFor(ExerciseMode.wordBank), SessionPhase.assemble);
    });
    test('typing / listening / cloze are review', () {
      expect(phaseFor(ExerciseMode.typing), SessionPhase.review);
      expect(phaseFor(ExerciseMode.listening), SessionPhase.review);
      expect(phaseFor(ExerciseMode.cloze), SessionPhase.review);
    });
  });

  group('daysUntil — whole local calendar days (0 today, 1 tomorrow, overdue clamps to 0)', () {
    final now = DateTime(2026, 8, 6, 14, 30);
    test('same calendar day is 0', () {
      expect(daysUntil(DateTime(2026, 8, 6, 23, 59), now), 0);
    });
    test('next day is 1 even if earlier in the day', () {
      expect(daysUntil(DateTime(2026, 8, 7, 1, 0), now), 1);
    });
    test('four days out', () {
      expect(daysUntil(DateTime(2026, 8, 10, 9, 0), now), 4);
    });
    test('already overdue clamps to 0', () {
      expect(daysUntil(DateTime(2026, 8, 4, 9, 0), now), 0);
    });
  });

  // The станок's accepted variants are the client's half of "never stricter than the server". The
  // server folds them into its answer key; if the device didn't, it would print «Не то» on a card
  // the scheduler counts as correct — the one disagreement the invariant forbids.
  group('SessionGrader.check — accepted variants (offline grading agrees with the server)', () {
    test('a variant is accepted, not rejected', () {
      expect(
        SessionGrader.check('that is my seat', 'this is my seat',
            variants: const ['that is my seat']),
        LocalCheck.correct,
      );
    });
    test('the canonical answer still wins when variants are present', () {
      expect(
        SessionGrader.check('this is my seat', 'this is my seat',
            variants: const ['that is my seat']),
        LocalCheck.correct,
      );
    });
    test('a typo against a VARIANT is «Почти», not «Не то»', () {
      expect(
        SessionGrader.check('that is my seaf', 'this is my seat',
            variants: const ['that is my seat']),
        LocalCheck.typo,
      );
    });
    test('an exact variant beats a typo on the canonical answer', () {
      // "may I" is one edit from "can I", and also an exact variant. Exact must win, else a correct
      // answer would be reported as «Почти» and look like a mistake to the learner.
      expect(
        SessionGrader.check('may I', 'can I', variants: const ['may I']),
        LocalCheck.correct,
      );
    });
    test('something outside the set is still wrong', () {
      expect(
        SessionGrader.check('this is my chair', 'this is my seat',
            variants: const ['that is my seat']),
        LocalCheck.wrong,
      );
    });
    test('no variants behaves exactly as before', () {
      expect(SessionGrader.check('withdraw', 'withdraw'), LocalCheck.correct);
      expect(SessionGrader.check('deposit', 'withdraw'), LocalCheck.wrong);
    });
    test('blank is wrong even with variants on the card', () {
      expect(SessionGrader.check('   ', 'withdraw', variants: const ['take out']),
          LocalCheck.wrong);
    });
    test('an empty variant string cannot make everything correct', () {
      expect(SessionGrader.check('nonsense', 'withdraw', variants: const ['', '   ']),
          LocalCheck.wrong);
    });
  });

  group('SessionCard.fromJson — accepted_variants', () {
    test('reads the list from the wire', () {
      final card = SessionCard.fromJson({
        'term_id': 't1',
        'exercise_mode': 'typing',
        'answer': 'this is my seat',
        'accepted_variants': ['that is my seat'],
      });

      expect(card.acceptedVariants, ['that is my seat']);
    });
    test('an absent field is an empty list, never null', () {
      final card = SessionCard.fromJson({
        'term_id': 't1',
        'exercise_mode': 'typing',
        'answer': 'x',
      });

      expect(card.acceptedVariants, isEmpty);
    });
  });

  group('ExerciseMode.fromWire — forward-compatible', () {
    test('known wires map', () {
      expect(ExerciseMode.fromWire('word_bank'), ExerciseMode.wordBank);
      expect(ExerciseMode.fromWire('cloze'), ExerciseMode.cloze);
    });
    test('unknown / null fall back to typing (free recall, never a crash)', () {
      expect(ExerciseMode.fromWire('some_future_mode'), ExerciseMode.typing);
      expect(ExerciseMode.fromWire(null), ExerciseMode.typing);
    });
  });
}
