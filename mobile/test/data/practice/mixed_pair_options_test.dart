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

/// THE PAIR GATE IN FREE PRACTICE — «Cześć» must never stand beside `hello` (BUGFIX-2 Ч.1).
///
/// The live break, from the owner's phone: «Тренировать это слово» on an English word offered the
/// Polish `Cześć` among its options. The path explains it — that button names a WORD and no folder
/// (the pool outlives the folders its words came from), so the card's material is the whole local
/// mirror, and the mirror holds every language the learner is studying. Every option was a correct
/// word in its own language and only the one the answer key named counted; knowing the word did not
/// help, because knowing the word was not what the card asked.
///
/// It is a recurrence of MIX-1a, which the SERVER fixed at the same place: options are term TEXTS in
/// the studied language, so `EloquentDistractorReader` compares `terms.lang` and drops everything
/// else, and `recognitionCard()` compares BOTH halves of the pair because a far option may be shown
/// as the translation and a pair is directed. This file is the device's half of the same rule.
///
/// `terms` has no `lang` column and is not getting one: a word's language is a fact about the folder
/// it is being studied through, and `AppDatabase.pairByTerms` is where that is answered — the same
/// resolver the pair badge and the TTS voice already read.
void main() {
  final t0 = DateTime.utc(2026, 8, 27, 9);

  Term term(String id, String text, String translation) => Term(
    id: id,
    termText: text,
    type: 'word',
    transcription: null,
    translation: translation,
    example: null,
    exampleTranslation: null,
    imageUrl: null,
    imageAuthor: null,
    imageAuthorUrl: null,
    updatedAt: t0,
  );

  // Three pairs, all with Russian on the support side — the shape of a learner with several
  // collections open, which is the owner's actual mirror.
  final en = [
    term('01M1EN0000000000000000000A', 'hello', 'привет'),
    term('01M1EN0000000000000000000B', 'goodbye', 'пока'),
    term('01M1EN0000000000000000000C', 'thanks', 'спасибо'),
    term('01M1EN0000000000000000000D', 'sorry', 'извините'),
  ];
  final pl = [
    term('01M1PL0000000000000000000A', 'Cześć', 'привет'),
    term('01M1PL0000000000000000000B', 'dziękuję', 'спасибо'),
  ];
  final it = [
    term('01M1IT0000000000000000000A', 'ciao', 'привет'),
    term('01M1IT0000000000000000000B', 'grazie', 'спасибо'),
  ];
  final everything = [...en, ...pl, ...it];

  Map<String, ({String learned, String support})> pairsOf() => {
    for (final t in en) t.id: (learned: 'en', support: 'ru'),
    for (final t in pl) t.id: (learned: 'pl', support: 'ru'),
    for (final t in it) t.id: (learned: 'it', support: 'ru'),
  };

  /// A pair at the top of the ladder, so what the card is dealt is the gate's doing and not the
  /// rung's.
  const studied = LadderPosition(
    acquisition: Acquisition.graduated,
    successfulReviews: LearningLadder.dictationMinSuccesses,
    enrolled: true,
  );

  StudySession drill(
    Term target, {
    required List<Term> deck,
    required Map<String, ({String learned, String support})> pairs,
    LadderPosition position = studied,
    int seed = 5,
  }) => LocalPracticeSessionBuilder.build(
    terms: deck,
    limit: 20,
    random: Random(seed),
    sessionId: 'S',
    onlyTermId: target.id,
    enabled: const PracticeModes([ExerciseMode.multipleChoice, ExerciseMode.descriptionMatch]),
    ladder: {for (final t in deck) t.id: position},
    pairs: pairs,
  );

  /// Every option string this session ever put on screen.
  List<String> optionsOf(StudySession session) => [
    for (final card in session.cards) ...?card.options,
  ];

  group('a mixed mirror', () {
    test('an English word is never offered a Polish or an Italian option', () {
      // Several seeds: the candidate list is shuffled per card, so one draw proves little and
      // «Cześć never appeared» must not pass by luck.
      for (var seed = 0; seed < 12; seed++) {
        final session = drill(en.first, deck: everything, pairs: pairsOf(), seed: seed);
        final options = optionsOf(session);

        expect(options, isNotEmpty, reason: 'seed $seed dealt no options at all');
        for (final foreign in [...pl, ...it]) {
          expect(
            options,
            isNot(contains(foreign.termText)),
            reason: 'seed $seed: «${foreign.termText}» is a correct word — in another card',
          );
        }
        // …and it is not empty-by-filtering: the English neighbours are still doing their job.
        expect(options.length, greaterThan(1));
      }
    });

    test('the same mirror still drills the Polish word — with Polish options', () {
      final session = drill(pl.first, deck: everything, pairs: pairsOf());
      final options = optionsOf(session);

      expect(options, contains('Cześć'));
      for (final foreign in [...en, ...it]) {
        expect(options, isNot(contains(foreign.termText)));
      }
    });

    test('a DIRECTED pair is not the same pair — ru→en options never reach an en→ru card', () {
      // Same two languages, opposite sides. The far-option branch compares both halves, exactly as
      // the server's recognitionCard() does, so these two decks never lend each other options.
      final reversed = {
        for (final t in en) t.id: (learned: 'en', support: 'ru'),
        // …and one deck the learner studies the other way round.
        for (final t in pl) t.id: (learned: 'en', support: 'pl'),
        for (final t in it) t.id: (learned: 'it', support: 'ru'),
      };
      // Rung 1: the recognition rungs are where far options are dealt, and where both halves matter.
      const firstMeeting = LadderPosition(
        acquisition: Acquisition.learning,
        learningStep: LearningLadder.stepRecognitionForward,
        enrolled: true,
      );

      final session = drill(
        en.first,
        deck: everything,
        pairs: reversed,
        position: firstMeeting,
      );

      for (final other in pl) {
        expect(optionsOf(session), isNot(contains(other.termText)));
      }
    });
  });

  group('the regression: one language in, nothing changes', () {
    test('a single-pair deck still fills its options as it always did', () {
      final session = drill(
        en.first,
        deck: en,
        pairs: {for (final t in en) t.id: (learned: 'en', support: 'ru')},
      );
      final options = optionsOf(session);

      expect(options, contains('hello'));
      // Four options on the choice card: the answer plus three neighbours, exactly as before.
      expect(
        session.cards.firstWhere((c) => c.mode == ExerciseMode.multipleChoice).options,
        hasLength(4),
      );
    });

    test('an ORPHAN word — no folder, no pair to compare — keeps every option it had', () {
      // The pool deliberately survives a deleted folder (п. 102), so a word can have no pair at all.
      // An unverifiable target pair is not a reason to leave the card with one option on it: the
      // gate is skipped and the mirror stays the honest superset it always was.
      final session = drill(en.first, deck: en, pairs: const {});

      expect(
        session.cards.firstWhere((c) => c.mode == ExerciseMode.multipleChoice).options,
        hasLength(4),
      );
    });
  });

  group('the provider resolves the pairs — the wiring, not just the rule', () {
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

    /// The mirror as «Мои слова» sees it: three folders of three pairs, every word synced, the
    /// English one in the pool. No collection is named to the session — that is the whole point of
    /// «Тренировать это слово».
    Future<void> seed() => db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(
          id: 'c-en',
          updatedAt: t0,
          title: const Value('Airport'),
          targetLang: const Value('en'),
          sourceLang: const Value('ru'),
        ),
        CollectionsCompanion.insert(
          id: 'c-it',
          updatedAt: t0,
          title: const Value('Ristorante'),
          targetLang: const Value('it'),
          sourceLang: const Value('ru'),
        ),
        CollectionsCompanion.insert(
          id: 'c-pl',
          updatedAt: t0,
          title: const Value('Lotnisko'),
          targetLang: const Value('pl'),
          sourceLang: const Value('ru'),
        ),
      ],
      termUpserts: [
        for (final t in everything)
          TermsCompanion.insert(
            id: t.id,
            updatedAt: t0,
            termText: Value(t.termText),
            translation: Value(t.translation),
            type: const Value('word'),
          ),
      ],
      itemUpserts: [
        for (final t in en) CollectionItemsCompanion.insert(collectionId: 'c-en', termId: t.id, updatedAt: t0),
        for (final t in pl) CollectionItemsCompanion.insert(collectionId: 'c-pl', termId: t.id, updatedAt: t0),
        for (final t in it) CollectionItemsCompanion.insert(collectionId: 'c-it', termId: t.id, updatedAt: t0),
      ],
      progressUpserts: [
        TermProgressCompanion.insert(
          termId: en.first.id,
          updatedAt: t0,
          state: const Value('review'),
          acquisition: const Value('graduated'),
          successfulReviews: const Value(LearningLadder.dictationMinSuccesses),
          enrolledAt: Value(t0),
        ),
      ],
    );

    test('«Тренировать это слово» from «Мои слова» deals a clean English card', () async {
      await seed();

      final session = await container.read(
        studySessionProvider((
          sessionId: 'S',
          collectionId: null,
          practice: true,
          limit: 20,
          onlyTermId: en.first.id,
        )).future,
      );

      final options = [
        for (final card in session.cards) ...?card.options,
      ];
      expect(options, isNotEmpty, reason: 'the fan must still contain a choice card');
      for (final foreign in [...pl, ...it]) {
        expect(
          options,
          isNot(contains(foreign.termText)),
          reason: 'the provider must hand the builder the pairs it resolved',
        );
      }
    });
  });
}
