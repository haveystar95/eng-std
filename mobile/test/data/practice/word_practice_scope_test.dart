import 'dart:math';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:eng_std/data/providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// «ТРЕНИРОВАТЬ ЭТО СЛОВО» — A WORD IS A SCOPE, A COLLECTION IS NOT REQUIRED.
///
/// The live break (BUG-3, 27.08.2026): the button on a word card in «Мои слова» opened the session
/// screen and immediately showed «Не удалось загрузить сессию», with a «Повторить» that could never
/// work — the practice builder demanded a `collection_id` and that entry point has none. It has
/// none BY DESIGN: the pool outlives the folders its words came from (п. 102), so a pool word may
/// belong to several collections or to none at all.
///
/// Two halves are pinned here, and they are separate on purpose:
///
///   * the SCOPE — a term alone is enough to build a practice session, orphan word included;
///   * the MATERIAL — the term list stays wide even when one word is questioned, because a choice
///     card's wrong options come from it and a lone word cannot furnish its own (QA-15).
void main() {
  Term term(String id, String text, {String translation = 'перевод'}) => Term(
    id: id,
    termText: text,
    type: 'word',
    transcription: null,
    translation: translation,
    example: 'Please find the $text before tonight.',
    exampleTranslation: 'Пожалуйста, найдите это до вечера.',
    imageUrl: null,
    imageAuthor: null,
    imageAuthorUrl: null,
    updatedAt: DateTime.utc(2026, 8, 27),
  );

  final terms = [
    term('01KZETCAA50EMHCN6SP80T8DHC', 'reservation', translation: 'бронь'),
    term('01KZETCAB4AW6M9ZFRB3X02CVW', 'front desk', translation: 'стойка регистрации'),
    term('01KZETCAC103WZ24WQ7H087ZJ3', 'towel', translation: 'полотенце'),
    term('01KZETCAD2EWE2H5ZV7WD8JWKT', 'check in', translation: 'заселение'),
  ];
  final drilled = terms.first;

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

  /// In the pool and past its first meeting — the ordinary word «Мои слова» offers to drill.
  const studied = LadderPosition(acquisition: Acquisition.graduated, enrolled: true);

  group('the builder: one word questioned, the whole list still lending options', () {
    test('only the named term is asked about', () {
      final session = LocalPracticeSessionBuilder.build(
        terms: terms,
        limit: 20,
        random: Random(4),
        sessionId: 'S',
        onlyTermId: drilled.id,
        enabled: everyMode,
        ladder: {for (final t in terms) t.id: studied},
      );

      expect(session.cards, isNotEmpty);
      expect(session.cards.map((c) => c.termId).toSet(), {drilled.id});
    });

    test('its choice cards are real questions — the neighbours are still there', () {
      // The half that a narrowed list broke silently: with only the drilled term in `terms`, every
      // multiple_choice card came out with the answer alone on screen and was refused by the option
      // floor, so the fan quietly lost a trainer instead of failing loudly.
      final session = LocalPracticeSessionBuilder.build(
        terms: terms,
        limit: 20,
        random: Random(4),
        sessionId: 'S',
        onlyTermId: drilled.id,
        enabled: everyMode,
        ladder: {for (final t in terms) t.id: studied},
      );

      expect(session.cards.map((c) => c.mode), contains(ExerciseMode.multipleChoice));
      for (final card in session.cards) {
        if (card.options == null) continue;
        expect(card.options!.length, greaterThanOrEqualTo(LocalPracticeSessionBuilder.minOptions));
        // Every wrong option is another term of the list — never the drilled word twice.
        final others = {
          for (final t in terms)
            if (t.id != drilled.id) t.termText,
        };
        for (final option in card.options!) {
          if (option == card.answer) continue;
          expect(others, contains(option));
        }
      }
    });

    test('a term that is not in the list yields no cards, rather than someone else’s', () {
      final session = LocalPracticeSessionBuilder.build(
        terms: terms,
        limit: 20,
        random: Random(4),
        sessionId: 'S',
        onlyTermId: '01KZETCZZZ0EMHCN6SP80T8DHC',
        enabled: everyMode,
        ladder: {for (final t in terms) t.id: studied},
      );

      expect(session.cards, isEmpty);
    });
  });

  group('the provider: the scope is a collection OR a word', () {
    late AppDatabase db;
    late ProviderContainer container;

    setUp(() {
      db = AppDatabase.forTesting(NativeDatabase.memory());
      container = ProviderContainer(overrides: [appDatabaseProvider.overrideWith((ref) => db)]);
    });

    tearDown(() async {
      container.dispose();
      await db.close();
    });

    final t0 = DateTime.utc(2026, 8, 27, 9);

    /// The mirror as «Мои слова» sees it: terms synced, the drilled one in the pool — and NO
    /// collection membership at all, which is the orphan case (its folder was deleted, or it was
    /// enrolled from search).
    Future<void> seedOrphan() => db.applyDelta(
      termUpserts: [
        for (final t in terms)
          TermsCompanion.insert(
            id: t.id,
            updatedAt: t0,
            termText: Value(t.termText),
            translation: Value(t.translation),
            example: Value(t.example),
            exampleTranslation: Value(t.exampleTranslation),
            type: const Value('word'),
          ),
      ],
      progressUpserts: [
        TermProgressCompanion.insert(
          termId: drilled.id,
          updatedAt: t0,
          state: const Value('review'),
          acquisition: const Value('graduated'),
          enrolledAt: Value(t0),
        ),
      ],
    );

    SessionArgs argsFor({String? collectionId, String? onlyTermId}) => (
      sessionId: 'S',
      collectionId: collectionId,
      practice: true,
      limit: 20,
      onlyTermId: onlyTermId,
    );

    test('a word with no collection is drilled — this is the session that used to crash', () async {
      await seedOrphan();

      final session = await container.read(
        studySessionProvider(argsFor(onlyTermId: drilled.id)).future,
      );

      expect(session.cards, isNotEmpty);
      expect(session.cards.map((c) => c.termId).toSet(), {drilled.id});
      expect(session.builtLocally, isTrue, reason: 'practice is built on the device, always');
    });

    test('rebuilding it succeeds too — «Повторить» is not a button into the same wall', () async {
      await seedOrphan();
      final args = argsFor(onlyTermId: drilled.id);

      await container.read(studySessionProvider(args).future);
      container.invalidate(studySessionProvider(args)); // what «Повторить» does
      final again = await container.read(studySessionProvider(args).future);

      expect(again.cards, isNotEmpty);
    });

    test('the mirror lends the wrong options a missing collection cannot', () async {
      await seedOrphan();

      final session = await container.read(
        studySessionProvider(argsFor(onlyTermId: drilled.id)).future,
      );

      final choice = session.cards.where((c) => c.options != null);
      expect(choice, isNotEmpty, reason: 'a choice card is buildable without a collection');
      for (final card in choice) {
        expect(card.options!.length, greaterThanOrEqualTo(LocalPracticeSessionBuilder.minOptions));
      }
    });

    test('a scope is still required — neither a collection nor a word is an error', () async {
      await seedOrphan();

      await expectLater(
        container.read(studySessionProvider(argsFor()).future),
        throwsA(isA<StateError>()),
      );
    });

    test('a collection scope is unchanged — the whole topic is still dealt', () async {
      await seedOrphan();
      await db.applyDelta(
        collectionUpserts: [
          CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Отель')),
        ],
        itemUpserts: [
          for (var i = 0; i < terms.length; i++)
            CollectionItemsCompanion.insert(
              collectionId: 'c1',
              termId: terms[i].id,
              updatedAt: t0,
              position: Value(i),
            ),
        ],
      );

      final session = await container.read(
        studySessionProvider(argsFor(collectionId: 'c1')).future,
      );

      expect(session.cards.map((c) => c.termId).toSet().length, greaterThan(1));
    });
  });
}
