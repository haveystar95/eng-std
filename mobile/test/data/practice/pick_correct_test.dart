import 'dart:convert';
import 'dart:math';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// `pick_correct` offline: the distractors ride in on `/sync` as a JSON column, the builder turns
/// them into three options, and each wrong one keeps its explanation. The gate is unlike every other
/// mode's — it depends on content the станок wrote, so deleting a bad distractor during proofreading
/// takes the mode away from that term again.
void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 12, 9);

  const right = 'Your workstation is ready for you.';
  const wrongTense = 'Your workstation are ready for you.';
  const wrongPrep = 'Your workstation is ready of you.';

  List<Map<String, String>> distractors({int count = 2}) => [
        {'sentence': wrongTense, 'error_span': 'are', 'correction': 'is'},
        {'sentence': wrongPrep, 'error_span': 'of', 'correction': 'for'},
      ].take(count).toList();

  Future<Term> seed({int distractorCount = 2, bool translated = true}) async {
    await db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Офис')),
      ],
      termUpserts: [
        TermsCompanion.insert(
          id: 't1',
          updatedAt: t0,
          termText: const Value('workstation'),
          type: const Value('word'),
          translation: const Value('рабочее место'),
          example: const Value(right),
          exampleTranslation:
              translated ? const Value('Ваше рабочее место готово.') : const Value.absent(),
          exampleDistractors: Value(jsonEncode(distractors(count: distractorCount))),
        ),
      ],
      itemUpserts: [
        CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0),
      ],
    );

    return (await db.watchCollectionTerms('c1').first).single.term;
  }

  SessionCard cardFor(Term term, {int seed = 1}) => LocalPracticeSessionBuilder.build(
        terms: [term],
        limit: 1,
        random: Random(seed),
        enabled: const PracticeModes([ExerciseMode.pickCorrect]),
        sessionId: 's1',
      ).cards.single;

  test('sync stores the distractors as JSON on the term', () async {
    final term = await seed();

    expect(jsonDecode(term.exampleDistractors!), hasLength(2));
  });

  test('builds three options with the translation as the prompt and the example as the answer',
      () async {
    final card = cardFor(await seed());

    expect(card.mode, ExerciseMode.pickCorrect);
    expect(card.answer, right);
    expect(card.prompt, 'Ваше рабочее место готово.');
    expect(card.options, hasLength(3));
    expect(card.options, contains(right));
    expect(card.options, contains(wrongTense));
  });

  test('carries the explanation for each wrong option and none for the right one', () async {
    final card = cardFor(await seed());

    expect(card.optionFeedback, hasLength(2));
    expect(card.feedbackFor(wrongTense)!.errorSpan, 'are');
    expect(card.feedbackFor(wrongTense)!.correction, 'is');
    expect(card.feedbackFor(wrongPrep)!.correction, 'for');
    expect(card.feedbackFor(right), isNull);
  });

  test('shuffles deterministically by seed — the same seed deals the same order', () async {
    final term = await seed();

    expect(cardFor(term, seed: 7).options, cardFor(term, seed: 7).options);
  });

  test('the gate refuses a term with one distractor — the proofreader deleted the other', () async {
    final card = cardFor(await seed(distractorCount: 1));

    // Not playable, so the practice fan falls back to something else rather than dealing a two-way
    // choice where a coin toss scores 50%.
    expect(card.mode, isNot(ExerciseMode.pickCorrect));
  });

  test('the gate refuses an untranslated example — the translation IS the question', () async {
    final card = cardFor(await seed(translated: false));

    expect(card.mode, isNot(ExerciseMode.pickCorrect));
  });

  test('malformed distractor JSON gates the mode out instead of throwing', () async {
    await db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Офис')),
      ],
      termUpserts: [
        TermsCompanion.insert(
          id: 't1',
          updatedAt: t0,
          termText: const Value('workstation'),
          type: const Value('word'),
          translation: const Value('рабочее место'),
          example: const Value(right),
          exampleTranslation: const Value('Ваше рабочее место готово.'),
          exampleDistractors: const Value('{not json'),
        ),
      ],
      itemUpserts: [
        CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0),
      ],
    );
    final term = (await db.watchCollectionTerms('c1').first).single.term;

    expect(cardFor(term).mode, isNot(ExerciseMode.pickCorrect));
  });

  test('the mode is not in the set a device assumes before its first sync', () {
    // The release rule, client side: a fresh install must not deal a trainer that ships off.
    expect(PracticeModes.serverDefault.modes, isNot(contains(ExerciseMode.pickCorrect)));
  });

  test('asksForExample is true, so the card grades against the sentence', () {
    expect(ExerciseMode.pickCorrect.asksForExample, isTrue);
    expect(ExerciseMode.pickCorrect.isSentenceChoice, isTrue);
    expect(ExerciseMode.pickCorrect.isTyped, isFalse);
  });

  test('fromWire round-trips the new mode', () {
    expect(ExerciseMode.fromWire('pick_correct'), ExerciseMode.pickCorrect);
  });
}
