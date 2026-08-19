import 'dart:math';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// QA-15: a choice card with ONE option is not a card.
///
/// On the device, «Как вы справляетесь с конфликтами?» was dealt with a single option — the answer —
/// and tapping the only thing on screen queued a correct answer for a retrieval that never happened.
///
/// The minimum was checked in the branch that could think of it (the far options shorten rather than
/// mix shapes) and not where that branch LANDS: the ordinary multiple_choice below takes however
/// many distractors the pool can give it, and a pool of one gives none.
///
/// Client port of the server's OptionFloorTest, and it has to exist twice for the usual reason: a
/// card the server would refuse must not appear just because the phone built the session offline.
void main() {
  Term term(String id, {required String text, String? translation, String? example}) => Term(
        id: id,
        termText: text,
        type: 'phrase',
        transcription: null,
        translation: translation,
        example: example,
        exampleTranslation: null,
        imageUrl: null,
        imageAuthor: null,
        imageAuthorUrl: null,
        updatedAt: DateTime.utc(2026, 8, 10),
      );

  final lonely = term(
    '01KZETAAA50EMHCN6SP80T8DHC',
    text: 'How do you deal with conflict?',
    translation: 'Как вы справляетесь с конфликтами?',
  );

  StudySession build(List<Term> from, {required Map<String, LadderPosition> ladder}) =>
      LocalPracticeSessionBuilder.build(
        terms: from,
        limit: 20,
        random: Random(7),
        sessionId: 'SESSION',
        ladder: ladder,
        // Only multiple_choice on, so the pool is the only thing that can furnish an option — the
        // exact corner the fallbacks land in.
        enabled: const PracticeModes([ExerciseMode.multipleChoice]),
      );

  test('a lone term is refused rather than dealt with the answer alone on screen', () {
    final session = build([lonely], ladder: {
      lonely.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
    });

    // Nothing to offer beside the answer, so nothing is dealt. An empty session is the screen's
    // empty state; a one-option card is a tap that proves nothing and is logged as if it did.
    expect(session.cards, isEmpty);
  });

  test('never a choice card below the floor, whatever the pool can manage', () {
    final pool = [
      lonely,
      term('01KZETAAB4AW6M9ZFRB3X02CVW',
          text: 'Can we talk about it later?', translation: 'Можем обсудить это позже?'),
    ];
    final session = build(pool, ladder: {
      for (final t in pool)
        t.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
    });

    expect(session.cards, isNotEmpty, reason: 'a second term is all it takes to build the card');
    for (final card in session.cards) {
      final options = card.options;
      if (options == null) continue;
      expect(options.length, greaterThanOrEqualTo(LocalPracticeSessionBuilder.minOptions),
          reason: '${card.prompt} was dealt as ${card.mode.wire} with ${options.length} option(s)');
    }
  });

  test('the fan drops the modes it cannot furnish, not the whole word', () {
    // «Тренировать слово» on a single word FANS across its applicable modes. word_bank asks for the
    // term itself and needs no options at all, so it survives where multiple_choice is refused —
    // the floor removes a card, never the word.
    final session = LocalPracticeSessionBuilder.build(
      terms: [lonely],
      limit: 20,
      random: Random(7),
      sessionId: 'SESSION',
      ladder: {
        lonely.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
      },
      enabled: const PracticeModes([ExerciseMode.multipleChoice, ExerciseMode.wordBank]),
    );

    expect(session.cards.map((c) => c.mode), [ExerciseMode.wordBank]);
  });
}
