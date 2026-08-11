import 'dart:math';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
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

  StudySession build({List<Term>? from, int limit = 20, int seed = 7}) =>
      LocalPracticeSessionBuilder.build(
        terms: from ?? terms,
        limit: limit,
        random: Random(seed),
        sessionId: 'SESSION',
      );

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
        case ExerciseMode.cloze:
          // The selector only picks cloze when the example can be blanked — so it must be here.
          expect(card.example, isNotNull);
          expect(card.example!.toLowerCase(), contains(card.answer.toLowerCase()));
        case ExerciseMode.typing:
        case ExerciseMode.listening:
          expect(card.options, isNull);
          expect(card.chips, isNull);
      }
    }
  });

  test('the card content comes from the mirror', () {
    final session = build(from: [terms.first]);
    final card = session.cards.single;

    expect(card.termId, terms.first.id);
    expect(card.answer, 'reservation');
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
    ).cards.single;
    expect(phrase.chips, containsAll(['front', 'desk']));

    final single = LocalPracticeSessionBuilder.build(
      terms: [terms[3]], // "towel"
      limit: 1,
      random: Random(11),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.wordBank]),
    ).cards.single;
    expect(single.chips, hasLength('towel'.length));
    expect(single.chips!.join(), isNot('towel'), reason: 'a shuffle that returns the answer is no puzzle');
  });

  test('a phrasal verb gets decoy particles that are never its own', () {
    final card = LocalPracticeSessionBuilder.build(
      terms: [terms[1]], // "give up"
      limit: 1,
      random: Random(13),
      sessionId: 'S',
      enabled: const PracticeModes([ExerciseMode.wordBank]),
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
}
