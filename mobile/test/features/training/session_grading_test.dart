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

  // Found on the device: pick_correct put a green check on "Could you takes a photo…" beside the real
  // answer "Could you take a photo…" — one character apart. The typo stage is for forgiving TYPING;
  // a tapped answer has no typing in it, and here the one character IS the mistake being tested.
  group('SessionGrader.check — typo leniency only where something was typed', () {
    const right = 'Could you take a photo of us in front of the monument?';
    const wrong = 'Could you takes a photo of us in front of the monument?';

    test('a picked sentence one character off is WRONG, not «Почти»', () {
      expect(SessionGrader.check(wrong, right, forgiveTypos: false), LocalCheck.wrong);
    });

    test('the same difference is still forgiven when it was typed', () {
      expect(SessionGrader.check(wrong, right), LocalCheck.typo);
      expect(SessionGrader.check('withdrow', 'withdraw', forgiveTypos: true), LocalCheck.typo);
    });

    test('an exact pick is still correct with leniency off', () {
      expect(SessionGrader.check(right, right, forgiveTypos: false), LocalCheck.correct);
    });

    test('a variant is still accepted with leniency off', () {
      expect(
        SessionGrader.check('that is my seat', 'this is my seat',
            variants: const ['that is my seat'], forgiveTypos: false),
        LocalCheck.correct,
      );
    });

    test('the mode decides: only typed modes forgive', () {
      expect(ExerciseMode.pickCorrect.forgivesTypos, isFalse);
      expect(ExerciseMode.multipleChoice.forgivesTypos, isFalse);
      expect(ExerciseMode.wordBank.forgivesTypos, isFalse);
      expect(ExerciseMode.scramble.forgivesTypos, isFalse);
      expect(ExerciseMode.typing.forgivesTypos, isTrue);
      expect(ExerciseMode.cloze.forgivesTypos, isTrue);
      expect(ExerciseMode.listening.forgivesTypos, isTrue);
      expect(ExerciseMode.dictation.forgivesTypos, isTrue);
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

  group('misplacedWords — which of the learner own words to mark (QA-16)', () {
    test('marks the one word that does not belong', () {
      expect(
        SessionGrader.misplacedWords(['withdraw', 'money'], 'withdraw cash'),
        {1},
      );
    });

    test('marks an extra word rather than shifting the blame onto the right ones', () {
      // «withdraw the cash» — everything expected is there, in order, plus one word that is not.
      expect(
        SessionGrader.misplacedWords(['withdraw', 'some', 'cash'], 'withdraw cash'),
        {1},
      );
    });

    test('marks nothing when the words are all there in order', () {
      expect(SessionGrader.misplacedWords(['withdraw', 'cash'], 'withdraw cash'), isEmpty);
    });

    test('normalises like the grader — case and punctuation are not mistakes', () {
      expect(SessionGrader.misplacedWords(['Withdraw', 'cash!'], 'withdraw cash'), isEmpty);
    });

    test('marks a whole typed phrase that is simply the wrong answer', () {
      // The cloze shape: ONE entry holding everything the learner typed.
      expect(SessionGrader.misplacedWords(['withdraw money'], 'withdraw cash'), {0});
    });

    test('marks nothing when there is nothing to compare', () {
      // «Не помню» is already said by the verdict; marking an empty answer says it twice.
      expect(SessionGrader.misplacedWords([''], 'withdraw cash'), isEmpty);
      expect(SessionGrader.misplacedWords(['anything'], ''), isEmpty);
    });

    test('order counts — this is typing, not a recogniser transcript', () {
      // coverageOf() is order-free because a recogniser drops words; here the learner chose the
      // order, so a swapped sentence has words in the wrong place and must be told so.
      expect(SessionGrader.misplacedWords(['cash', 'withdraw'], 'withdraw cash'), isNotEmpty);
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

  group('SpokenAnswer.windowFor — the recording window follows the LENGTH of what is said (QA-21)', () {
    test('a one- or two-word term keeps the short window', () {
      for (final term in ['evoke', 'boarding pass']) {
        final w = SpokenAnswer.windowFor(asksForExample: false, term: term);
        expect(w.listenFor, SpokenAnswer.wordFormListenFor, reason: term);
        expect(w.pauseFor, SpokenAnswer.wordFormPauseFor, reason: term);
      }
    });

    test('a term of three words or more gets the sentence-sized window', () {
      // The live case: an 8s/2s window cut this off after the first word («Heard: When»).
      final w = SpokenAnswer.windowFor(
        asksForExample: false,
        term: 'Where do you see yourself in five years?',
      );
      expect(w.listenFor, SpokenAnswer.exampleFormListenFor);
      expect(w.pauseFor, SpokenAnswer.exampleFormPauseFor);
    });

    test('the threshold is exactly SpokenAnswer.longTermWords, counted in words', () {
      expect(SpokenAnswer.longTermWords, 3);
      expect(SpokenAnswer.isLongTerm('take a photo'), isTrue); // 3
      expect(SpokenAnswer.isLongTerm('team player'), isFalse); // 2
      expect(SpokenAnswer.isLongTerm('   '), isFalse); // no words at all
    });

    test('the example form always gets the long window, however short its term', () {
      final w = SpokenAnswer.windowFor(asksForExample: true, term: 'evoke');
      expect(w.listenFor, SpokenAnswer.exampleFormListenFor);
      expect(w.pauseFor, SpokenAnswer.exampleFormPauseFor);
    });
  });

  group('SpokenAnswer.gradesByCoverage — long spoken answers are judged by coverage (QA-22)', () {
    test('a phrase-shaped term on the word form is coverage-graded', () {
      expect(
        SpokenAnswer.gradesByCoverage(
            asksForExample: false, term: 'How do you deal with conflict?'),
        isTrue,
      );
    });

    test('a one- or two-word term stays binary', () {
      expect(SpokenAnswer.gradesByCoverage(asksForExample: false, term: 'evoke'), isFalse);
      expect(SpokenAnswer.gradesByCoverage(asksForExample: false, term: 'team player'), isFalse);
    });

    test('the example form is coverage-graded whatever its term — unchanged', () {
      expect(SpokenAnswer.gradesByCoverage(asksForExample: true, term: 'evoke'), isTrue);
    });

    test('it is the SAME «длинность» rule the recording window uses — one source, never two', () {
      for (final term in ['evoke', 'team player', 'take a photo', 'How do you deal with conflict?']) {
        final longWindow =
            SpokenAnswer.windowFor(asksForExample: false, term: term).listenFor ==
                SpokenAnswer.exampleFormListenFor;
        expect(
          SpokenAnswer.gradesByCoverage(asksForExample: false, term: term),
          longWindow,
          reason: 'a term recorded like a sentence must be graded like one: $term',
        );
      }
    });
  });

  group('coversAny — the coverage counterpart of the accepted set (QA-22)', () {
    test('a variant is accepted, not just the canonical form', () {
      expect(
        SessionGrader.coversAny('i am a team player', ['Are you a team player?', 'I am a team player']),
        isTrue,
      );
    });

    test('blank candidates are ignored and an empty set never passes vacuously', () {
      expect(SessionGrader.coversAny('anything', const []), isFalse);
      expect(SessionGrader.coversAny('anything', const ['', '   ']), isFalse);
    });
  });

  group('the live QA-22 case — «How do you deal with conflict?»', () {
    const target = 'How do you deal with conflict?';
    const heard = 'How do you deal this a conflict?';

    test('the reading counts: 5 of 6 target words covered, above the 70% threshold', () {
      // «with» is the only target word missing. The eaten article «a» the recogniser INVENTED is
      // dropped from both sides, so it is not counted as an extra word either.
      final coverage = SessionGrader.coverageOf(heard, target, ignoreArticles: true);
      expect(coverage, closeTo(5 / 6, 1e-9));
      expect(coverage, greaterThanOrEqualTo(SpokenAnswer.minCoverage));
      expect(SessionGrader.covers(heard, target, ignoreArticles: true), isTrue);
    });

    test('«this» is uncovered and gets marked, «a» does not', () {
      // Indices into the TARGET's own displayed words: How(0) do(1) you(2) deal(3) with(4)
      // conflict?(5). The learner's stray «this» has no pair, which is what leaves «with» unmarked
      // — the mark is on the target word that never registered.
      expect(
        SessionGrader.uncoveredWords(heard, target, ignoreArticles: true),
        {4},
      );
    });

    test('binary grading would have failed the same reading — which is the bug', () {
      expect(SessionGrader.check(heard, target, ignoreArticles: true), LocalCheck.wrong);
    });
  });

  group('ignoreArticles — speaking forgives an article the microphone ate (QA-21)', () {
    test('check: «team player» ≡ «a team player» when the flag is on', () {
      expect(
        SessionGrader.check('are you team player', 'Are you a team player?', ignoreArticles: true),
        LocalCheck.correct,
      );
    });

    test('check: the SAME comparison without the flag is unchanged — typing still fails it', () {
      // The default is off, so every non-speaking mode keeps grading the article. Not `correct`:
      // the article is a whole word, so this is not a one-character typo either.
      expect(
        SessionGrader.check('are you team player', 'Are you a team player?'),
        LocalCheck.wrong,
      );
    });

    test('check: articles are dropped mid-sentence, not just at the front', () {
      // _normalize already stripped a LEADING article before this change; the eaten one here is in
      // the middle, which is the case the live run hit.
      expect(
        SessionGrader.check('i saw dog in the park', 'I saw a dog in the park', ignoreArticles: true),
        LocalCheck.correct,
      );
      expect(
        SessionGrader.check('i saw dog in the park', 'I saw a dog in the park'),
        LocalCheck.wrong,
      );
    });

    test('check: dropping articles does not make two different answers equal', () {
      expect(
        SessionGrader.check('are you a team leader', 'Are you a team player?', ignoreArticles: true),
        LocalCheck.wrong,
      );
    });

    test('coverage: the eaten article stops costing twice (missing word AND longer target)', () {
      const target = 'Could you take a photo of us?';
      const heard = 'could you take photo of us';
      expect(SessionGrader.coverageOf(heard, target, ignoreArticles: true), 1.0);
      // Without the flag the same reading is short of a word it never had a chance to say.
      expect(SessionGrader.coverageOf(heard, target), lessThan(1.0));
    });

    test('coverage: the 70% threshold itself is untouched — only what gets counted changed', () {
      expect(SpokenAnswer.minCoverage, 0.7);
      expect(SessionGrader.covers('nothing like it', 'Could you take a photo of us?', ignoreArticles: true), isFalse);
    });

    test('uncoveredWords: a forgiven article is never marked as missing', () {
      // Index 3 is «a» — with articles ignored it is neither covered nor uncovered, so the
      // highlight cannot contradict the verdict that just forgave it.
      expect(
        SessionGrader.uncoveredWords('could you take photo of us', 'Could you take a photo of us?',
            ignoreArticles: true),
        isEmpty,
      );
    });
  });
}
