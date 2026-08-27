import 'dart:math';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// THE RUNG-0 CORNER OF FREE PRACTICE — the canon, stated by the owner and the architect
/// (BUGFIX-2 Ч.2б):
///
///   «Свободная практика ступени 0 = рецептивные режимы; продуктивные (письмо по памяти, диктант)
///    открываются лестницей.»
///
/// So a word with no rung of its own — outside the pool, or in it and still at rung 0 — is dealt
/// узнавание/выбор, сборка word_bank, произнеси, аудио «услышал→напиши» and description_match при
/// наличии описания, and is dealt neither `typing` nor `dictation`. A word standing on a rung of its
/// own gets the full fan, bounded only by its material and its language.
///
/// Three things the owner met on the phone are pinned here, and each was a separate silence:
///
///  * `description_match` could not enter a FAN at all, whatever description the term had, because
///    the fan built its playability without passing the description in (Ч.2б D1). The round-robin
///    always passed it, so the trainer worked everywhere except on the one path — «Тренировать это
///    слово» — where the fan IS the веер the button promises.
///  * `listening` was withheld from the corner, because the ADMISSION MATRIX opens it at the typed
///    rung, where it belongs in a PLANNED session. Free practice is not a planned session.
///  * `word_bank` was withheld from every SINGLE-WORD term (Ч.2б D2), so the assembly trainer was
///    missing from exactly the words the owner drills most.
///
/// The server's half is `backend2/tests/Feature/Learning/PracticeCornerTest.php`.
void main() {
  final t0 = DateTime.utc(2026, 8, 27);

  Term term(
    String id,
    String text, {
    required String translation,
    String? example,
    String? exampleTranslation,
    String? description,
  }) => Term(
    id: id,
    termText: text,
    type: 'word',
    transcription: null,
    translation: translation,
    example: example,
    exampleTranslation: exampleTranslation,
    description: description,
    imageUrl: null,
    imageAuthor: null,
    imageAuthorUrl: null,
    updatedAt: t0,
  );

  /// One word with the full set of material, plus neighbours so a choice card can be built.
  final drilled = term(
    '01M1CO0000000000000000000A',
    'invoice',
    translation: 'счёт',
    example: 'Could you send me the invoice by email?',
    exampleTranslation: 'Не пришлёшь мне счёт по почте?',
    description: 'A document asking for payment for goods or services.',
  );
  final deck = [
    drilled,
    term('01M1CO0000000000000000000B', 'ledger', translation: 'книга учёта'),
    term('01M1CO0000000000000000000C', 'receipt', translation: 'квитанция'),
    term('01M1CO0000000000000000000D', 'refund', translation: 'возврат'),
  ];

  const everyMode = PracticeModes([
    ExerciseMode.multipleChoice,
    ExerciseMode.wordBank,
    ExerciseMode.typing,
    ExerciseMode.listening,
    ExerciseMode.cloze,
    ExerciseMode.scramble,
    ExerciseMode.dictation,
    ExerciseMode.speaking,
    ExerciseMode.descriptionMatch,
    ExerciseMode.intro,
  ]);

  /// The FAN «Тренировать это слово» deals — every applicable trainer at once, so what is asserted
  /// is the SET and not one round-robin draw.
  Set<ExerciseMode> fanAt(LadderPosition position) => LocalPracticeSessionBuilder.build(
    terms: deck,
    limit: 20,
    random: Random(7),
    sessionId: 'S',
    onlyTermId: drilled.id,
    enabled: everyMode,
    ladder: {for (final t in deck) t.id: position},
    pairs: {for (final t in deck) t.id: (learned: 'en', support: 'ru')},
  ).cards.map((c) => c.mode).toSet();

  /// A pool word whose first meeting has not happened yet — rung 0, and no rung of its own.
  const rungZero = LadderPosition(acquisition: Acquisition.isNew, enrolled: true);

  /// A word of the collection nobody has taken into study at all.
  const catalogue = LadderPosition.untouched;

  /// A word that has earned every rung there is.
  const topOfLadder = LadderPosition(
    acquisition: Acquisition.graduated,
    successfulReviews: LearningLadder.dictationMinSuccesses,
    enrolled: true,
  );

  group('a word with no rung of its own', () {
    for (final (name, position) in [('at rung 0 in the pool', rungZero), ('in the catalogue', catalogue)]) {
      test('$name is dealt the receptive corner — audio and description_match included', () {
        final fan = fanAt(position);

        expect(fan, contains(ExerciseMode.multipleChoice));
        // Assembly on a SINGLE word: the letter chips (Ч.2б D2).
        expect(fan, contains(ExerciseMode.wordBank));
        expect(fan, contains(ExerciseMode.cloze));
        expect(fan, contains(ExerciseMode.speaking));
        // The two the canon adds back to this corner.
        expect(fan, contains(ExerciseMode.listening));
        expect(fan, contains(ExerciseMode.descriptionMatch));

        // …and the two it keeps out: writing a word out of memory, and a sentence by ear.
        expect(fan, isNot(contains(ExerciseMode.typing)));
        expect(fan, isNot(contains(ExerciseMode.dictation)));
        // Practice introduces nothing, rung 0 or not.
        expect(fan, isNot(contains(ExerciseMode.intro)));
      });
    }

    test('description_match needs the description, and says so by being absent', () {
      final bare = term('01M1CO0000000000000000000E', 'ledger', translation: 'книга учёта');
      final pool = [bare, ...deck];

      final fan = LocalPracticeSessionBuilder.build(
        terms: pool,
        limit: 20,
        random: Random(7),
        sessionId: 'S',
        onlyTermId: bare.id,
        enabled: everyMode,
        ladder: {for (final t in pool) t.id: rungZero},
        pairs: {for (final t in pool) t.id: (learned: 'en', support: 'ru')},
      ).cards.map((c) => c.mode).toSet();

      // The description IS the question here, so a term without one has no card rather than a
      // lesser one — while everything else in the corner is untouched.
      expect(fan, isNot(contains(ExerciseMode.descriptionMatch)));
      expect(fan, contains(ExerciseMode.multipleChoice));
      expect(fan, contains(ExerciseMode.listening));
    });
  });

  test('a word on a rung of its own gets the productive trainers too, and keeps the corner', () {
    final fan = fanAt(topOfLadder);

    expect(fan, contains(ExerciseMode.typing));
    expect(fan, contains(ExerciseMode.dictation));
    // …and it did not LOSE the corner on the way up: the full fan is the corner plus these two,
    // bounded by material and language, never a different set.
    expect(fan, contains(ExerciseMode.listening));
    expect(fan, contains(ExerciseMode.wordBank));
    expect(fan, contains(ExerciseMode.descriptionMatch));
  });

  test('a single word is assembled from its LETTERS, a phrase from its words', () {
    final cards = LocalPracticeSessionBuilder.build(
      terms: deck,
      limit: 20,
      random: Random(7),
      sessionId: 'S',
      onlyTermId: drilled.id,
      enabled: everyMode,
      ladder: {for (final t in deck) t.id: rungZero},
      pairs: {for (final t in deck) t.id: (learned: 'en', support: 'ru')},
    ).cards;

    final wordBank = cards.firstWhere((c) => c.mode == ExerciseMode.wordBank);

    expect(wordBank.chips, hasLength('invoice'.length));
    expect([...wordBank.chips!]..sort(), [...'invoice'.split('')]..sort());
    expect(wordBank.chips, isNot('invoice'.split('')), reason: 'never dealt in the answer own order');
  });
}
