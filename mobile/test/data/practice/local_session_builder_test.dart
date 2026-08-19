import 'dart:math';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// Free practice is built on the device so it works in airplane mode start to summary. The mode
/// ladder itself is pinned to the server by a fixture (see the contract test); what this file pins
/// is everything around it — the pool rule, the card contents, and the two cases where the local
/// mirror is thinner than the server's database.
void main() {
  Term term(
    String id, {
    required String text,
    String? translation,
    String? example,
    String type = 'word',
    String? transcription,
  }) =>
      Term(
        id: id,
        termText: text,
        type: type,
        transcription: transcription,
        translation: translation,
        example: example,
        exampleTranslation: null,
        imageUrl: null,
        imageAuthor: null,
        imageAuthorUrl: null,
        updatedAt: DateTime.utc(2026, 8, 10),
      );

  // Fixed ids so the mode each card gets is stable: the rotation seed is index + crc32(id).
  final terms = [
    term('01KZETAAA50EMHCN6SP80T8DHC', text: 'reservation', translation: 'бронь',
        example: 'I have a reservation for tonight.'),
    term('01KZETAAB4AW6M9ZFRB3X02CVW', text: 'give up', translation: 'сдаваться', type: 'phrasal_verb',
        example: "I won't give up."),
    term('01KZETAAC103WZ24WQ7H087ZJ3', text: 'front desk', translation: 'стойка регистрации'),
    term('01KZETAAD2EWE2H5ZV7WD8JWKT', text: 'towel', translation: 'полотенце'),
    term('01KZETAAE63W6K93C55NCYXKVA', text: 'check in', translation: 'заселение'),
  ];

  /// Every pair sits at the TOP of the acquisition ladder unless a test says otherwise.
  ///
  /// The cases below are about the term's DATA and the pool rule — which modes its content can fill,
  /// which distractors a card may carry — and the ladder is a separate question with its own tests
  /// further down. Leaving it out would silently make every case a rung-1 case (a term with no
  /// progress row has never been shown), and every assertion here would then be about one card.
  Map<String, LadderPosition> topOfLadder(List<Term> from) => {
        for (final t in from)
          t.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
      };

  StudySession build({
    List<Term>? from,
    int limit = 20,
    int seed = 7,
    Map<String, LadderPosition>? ladder,
  }) {
    final pool = from ?? terms;
    return LocalPracticeSessionBuilder.build(
      terms: pool,
      limit: limit,
      random: Random(seed),
      sessionId: 'SESSION',
      ladder: ladder ?? topOfLadder(pool),
    );
  }

  test('the pool is the whole collection, capped at the limit', () {
    expect(build().cards, hasLength(terms.length));
    expect(build(limit: 3).cards, hasLength(3));
  });

  test('the session is marked as built locally and keeps the id it was given', () {
    final session = build();
    expect(session.builtLocally, isTrue, reason: 'the server has never seen this id — it adopts it');
    expect(session.sessionId, 'SESSION');
  });

  test('a term with no text is dropped rather than asked about', () {
    final session = build(from: [...terms, term('01KZETAAF37FWHW8WKDRGK71WN', text: '   ')]);

    expect(session.cards, hasLength(terms.length));
    expect(session.cards.map((c) => c.answer), everyElement(isNotEmpty));
  });

  test('an empty collection yields an empty session, not a crash', () {
    expect(build(from: const []).cards, isEmpty);
  });

  test('every card carries what its mode needs, and nothing it does not', () {
    for (final card in build().cards) {
      switch (card.mode) {
        case ExerciseMode.multipleChoice:
          expect(card.options, isNotNull);
          expect(card.options, contains(card.answer), reason: 'the answer must be among the options');
          expect(card.options!.toSet(), hasLength(card.options!.length), reason: 'no repeated option');
          expect(card.chips, isNull);
        case ExerciseMode.wordBank:
          expect(card.chips, isNotNull);
          expect(card.chips, isNotEmpty);
          expect(card.options, isNull);
        case ExerciseMode.scramble:
          // The card asks for the SENTENCE: the answer is the example, the prompt is its
          // translation, and the chips are that sentence's own words with no full stop.
          expect(card.chips, isNotNull);
          expect(card.answer, card.example);
          expect(card.prompt, card.exampleTranslation);
          expect(card.options, isNull);
          expect(card.chips!.length, greaterThanOrEqualTo(TermPlayability.minScrambleTokens));
          expect(card.chips, everyElement(isNot(endsWith('.'))));
        case ExerciseMode.dictation:
          // The task is the audio, so there is no written cue at all — and the answer is the
          // sentence that will be spoken, not the term.
          expect(card.prompt, isNull);
          expect(card.answer, card.example);
          expect(card.options, isNull);
          expect(card.chips, isNull);
        case ExerciseMode.pickCorrect:
          // Three whole sentences: the example plus two wrong ones, with the translation as the
          // question. Each wrong option must carry its own explanation, or the mode is pointless.
          expect(card.answer, card.example);
          expect(card.prompt, card.exampleTranslation);
          expect(card.options, hasLength(3));
          expect(card.options, contains(card.answer));
          expect(card.optionFeedback, hasLength(2));
          expect(card.feedbackFor(card.answer), isNull, reason: 'the right option explains nothing');
          expect(card.chips, isNull);
        case ExerciseMode.cloze:
          // The selector only picks cloze when the example can be blanked — so it must be here.
          expect(card.example, isNotNull);
          expect(card.example!.toLowerCase(), contains(card.answer.toLowerCase()));
        case ExerciseMode.typing:
        case ExerciseMode.listening:
          expect(card.options, isNull);
          expect(card.chips, isNull);
        case ExerciseMode.speaking:
          // Nothing is tapped or assembled — the answer is spoken. Which of the two forms the card
          // is, is decided by the rung it carries, and either way its answer must be the thing it
          // asked for: the sentence when it asked for the sentence, the term otherwise.
          expect(card.options, isNull);
          expect(card.chips, isNull);
          expect(card.answer, card.asksForExample ? card.example : isNot(card.example));
        case ExerciseMode.intro:
          fail('practice introduces nothing — an intro card must never be dealt here');
      }
    }
  });

  test('the card content comes from the mirror', () {
    // A one-term pool fans across the modes (see «Тренировать слово» below), so this reads the
    // first card rather than the only one — the content assertions are the same either way.
    //
    // The second term is a DECOY: at rung 0 it is not drillable, so the pool is still one word and
    // the fan still happens, but it can be a wrong option. Without it multiple_choice has nothing
    // to offer beside the answer and the card is refused (QA-15).
    final card = LocalPracticeSessionBuilder.build(
      terms: [terms.first, terms[3]],
      limit: 20,
      random: Random(7),
      sessionId: 'SESSION',
      ladder: {
        terms.first.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
        terms[3].id: const LadderPosition(enrolled: true),
      },
    ).cards.first;

    expect(card.termId, terms.first.id);
    expect(card.answer, 'reservation');
    expect(card.mode, ExerciseMode.multipleChoice, reason: 'the fan opens on the first enabled mode');
    expect(card.prompt, 'бронь', reason: 'the prompt is the translation, in the user\'s language');
    expect(card.type, 'word');
  });

  test('the mode matches the shared ladder for the card it lands on', () {
    // Not a duplicate of the contract test: that one pins the LADDER against the server, this one
    // pins that the builder actually feeds it the right card index and term.
    final session = build();
    for (var i = 0; i < session.cards.length; i++) {
      final card = session.cards[i];
      final source = terms.firstWhere((t) => t.id == card.termId);
      expect(
        card.mode,
        PracticeModeSelector.select(
          enabled: PracticeModes.serverDefault,
          rotation: PracticeModeSelector.rotationFor(card.termId, i),
          playable: TermPlayability.of(
            answer: source.termText!,
            example: source.example,
            exampleTranslation: source.exampleTranslation,
          ),
        ),
      );
    }
  });

  test('a repeat re-deals: same pool, different shuffle', () {
    final a = build(seed: 1).cards.map((c) => c.termId).toList();
    final b = build(seed: 2).cards.map((c) => c.termId).toList();

    expect(a.toSet(), b.toSet(), reason: 'the same pool');
    expect(a, isNot(b), reason: '«Ещё раз» must not replay the same order');
  });

  test('distractors never repeat the answer and never share its translation', () {
    // Two terms that mean the same thing: one must never be offered as the other's wrong answer.
    final ambiguous = [
      term('01KZETAAG08AQK14YFSBWW6KRM', text: 'lift', translation: 'лифт'),
      term('01KZETAAH0QP83Z3KFAQTHK1WQ', text: 'elevator', translation: 'лифт'),
      term('01KZETAAJ0QP83Z3KFAQTHK1WR', text: 'stairs', translation: 'лестница'),
    ];
    final session = LocalPracticeSessionBuilder.build(
      terms: ambiguous,
      limit: 20,
      random: Random(3),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.multipleChoice]),
      ladder: topOfLadder(ambiguous),
    );

    for (final card in session.cards) {
      final translation = ambiguous.firstWhere((t) => t.id == card.termId).translation;
      final sameMeaning = ambiguous
          .where((t) => t.id != card.termId && t.translation == translation)
          .map((t) => t.termText);
      for (final text in sameMeaning) {
        expect(card.options, isNot(contains(text)),
            reason: 'a synonym would read as correct for the same prompt');
      }
    }
  });

  test('a collection too small to fill four options yields fewer, not nonsense', () {
    final session = LocalPracticeSessionBuilder.build(
      terms: [terms.first, terms[3]],
      limit: 20,
      random: Random(5),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.multipleChoice]),
      ladder: topOfLadder([terms.first, terms[3]]),
    );

    for (final card in session.cards) {
      expect(card.options!.length, lessThanOrEqualTo(LocalPracticeSessionBuilder.optionCount));
      expect(card.options, contains(card.answer));
    }
  });

  test('word_bank chips: words for a phrase, letters for a single word, shuffled', () {
    final phrase = LocalPracticeSessionBuilder.build(
      terms: [terms[2]], // "front desk"
      limit: 1,
      random: Random(11),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.wordBank]),
      ladder: topOfLadder([terms[2]]),
    ).cards.single;
    expect(phrase.chips, containsAll(['front', 'desk']));

    // A SINGLE word is gated out of word_bank (minWordBankWords is 2 — one chip is not a puzzle), so
    // with only word_bank switched on the term falls to the floor. It used to come back as word_bank
    // with letter chips, but only because the floor returned `enabled.first` and skipped the gate;
    // the server has always answered multiple_choice here. ChipShuffler keeps its letter branch for a
    // future policy that lowers the floor — nothing selects it today.
    // The neighbour is here so the floor's multiple_choice can be BUILT — a deck of one leaves it
    // with a single option and the card is refused (QA-15).
    final pool = [terms[3], terms.first]; // "towel", + something to offer beside it
    final single = LocalPracticeSessionBuilder.build(
      terms: pool,
      limit: 2,
      random: Random(11),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.wordBank]),
      ladder: topOfLadder(pool),
    ).cards.firstWhere((c) => c.termId == terms[3].id);
    expect(single.mode, ExerciseMode.multipleChoice, reason: 'the floor, exactly as on the server');
    expect(single.chips, isNull);
  });

  test('a phrasal verb gets decoy particles that are never its own', () {
    final card = LocalPracticeSessionBuilder.build(
      terms: [terms[1]], // "give up"
      limit: 1,
      random: Random(13),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.wordBank]),
      ladder: topOfLadder([terms[1]]),
    ).cards.single;

    expect(card.chips!.length, greaterThan(2), reason: 'decoys make it a real choice');
    expect(card.chips!.where((c) => c == 'up'), hasLength(1), reason: 'never a second real particle');
  });

  group('the collection snapshot it builds from', () {
    late AppDatabase db;
    setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
    tearDown(() => db.close());

    test('reads the collection\'s terms in study order', () async {
      final at = DateTime.utc(2026, 8, 10);
      await db.applyDelta(
        termUpserts: [
          TermsCompanion.insert(id: 't1', updatedAt: at, termText: const Value('one')),
          TermsCompanion.insert(id: 't2', updatedAt: at, termText: const Value('two')),
          TermsCompanion.insert(id: 't3', updatedAt: at, termText: const Value('other set')),
        ],
        itemUpserts: [
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't2', updatedAt: at, position: const Value(1)),
          CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: at, position: const Value(0)),
          CollectionItemsCompanion.insert(collectionId: 'c2', termId: 't3', updatedAt: at),
        ],
      );

      final rows = await db.collectionTerms('c1');

      expect(rows.map((t) => t.id), ['t1', 't2'], reason: 'ordered by position, scoped to c1');
    });
  });

  group('«Тренировать слово» — a one-word pool fans across the trainers (QA-14)', () {
    // The device saw «1 of 1»: the round-robin walks the circle by CARD INDEX, so one word is one
    // point on it. Over many words the same rotation already shows every mode, which is why the
    // promise only ever failed for the single-word case — and why the fan is bounded by pool size.
    StudySession solo(Term t, {PracticeModes? enabled}) => LocalPracticeSessionBuilder.build(
          terms: [t],
          limit: 20,
          random: Random(4),
          sessionId: 'S',
          enabled: enabled ?? PracticeModes.serverDefault,
          ladder: topOfLadder([t]),
        );

    test('deals one card per applicable mode, in the matrix order', () {
      final rich = term('01KZETAAK18AQK14YFSBWW6KRN',
          text: 'reservation', translation: 'бронь', example: 'I have a reservation for tonight.');
      final cards = solo(rich).cards;

      expect(cards.length, greaterThan(1), reason: 'this is the «1 of 1» the acceptance saw');
      expect(cards.map((c) => c.termId).toSet(), {rich.id}, reason: 'one word, several trainers');

      final modes = cards.map((c) => c.mode).toList();
      expect(modes.toSet(), hasLength(modes.length), reason: 'a fan, not a repeat');
      expect(modes, isNot(contains(ExerciseMode.intro)), reason: 'practice introduces nothing');
      // The order is the enabled set's — the server sorts it by `learning_mode_settings.position`
      // and the sync feed hands it over unchanged — filtered by what the term supports.
      expect(modes, [
        for (final m in PracticeModes.serverDefault.modes)
          if (modes.contains(m)) m,
      ]);
    });

    test('every fanned mode is one the term data can actually build', () {
      final bare = term('01KZETAAM18AQK14YFSBWW6KRP', text: 'towel', translation: 'полотенце');
      final modes = solo(bare).cards.map((c) => c.mode).toSet();

      // No example and a single word: word_bank, cloze and scramble are all gated out by content.
      expect(modes, isNot(contains(ExerciseMode.wordBank)));
      expect(modes, isNot(contains(ExerciseMode.cloze)));
      expect(modes, isNot(contains(ExerciseMode.scramble)));
    });

    test('a pair low on the ladder gets its one card — once there is a second option to offer', () {
      // Rung 1 admits multiple_choice and nothing else, so the fan is a fan of one. In a deck of ONE
      // that card has nothing beside the answer and is refused (QA-15); with a neighbour it is dealt,
      // and «few modes apply» still does not become «no session».
      final t = term('01KZETAAN18AQK14YFSBWW6KRQ', text: 'towel', translation: 'полотенце');
      final other = term('01KZETAAP2C0MG5J6ZV1S4XQD7', text: 'sheets', translation: 'простыни');
      final ladder = {
        t.id: const LadderPosition(acquisition: Acquisition.learning, learningStep: 1, enrolled: true),
        other.id: const LadderPosition(acquisition: Acquisition.learning, learningStep: 1, enrolled: true),
      };

      expect(
        LocalPracticeSessionBuilder.build(
          terms: [t], limit: 20, random: Random(4), sessionId: 'S',
          ladder: {t.id: ladder[t.id]!},
        ).cards,
        isEmpty,
        reason: 'nothing to offer beside the answer — the card is not a question',
      );

      final session = LocalPracticeSessionBuilder.build(
        terms: [t, other], limit: 20, random: Random(4), sessionId: 'S', ladder: ladder,
      );
      final own = session.cards.where((c) => c.termId == t.id).toList();
      expect(own, hasLength(1));
      expect(own.single.mode, ExerciseMode.multipleChoice);
    });

    test('a MANY-word session is untouched — one card per term', () {
      expect(build().cards, hasLength(terms.length));
      expect(build().cards.map((c) => c.termId).toSet(), hasLength(terms.length));
    });
  });

  test('far options are all of the card own shape (QA-6)', () {
    // The mixed deck from the acceptance: a word among sentences. An option of another shape is
    // discarded on sight, so it is not an option — the card gets fewer, never mixed.
    final mixed = [
      term('01KZETAAP18AQK14YFSBWW6KRR', text: 'grain-free', translation: 'без злаков'),
      term('01KZETAAQ18AQK14YFSBWW6KRS', text: 'organic', translation: 'органический'),
      term('01KZETAAR18AQK14YFSBWW6KRT',
          text: 'Where can I find dog food?', translation: 'Где я могу найти корм для собак?', type: 'phrase'),
      term('01KZETAAS18AQK14YFSBWW6KRU',
          text: 'Is this suitable for small breeds?', translation: 'Подходит ли это для мелких пород?', type: 'phrase'),
    ];
    // Rung 2 is where practice deals a recognition card, and the matrix gives it `distant` options.
    final session = LocalPracticeSessionBuilder.build(
      terms: mixed,
      limit: 20,
      random: Random(6),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.multipleChoice]),
      ladder: {
        for (final t in mixed)
          t.id: const LadderPosition(acquisition: Acquisition.learning, learningStep: 2, enrolled: true),
      },
    );

    expect(session.cards, isNotEmpty);
    for (final card in session.cards) {
      final own = mixed.firstWhere((t) => t.id == card.termId);
      for (final option in card.options!) {
        final source = mixed.firstWhere((t) => t.termText == option, orElse: () => own);
        expect(source.type, own.type, reason: 'a ${own.type} card was offered «$option»');
      }
      expect(card.options!.length, greaterThanOrEqualTo(2), reason: 'fewer options, still a choice');
    }
  });
}
