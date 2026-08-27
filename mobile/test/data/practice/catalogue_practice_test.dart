import 'dart:math';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:flutter_test/flutter_test.dart';

/// «ТРЕНИРОВКА ПО ТЕМЕ» OVER AN UNTRIAGED COLLECTION.
///
/// The owner's scenario: «зашёл в кафе, открыл тему, прошёл маленькую тренировку без разбора
/// коллекции». Free practice used to draw from the POOL alone, so a collection nobody had swiped
/// through produced an empty session and the button was a dead end.
///
/// What this file pins is the change and, just as importantly, its edges:
///
///   * the collection is drillable without triage;
///   * a word outside the pool is asked only what the matrix opens at
///     [LearningLadder.stepUnenrolledPractice] — choice and assembly, never «напиши по памяти»,
///     «послушай и напечатай» or a whole sentence from dictation;
///   * a word IN the pool is untouched by all of this — the cap is the catalogue's and never leaks
///     onto a word being studied;
///   * the studied words lead the session, the catalogue fills the tail;
///   * and nothing about running the session puts a word into the pool.
///
/// The server's half of the same story is `backend2/tests/Feature/Learning/CataloguePracticeTest.php`.
void main() {
  Term term(String id, String text, {String translation = 'перевод'}) => Term(
    id: id,
    termText: text,
    type: 'word',
    transcription: null,
    translation: translation,
    // Long enough to scramble and to dictate, and it CONTAINS the term, so cloze is playable
    // too: any trainer missing from a session below is the SELECTION's doing, never the data's.
    example: 'Please find the $text before tonight.',
    exampleTranslation: 'Пожалуйста, найдите это до вечера.',
    imageUrl: null,
    imageAuthor: null,
    imageAuthorUrl: null,
    updatedAt: DateTime.utc(2026, 8, 20),
  );

  final terms = [
    term('01KZETBAA50EMHCN6SP80T8DHC', 'reservation', translation: 'бронь'),
    term('01KZETBAB4AW6M9ZFRB3X02CVW', 'front desk', translation: 'стойка регистрации'),
    term('01KZETBAC103WZ24WQ7H087ZJ3', 'towel', translation: 'полотенце'),
    term('01KZETBAD2EWE2H5ZV7WD8JWKT', 'check in', translation: 'заселение'),
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

  /// The trainers a word outside the pool must never be asked in: the two that ask it to be written
  /// out of memory. «Свободная практика ступени 0 = рецептивные режимы; продуктивные (письмо по
  /// памяти, диктант) открываются лестницей» (владелец/архитектор, BUGFIX-2 Ч.2б).
  ///
  /// `listening` used to be on this list and is not any more: writing down a word the phone has just
  /// said, as many times as asked, is RECEPTION — the sound is the question and it stays available
  /// for as long as the learner wants it.
  const withheld = [ExerciseMode.typing, ExerciseMode.dictation];

  /// A pair that has earned every rung, so the trainers it is dealt are the gate's doing.
  const topOfLadder = LadderPosition(
    acquisition: Acquisition.graduated,
    successfulReviews: LearningLadder.dictationMinSuccesses,
    enrolled: true,
  );

  StudySession sessionOver(
    List<Term> deck,
    Map<String, LadderPosition> ladder, {
    int seed = 3,
    int limit = 40,
  }) => LocalPracticeSessionBuilder.build(
    terms: deck,
    limit: limit,
    random: Random(seed),
    sessionId: 'S',
    enabled: everyMode,
    ladder: ladder,
  );

  test('an untriaged collection is drillable — the session is no longer empty', () {
    // Nothing enrolled, nothing ever shown: the state a freshly generated topic is in.
    final session = sessionOver(terms, {for (final t in terms) t.id: LadderPosition.untouched});

    expect(session.cards, isNotEmpty);
    expect(session.cards.map((c) => c.termId).toSet(), {
      for (final t in terms) t.id,
    }, reason: 'every word of the collection is reachable');
  });

  test('a word outside the pool is dealt the easy trainers only, over many seeds', () {
    // Deterministic but broad: the round-robin walks the applicable set, so one seed proves little.
    // These terms support EVERY trainer, so a withheld one appearing would be the gate failing and
    // not the data being thin.
    final dealt = <ExerciseMode>{};
    for (var seed = 0; seed < 12; seed++) {
      dealt.addAll(
        sessionOver(terms, {
          for (final t in terms) t.id: LadderPosition.untouched,
        }, seed: seed).cards.map((c) => c.mode),
      );
    }

    for (final mode in withheld) {
      expect(
        dealt,
        isNot(contains(mode)),
        reason: '$mode asks a word nobody has studied to produce it from memory',
      );
    }
    expect(dealt, isNot(contains(ExerciseMode.intro)), reason: 'practice introduces nothing');
    // …and it is not narrowed down to one trainer either: choice AND assembly, as the owner asked.
    expect(dealt, contains(ExerciseMode.multipleChoice));
    expect(dealt.length, greaterThan(1), reason: 'a drill of one trainer is not a drill');
  });

  test('a card for a word outside the pool reports the rung it was dealt at', () {
    final session = sessionOver(terms, {for (final t in terms) t.id: LadderPosition.untouched});

    for (final card in session.cards) {
      expect(card.ladderStep, LearningLadder.stepUnenrolledPractice);
      // Never the identity-graded direction: the server refuses to grade one for practice.
      expect(card.isIdentityGraded, isFalse);
      expect(card.optionIds, isNull);
    }
  });

  test('in a MIXED session the withheld trainers still reach the pool words, and only them', () {
    // The invariant the cap must not break: it belongs to the catalogue half. A deck big enough for
    // the round-robin to walk every trainer (the rotation seed is card index + crc32 of the id), so
    // «dictation was never dealt» cannot pass by accident.
    final deck = [
      for (var i = 0; i < 24; i++)
        term(
          '01KZETBB${i.toString().padLeft(2, '0')}0EMHCN6SP80T8DH',
          i.isEven ? 'towel$i' : 'front desk$i',
          // Distinct translations: a choice card's near-miss distractors are filtered by meaning,
          // and a deck all meaning the same thing would leave one with no wrong option.
          translation: 'перевод$i',
        ),
    ];
    final studied = {for (var i = 0; i < deck.length; i += 2) deck[i].id};
    final ladder = {
      for (final t in deck) t.id: studied.contains(t.id) ? topOfLadder : LadderPosition.untouched,
    };

    final dealtToPool = <ExerciseMode>{};
    for (var seed = 0; seed < 6; seed++) {
      for (final card in sessionOver(deck, ladder, seed: seed, limit: deck.length).cards) {
        final enrolled = studied.contains(card.termId);
        if (enrolled) dealtToPool.add(card.mode);
        if (!enrolled) {
          expect(
            withheld,
            isNot(contains(card.mode)),
            reason: 'a catalogue word was dealt ${card.mode}',
          );
        }
      }
    }

    expect(
      dealtToPool,
      containsAll(withheld),
      reason: 'missing ${withheld.toSet().difference(dealtToPool)} — the cap leaked onto the pool',
    );
  });

  test('the studied words lead the session; the catalogue fills the tail', () {
    const onLadder = LadderPosition(
      acquisition: Acquisition.learning,
      learningStep: LearningLadder.stepRecognitionReverse,
      enrolled: true,
    );
    final studied = {terms[1].id, terms[3].id};

    for (var seed = 0; seed < 8; seed++) {
      final cards = sessionOver(terms, {
        for (final t in terms) t.id: studied.contains(t.id) ? onLadder : LadderPosition.untouched,
      }, seed: seed).cards;

      var seenCatalogue = false;
      for (final card in cards) {
        if (!studied.contains(card.termId)) {
          seenCatalogue = true;
          continue;
        }
        expect(
          seenCatalogue,
          isFalse,
          reason: 'a pool word came after a catalogue one (seed $seed)',
        );
      }
    }
  });

  test('a session that cannot fit everything spends its size on the pool first', () {
    const onLadder = LadderPosition(
      acquisition: Acquisition.learning,
      learningStep: LearningLadder.stepRecognitionReverse,
      enrolled: true,
    );
    final studied = {terms[0].id, terms[2].id};

    final cards = sessionOver(terms, {
      for (final t in terms) t.id: studied.contains(t.id) ? onLadder : LadderPosition.untouched,
    }, limit: 2).cards;

    expect(
      cards.map((c) => c.termId).toSet(),
      studied,
      reason: 'the catalogue never crowds out a word being studied',
    );
  });

  test('an ENROLLED rung-0 word is drilled too, at the same easy corner (BUG-2)', () {
    // The refusal this used to pin is gone: the two populations turned out to be ONE case, «no rung
    // of its own», and the enrolled half was refused while the un-enrolled half was drilled — so
    // deciding to learn a word made it LESS practisable than leaving it in the catalogue.
    const enrolledUnmet = LadderPosition(acquisition: Acquisition.isNew, enrolled: true);
    expect(enrolledUnmet.drillsAtOwnRung, isFalse);
    expect(LadderPosition.untouched.drillsAtOwnRung, isFalse);
    expect(enrolledUnmet.practiceCardStep, LearningLadder.stepUnenrolledPractice);

    final cards = sessionOver(terms, {
      terms.first.id: enrolledUnmet,
      for (final t in terms.skip(1)) t.id: LadderPosition.untouched,
    }).cards;

    expect(cards.map((c) => c.termId), contains(terms.first.id));
    expect(cards, isNotEmpty, reason: 'the rest of the collection still plays');
  });

  test('a choice card for a catalogue word is never a one-option card', () {
    for (var seed = 0; seed < 8; seed++) {
      final session = sessionOver(terms, {
        for (final t in terms) t.id: LadderPosition.untouched,
      }, seed: seed);
      for (final card in session.cards) {
        if (card.options == null) continue;
        expect(card.options!.length, greaterThanOrEqualTo(LocalPracticeSessionBuilder.minOptions));
      }
    }
  });

  group('and it still moves nothing', () {
    late AppDatabase db;
    setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
    tearDown(() => db.close());

    test('building the session enrols nobody — «Мои слова» does not grow', () async {
      final t0 = DateTime.utc(2026, 8, 20, 9);
      await db.applyDelta(
        collectionUpserts: [
          CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Отель')),
        ],
        termUpserts: [
          for (final t in terms)
            TermsCompanion.insert(
              id: t.id,
              updatedAt: t0,
              termText: Value(t.termText),
              translation: Value(t.translation),
            ),
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

      final mirrored = await db.collectionTerms('c1');
      final session = LocalPracticeSessionBuilder.build(
        terms: mirrored,
        limit: 20,
        random: Random(1),
        sessionId: 'S',
        enabled: everyMode,
        ladder: await db.ladderPositions([for (final t in mirrored) t.id]),
      );

      expect(session.cards, isNotEmpty, reason: 'the collection is drillable untriaged');
      expect(await db.watchPool().first, isEmpty, reason: 'and the pool is still empty');
      expect(await db.watchLearnableCount().first, 0);
    });
  });
}
