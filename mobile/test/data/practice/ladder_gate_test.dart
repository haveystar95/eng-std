import 'dart:math';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// WHAT THE LADDER STILL DECIDES ABOUT FREE PRACTICE — AND WHAT IT NO LONGER DOES.
///
/// It decides two things, both about the POOL and the card's SHAPE:
///
///   * a pair standing at RUNG 0 gets no practice card at all. Practice introduces nothing — no
///     exposure, no quota — so a word nobody has introduced has nothing for practice to drill.
///   * a pair still on the recognition rungs gets FAR options, so its first meetings stay winnable.
///
/// It no longer decides WHICH TRAINERS may be dealt (QA-26). Practice fans across every switched-on
/// trainer the term's data can furnish, which is what the server's own `selectForPractice` has
/// always done; gating the mode set here as well meant dictation (6 successful reviews) and typed
/// production (4) were unreachable in free practice on any real pool, and the dictation card never
/// appeared once. Free practice is a drill, not a rung: it schedules nothing, so nothing has to be
/// earned to enter it.
void main() {
  Term term(String id, String text, {String translation = 'перевод'}) => Term(
        id: id,
        termText: text,
        type: 'word',
        transcription: null,
        translation: translation,
        // Long enough to scramble and to dictate, and it CONTAINS the term, so cloze is playable
        // too: anything missing from a session is then the selection's doing, never the content's.
        example: 'Please find the $text before tonight.',
        exampleTranslation: 'Пожалуйста, найдите это до вечера.',
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

  test('rung 0 is refused by the POOL, which is the only place that refusal now lives', () {
    // The mode filter no longer says no to anything (QA-26), so this is the single gate keeping a
    // never-introduced word out of practice. PracticeModeSelector floors an empty applicable set to
    // multiple_choice, so a rung-0 word that reached the card builder WOULD come back as a card —
    // which is exactly why `build` must drop it from the pool and not rely on the matrix.
    expect(LearningLadder.admitsPractice(LearningLadder.stepIntro), isFalse);
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

    expect(session.cards.map((c) => c.termId).toSet(), {terms.first.id});
    for (final card in session.cards) {
      // Whatever trainer was dealt, a choice card is never a one-option card.
      if (card.options != null) expect(card.options, hasLength(greaterThan(1)));
    }
  });

  test('practice introduces nothing — an intro card is never dealt, even with intro switched on', () {
    for (final position in [
      const LadderPosition(acquisition: Acquisition.learning, learningStep: 1, enrolled: true),
      const LadderPosition(acquisition: Acquisition.graduated, enrolled: true),
    ]) {
      expect(modesAt(position), isNot(contains(ExerciseMode.intro)));
    }
  });

  test('EVERY switched-on trainer is dealt in free practice, at every rung above 0 (QA-26)', () {
    // The owner's rule, and the bug it replaced: with all trainers on, a long enough draw must show
    // all of them — dictation included, on a pair that has never been through the scheduler six
    // times. Deterministic: fixed ids (the rotation seed is card index + crc32 of the id), fixed
    // seeds, and terms whose example every content gate accepts.
    //
    // A DECK, not the four terms above, because the round-robin gives a term as many rotations as
    // the session has cards: four cards can never walk seven trainers however the deck is shuffled.
    final deck = [
      for (var i = 0; i < 24; i++)
        term('01KZETAB${i.toString().padLeft(2, '0')}0EMHCN6SP80T8DH', i.isEven ? 'towel$i' : 'front desk$i',
            // Distinct translations: a choice card's near-miss distractors are filtered by meaning,
            // and twenty-four terms all meaning the same thing would leave it with no wrong option.
            translation: 'перевод$i'),
    ];
    final graded = [for (final m in everyMode.modes) if (m.isGraded) m];

    for (final position in [
      // The lowest rung practice deals at all…
      onLadder,
      const LadderPosition(acquisition: Acquisition.learning,
          learningStep: LearningLadder.stepRecognitionReverse, enrolled: true),
      // …just graduated, nothing earned yet — the state a real pool is almost entirely in…
      const LadderPosition(acquisition: Acquisition.graduated, enrolled: true),
      // …and a pair that HAS earned the top rung.
      const LadderPosition(acquisition: Acquisition.graduated,
          successfulReviews: LearningLadder.dictationMinSuccesses, enrolled: true),
    ]) {
      final dealt = <ExerciseMode>{};
      for (var seed = 0; seed < 4; seed++) {
        dealt.addAll(LocalPracticeSessionBuilder.build(
          terms: deck,
          limit: deck.length,
          random: Random(seed),
          sessionId: 'S',
          enabled: everyMode,
          ladder: {for (final t in deck) t.id: position},
        ).cards.map((c) => c.mode));
      }

      expect(dealt, containsAll(graded), reason: 'missing ${graded.toSet().difference(dealt)}');
      // …and never the one trainer that asks nothing: practice introduces nothing.
      expect(dealt, isNot(contains(ExerciseMode.intro)));
    }
  });

  test('«Тренировать слово» fans one word across every trainer it can furnish, dictation included', () {
    // The other practice entry point (QA-14). Same rule, so the same trainers must be reachable —
    // including dictation on a pair that has only just graduated.
    final cards = LocalPracticeSessionBuilder.build(
      terms: terms,
      limit: 1,
      random: Random(3),
      sessionId: 'S',
      enabled: everyMode,
      ladder: {
        for (final t in terms)
          t.id: const LadderPosition(acquisition: Acquisition.graduated, enrolled: true),
      },
    ).cards;

    expect(cards.map((c) => c.termId).toSet(), hasLength(1), reason: 'the fan is over ONE word');
    expect(cards.map((c) => c.mode), contains(ExerciseMode.dictation));
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

    for (final card in session.cards.where((c) => c.mode == ExerciseMode.multipleChoice)) {
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
