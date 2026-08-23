import 'dart:convert';
import 'dart:math';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:flutter_test/flutter_test.dart';

/// Offline typed grading has to agree with the server, and the only way it can is by holding the
/// same accepted set. This pins the whole local path: `/sync` stores the variants, the practice
/// builder puts them on the card, and the grader accepts them — in airplane mode, with no session
/// endpoint involved.
void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 12, 9);

  /// The one term these cases build from, at the TOP of the acquisition ladder. This file is about
  /// the VARIANTS reaching an offline card; a never-shown pair would be held at rung 1, where the
  /// only trainer admitted is multiple_choice and there is no typed answer to accept a variant for.
  const topOfLadder = {
    't1': LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
  };

  Future<void> seedTerm({List<String>? variants}) => db.applyDelta(
    collectionUpserts: [
      CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Полёт')),
    ],
    termUpserts: [
      TermsCompanion.insert(
        id: 't1',
        updatedAt: t0,
        termText: const Value('this is my seat'),
        type: const Value('phrase'),
        translation: const Value('это моё место'),
        example: const Value('Excuse me, this is my seat.'),
        exampleTranslation: const Value('Простите, это моё место.'),
        acceptedVariants: Value(variants == null ? null : jsonEncode(variants)),
      ),
    ],
    itemUpserts: [CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0)],
  );

  Future<Term> readTerm() async => (await db.watchCollectionTerms('c1').first).single.term;

  test('sync stores the variants as JSON on the term', () async {
    await seedTerm(variants: const ['that is my seat']);

    expect(jsonDecode((await readTerm()).acceptedVariants!), ['that is my seat']);
  });

  test('a term with no variants stores null, not the string "null"', () async {
    await seedTerm();

    expect((await readTerm()).acceptedVariants, isNull);
  });

  test('a locally built typed card carries the variants, and the grader accepts them', () async {
    await seedTerm(variants: const ['that is my seat']);
    final term = await readTerm();

    // Only the typed/assembled term modes — the sentence modes are covered separately below.
    final session = LocalPracticeSessionBuilder.build(
      terms: [term],
      limit: 1,
      random: Random(1),
      enabled: const PracticeModes([ExerciseMode.typing]),
      sessionId: 's1',
      ladder: topOfLadder,
    );
    final card = session.cards.single;

    expect(card.acceptedVariants, ['that is my seat']);
    expect(
      SessionGrader.check('that is my seat', card.answer, variants: card.acceptedVariants),
      LocalCheck.correct,
    );
  });

  test('a sentence-graded card carries NO variants — the answer is the sentence', () async {
    await seedTerm(variants: const ['that is my seat']);
    final term = await readTerm();

    final session = LocalPracticeSessionBuilder.build(
      terms: [term],
      limit: 1,
      random: Random(1),
      enabled: const PracticeModes([ExerciseMode.dictation]),
      sessionId: 's1',
      ladder: topOfLadder,
    );
    final card = session.cards.single;

    expect(card.mode, ExerciseMode.dictation);
    expect(card.answer, 'Excuse me, this is my seat.');
    // A variant of the term is not a variant of the sentence; the server's expected set here is the
    // sentence alone, so accepting the term's variants would make the client LOOSER than the server.
    expect(card.acceptedVariants, isEmpty);
  });

  test('malformed variant JSON degrades to no variants instead of throwing', () async {
    await db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Полёт')),
      ],
      termUpserts: [
        TermsCompanion.insert(
          id: 't1',
          updatedAt: t0,
          termText: const Value('this is my seat'),
          type: const Value('phrase'),
          translation: const Value('это моё место'),
          acceptedVariants: const Value('{not json'),
        ),
      ],
      itemUpserts: [
        CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0),
      ],
    );

    final session = LocalPracticeSessionBuilder.build(
      terms: [await readTerm()],
      limit: 1,
      random: Random(1),
      enabled: const PracticeModes([ExerciseMode.typing]),
      sessionId: 's1',
      ladder: topOfLadder,
    );

    // Stricter than the server, never looser — a bad row can't let a wrong answer through.
    expect(session.cards.single.acceptedVariants, isEmpty);
  });
}
