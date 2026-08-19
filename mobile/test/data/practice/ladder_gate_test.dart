import 'dart:math';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// FREE PRACTICE IS UNDER THE LADDER TOO.
///
/// This is the gate that was deferred when the ladder landed server-side (B1): practice fans across
/// modes, but a word met a minute ago is not dealt dictation here either. The rule is the server's —
/// which rung opens which trainer — read from `/sync` and applied before the mode fan, so the
/// contract-pinned [PracticeModeSelector] itself is untouched by it.
void main() {
  Term term(String id, String text) => Term(
        id: id,
        termText: text,
        type: 'word',
        transcription: null,
        translation: 'перевод',
        example: 'A sentence that is long enough to scramble and dictate.',
        exampleTranslation: 'Достаточно длинное предложение.',
        imageUrl: null,
        imageAuthor: null,
        imageAuthorUrl: null,
        updatedAt: DateTime.utc(2026, 8, 14),
      );

  // Rich enough to support every trainer, so anything missing from a session is the LADDER's doing
  // and not the term's data.
  final terms = [
    term('01KZETAAA50EMHCN6SP80T8DHC', 'reservation'),
    term('01KZETAAB4AW6M9ZFRB3X02CVW', 'front desk'),
    term('01KZETAAC103WZ24WQ7H087ZJ3', 'towel'),
    term('01KZETAAD2EWE2H5ZV7WD8JWKT', 'check in'),
  ];

  const everyMode = PracticeModes([
    ExerciseMode.multipleChoice,
    ExerciseMode.wordBank,
    ExerciseMode.typing,
    ExerciseMode.listening,
    ExerciseMode.cloze,
    ExerciseMode.scramble,
    ExerciseMode.dictation,
    ExerciseMode.intro,
  ]);

  /// The lowest rung practice will actually deal: introduced, working through recognition. Its
  /// options are still `distant`, because the pair has not graduated.
  const onLadder = LadderPosition(acquisition: Acquisition.learning,
    learningStep: LearningLadder.stepRecognitionForward, enrolled: true);

  Set<ExerciseMode> modesAt(LadderPosition position, {int seed = 3}) =>
      LocalPracticeSessionBuilder.build(
        terms: terms,
        limit: 20,
        random: Random(seed),
        sessionId: 'S',
        enabled: everyMode,
        ladder: {for (final t in terms) t.id: position},
      ).cards.map((c) => c.mode).toSet();

  List<SessionCard> cardsAt(LadderPosition position, {int seed = 3}) =>
      LocalPracticeSessionBuilder.build(
        terms: terms,
        limit: 20,
        random: Random(seed),
        sessionId: 'S',
        enabled: everyMode,
        ladder: {for (final t in terms) t.id: position},
      ).cards;

  test('a never-shown word gets NO practice card at all — the gate is fail-closed', () {
    // The owner's rule: practice introduces nothing, so a word nobody has introduced has nothing
    // for practice to drill. Rung 0 used to be handed the rung-1 card as a substitute, which made
    // the one rung the matrix places a trainer at the one rung the gate ignored.
    for (final position in [
      LadderPosition.untouched,
      // reps survived a `known` undo, but the pair still stands at rung 0.
      const LadderPosition(acquisition: Acquisition.isNew, successfulReviews: 3, enrolled: true),
    ]) {
      expect(position.step, LearningLadder.stepIntro);
      expect(position.admitsPractice, isFalse);
      expect(cardsAt(position), isEmpty);
    }
  });

  test('the substitution is gone from the mode filter too, not just from the pool', () {
    // Belt and braces: PracticeModeSelector floors an EMPTY applicable set to multiple_choice, so a
    // rung-0 word reaching the card builder would still come back as a rung-1 card. Nothing may
    // deal it — which the empty session above already shows — and nothing may claim it is dealable.
    expect(ModeAdmission.shipped.only(
      [for (final m in everyMode.modes) if (m.isGraded) m],
      LearningLadder.stepIntro,
    ), isEmpty);
  });

  test('one introduced word is drilled while its rung-0 neighbours only lend their text', () {
    // A half-new collection must still be practisable, and a rung-0 word is allowed to be someone
    // else's WRONG option: appearing there claims nothing about it. Dropping it from the option
    // pool as well would leave a one-option multiple choice.
    final session = LocalPracticeSessionBuilder.build(
      terms: terms,
      limit: 20,
      random: Random(3),
      sessionId: 'S',
      enabled: everyMode,
      ladder: {
        terms.first.id: const LadderPosition(acquisition: Acquisition.learning,
          learningStep: LearningLadder.stepRecognitionReverse, enrolled: true),
        for (final t in terms.skip(1)) t.id: LadderPosition.untouched,
      },
    );

    expect(session.cards.map((c) => c.termId), [terms.first.id]);
    final card = session.cards.single;
    expect(card.mode, ExerciseMode.multipleChoice);
    expect(card.options, hasLength(greaterThan(1)));
  });

  test('practice introduces nothing — an intro card is never dealt, even with intro switched on', () {
    for (final position in [
      const LadderPosition(acquisition: Acquisition.learning, learningStep: 1, enrolled: true),
      const LadderPosition(acquisition: Acquisition.graduated, enrolled: true),
    ]) {
      expect(modesAt(position), isNot(contains(ExerciseMode.intro)));
    }
  });

  test('the assembly rungs open on graduation, and typed production does not', () {
    final dealt = modesAt(const LadderPosition(acquisition: Acquisition.graduated, enrolled: true));

    expect(dealt, isNot(contains(ExerciseMode.typing)));
    expect(dealt, isNot(contains(ExerciseMode.listening)));
    expect(dealt, isNot(contains(ExerciseMode.dictation)));
    expect(dealt.any((m) => const [
          ExerciseMode.wordBank,
          ExerciseMode.cloze,
          ExerciseMode.scramble,
          ExerciseMode.multipleChoice,
        ].contains(m)), isTrue);
  });

  test('typing opens at rung 4 and dictation only at rung 5', () {
    final atFour = modesAt(
      const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: LearningLadder.typingMinSuccesses, enrolled: true),
      seed: 11,
    );
    expect(atFour, isNot(contains(ExerciseMode.dictation)));

    // Sweep the seeds at rung 5 — the fan is a round-robin, so one session need not show every mode.
    final atFive = <ExerciseMode>{};
    for (var seed = 0; seed < 8; seed++) {
      atFive.addAll(modesAt(
        const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: LearningLadder.dictationMinSuccesses, enrolled: true),
        seed: seed,
      ));
    }
    expect(atFive, contains(ExerciseMode.dictation));
  });

  test('a `known` word is not held back — it is outside the ladder, not at the bottom of it', () {
    final dealt = modesAt(const LadderPosition(acquisition: Acquisition.graduated, isKnown: true, enrolled: true));

    // Reading «no rung» as rung 0 would gate a self-assessed word down to recognition, which proves
    // nothing about a claim.
    expect(dealt, isNot({ExerciseMode.multipleChoice}));
  });

  test('a pair still on the ladder gets FAR options — the session neighbours, not the near-misses', () {
    final session = LocalPracticeSessionBuilder.build(
      terms: terms,
      limit: 20,
      random: Random(5),
      sessionId: 'S',
      enabled: everyMode,
      ladder: {for (final t in terms) t.id: onLadder},
    );

    for (final card in session.cards) {
      expect(card.mode, ExerciseMode.multipleChoice);
      expect(card.options, isNotNull);
      // Every wrong option is another term OF THIS SESSION — unmistakably different, so the card is
      // answerable by knowing the word and by nothing else.
      final others = {for (final t in terms) if (t.id != card.termId) t.termText};
      for (final option in card.options!) {
        if (option == card.answer) continue;
        expect(others, contains(option));
      }
    }
  });

  test('practice never deals the identity-graded direction — the server refuses to grade it', () {
    // Rung 1 uploads the tapped option's TERM ID as the answer, and `SubmitReviewsHandler` refuses
    // identity grading for a practice answer. A card built that way here would be graded as text
    // against the term's forms and marked wrong for a correct tap.
    final session = LocalPracticeSessionBuilder.build(
      terms: terms,
      limit: 20,
      random: Random(9),
      sessionId: 'S',
      enabled: everyMode,
      ladder: {for (final t in terms) t.id: onLadder},
    );

    for (final card in session.cards) {
      expect(card.optionIds, isNull);
      expect(card.isIdentityGraded, isFalse);
      expect(card.ladderStep, isNot(LearningLadder.stepRecognitionForward));
      // …and the answer is the TERM, so the text check has something real to compare.
      expect(card.answer, isNot(card.termId));
    }
  });
}
