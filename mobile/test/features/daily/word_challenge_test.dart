import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/features/daily/word_challenge.dart';
import 'package:eng_std/features/daily/word_challenge_store.dart';

/// СЛОВО-ВЫЗОВ — the stub's brain, held to the four promises it makes to the learner.
///
/// It is a STUB by design: DAILY-1 replaces the source with a server pick by level. What these tests
/// pin is therefore not «which word» but the properties that must survive that replacement — one
/// word per day, a word they are not already studying, options they can actually tell apart, and
/// nothing at all when the mirror cannot honestly produce a card.
void main() {
  ChallengeTerm term(
    String id,
    String text,
    String translation, {
    String learned = 'en',
    String support = 'ru',
    bool inPool = false,
    String? example = 'A sentence with the word in it.',
  }) => ChallengeTerm(
    termId: id,
    text: text,
    translation: translation,
    learned: learned,
    support: support,
    inPool: inPool,
    example: example,
    exampleTranslation: example == null ? null : 'Предложение со словом.',
  );

  final english = [
    term('t1', 'reluctant', 'неохотный'),
    term('t2', 'reliable', 'надёжный'),
    term('t3', 'noticeable', 'заметный'),
    term('t4', 'withdraw', 'снимать'),
  ];

  group('the pick is a function of the day, and of nothing else', () {
    test('the same seed gives the same word twice', () {
      final first = pickWordChallenge(mirror: english, seed: '2026-08-27:u1');
      final again = pickWordChallenge(mirror: english, seed: '2026-08-27:u1');

      expect(first!.termId, again!.termId);
      // …and the options are in the same slots. A card that reshuffles between two builds of the
      // same screen is a card the learner cannot answer.
      expect(first.options, again.options);
    });

    test('a different day is a different word, and a different learner too', () {
      final days = {
        for (final d in ['2026-08-27', '2026-08-28', '2026-08-29', '2026-08-30'])
          pickWordChallenge(mirror: english, seed: '$d:u1')!.termId,
      };
      expect(days.length, greaterThan(1), reason: 'the word must turn over with the date');

      final mine = pickWordChallenge(mirror: english, seed: '2026-08-27:u1')!.termId;
      final theirs = pickWordChallenge(mirror: english, seed: '2026-08-27:u2')!.termId;
      // Not a guarantee for every pair of ids — but for THESE two it must differ, or the seed is
      // not reading the user at all.
      expect(mine, isNot(theirs));
    });

    test('the pinned word wins over the seed — and survives being taken into study', () {
      // «Учить» moves the word into the pool, which takes it out of the candidate set. The day's
      // word must not change under the learner's hand the moment they take it.
      final taken = [term('t1', 'reluctant', 'неохотный', inPool: true), ...english.skip(1)];
      final pinned = pickWordChallenge(mirror: taken, seed: 'any:u1', pinnedTermId: 't1');

      expect(pinned!.termId, 't1');
    });
  });

  group('what the card may ask about', () {
    test('never a word already in the queue', () {
      final pooled = english.map((t) => term(t.termId, t.text, t.translation, inPool: true)).toList();

      // Every candidate is in the pool: «Учить» would be a no-op and the challenge would be asking
      // about homework. No card.
      expect(pickWordChallenge(mirror: pooled, seed: 'd:u1'), isNull);
    });

    test('never a word with no example — the answer state has one to show', () {
      final bare = [
        term('t1', 'reluctant', 'неохотный', example: null),
        ...english.skip(1).map((t) => term(t.termId, t.text, t.translation, inPool: true)),
      ];

      expect(pickWordChallenge(mirror: bare, seed: 'd:u1'), isNull);
    });

    test('an empty mirror is no card at all, not an empty one', () {
      expect(pickWordChallenge(mirror: const [], seed: 'd:u1'), isNull);
    });
  });

  group('the options', () {
    test('are three, one of them right', () {
      final c = pickWordChallenge(mirror: english, seed: 'd:u1')!;

      expect(c.options, hasLength(kChallengeOptions));
      expect(c.options, contains(c.translation));
      expect(c.options.toSet(), hasLength(kChallengeOptions), reason: 'no option twice');
    });

    test('come from the CARD\'S OWN PAIR and never from another', () {
      // The mirror mixes pairs by design — the pool is an attribute of (user, term) and the words in
      // it come from folders of different languages (DECISIONS п. 128). A Polish option under an
      // English word is right in its own language, so the card would ask nothing (MIX-1a).
      final mixed = [
        ...english,
        term('p1', 'cześć', 'привет', learned: 'pl'),
        term('p2', 'dziękuję', 'спасибо', learned: 'pl'),
        term('p3', 'proszę', 'пожалуйста', learned: 'pl'),
      ];

      final c = pickWordChallenge(mirror: mixed, seed: 'd:u1')!;
      final subject = mixed.firstWhere((t) => t.termId == c.termId);
      final ownPair = {
        for (final t in mixed)
          if (t.learned == subject.learned && t.support == subject.support) t.translation,
      };

      // EVERY option is a translation of the subject's own pair — including the right one.
      expect(c.options.every(ownPair.contains), isTrue, reason: 'options: ${c.options}');
    });

    test('a pair with nothing to compare against draws no card', () {
      // One English word and no second translation of the same pair: there is no honest set of three.
      final lonely = [
        term('t1', 'reluctant', 'неохотный'),
        term('p1', 'cześć', 'привет', learned: 'pl'),
      ];

      expect(pickWordChallenge(mirror: lonely, seed: 'd:u1'), isNull);
    });

    test('never offers the right answer twice under two spellings', () {
      final twins = [
        term('t1', 'reluctant', 'неохотный'),
        term('t2', 'unwilling', 'Неохотный'), // same word, other case — two true answers
        term('t3', 'reliable', 'надёжный'),
        term('t4', 'noticeable', 'заметный'),
      ];

      final c = pickWordChallenge(mirror: twins, seed: 'd:u1')!;
      final lowered = c.options.map((o) => o.toLowerCase()).toList();

      expect(lowered.toSet(), hasLength(kChallengeOptions));
    });
  });

  group('what it remembers between launches', () {
    late AppDatabase db;
    late WordChallengeStore store;
    final t0 = DateTime(2026, 8, 27, 9);

    setUp(() {
      db = AppDatabase.forTesting(NativeDatabase.memory());
      store = WordChallengeStore(db);
    });
    tearDown(() => db.close());

    Future<void> seed() => db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(
          id: 'c1',
          updatedAt: t0,
          title: const Value('Слова'),
          sourceLang: const Value('ru'),
          targetLang: const Value('en'),
        ),
      ],
      termUpserts: [
        for (final t in english)
          TermsCompanion.insert(
            id: t.termId,
            updatedAt: t0,
            termText: Value(t.text),
            translation: Value(t.translation),
            example: Value(t.example),
          ),
      ],
      itemUpserts: [
        for (var i = 0; i < english.length; i++)
          CollectionItemsCompanion.insert(
            collectionId: 'c1',
            termId: english[i].termId,
            updatedAt: t0,
            position: Value(i),
          ),
      ],
    );

    test('the word holds until midnight and turns over after it', () async {
      await seed();

      final morning = await store.today(now: t0, userId: 'u1');
      final evening = await store.today(now: t0.add(const Duration(hours: 12)), userId: 'u1');
      expect(morning!.termId, evening!.termId);

      final tomorrow = await store.today(now: t0.add(const Duration(days: 1)), userId: 'u1');
      expect(tomorrow, isNotNull);
    });

    test('the collapse survives a restart, and the run survives the day', () async {
      await seed();
      final today = (await store.today(now: t0, userId: 'u1'))!;

      await store.answer(now: t0, challenge: today, option: today.translation);
      await store.collapse(now: t0);

      // A NEW store over the same database is what a relaunch is.
      final afterRestart = await WordChallengeStore(db).today(now: t0, userId: 'u1');
      expect(afterRestart!.collapsed, isTrue);
      expect(afterRestart.chosen, today.translation);

      // The run outlives the day — it is the only thing that does, and the counter is worthless
      // otherwise.
      final nextDay = await store.today(now: t0.add(const Duration(days: 1)), userId: 'u1');
      expect(nextDay!.streak, 1);
      expect(nextDay.collapsed, isFalse, reason: 'a new day is a new question');
      expect(nextDay.chosen, isNull);
    });

    test('a miss puts the run back to zero', () async {
      await seed();
      var today = (await store.today(now: t0, userId: 'u1'))!;
      await store.answer(now: t0, challenge: today, option: today.translation);

      final second = t0.add(const Duration(days: 1));
      today = (await store.today(now: second, userId: 'u1'))!;
      expect(today.streak, 1);

      final wrong = today.options.firstWhere((o) => o != today.translation);
      await store.answer(now: second, challenge: today, option: wrong);

      final third = await store.today(now: t0.add(const Duration(days: 2)), userId: 'u1');
      expect(third!.streak, 0);
    });

    test('a second tap on the same day does not re-score the run', () async {
      await seed();
      final today = (await store.today(now: t0, userId: 'u1'))!;

      await store.answer(now: t0, challenge: today, option: today.translation);
      final wrong = today.options.firstWhere((o) => o != today.translation);
      await store.answer(now: t0, challenge: today, option: wrong); // a mis-tap, not an answer

      final again = await store.today(now: t0, userId: 'u1');
      expect(again!.chosen, today.translation);
      // Counted ONCE. Reading the answered day back must not score it a second time.
      expect(again.streak, 1);
    });

    test('an empty mirror produces no card and no crash', () async {
      expect(await store.today(now: t0, userId: 'u1'), isNull);
    });

    test('a word already in the pool is never the subject', () async {
      await seed();
      // Everything enrolled: the learner is studying all they own.
      for (final t in english) {
        await db.enrollLocally(t.termId, t0);
      }

      expect(await WordChallengeStore(db).today(now: t0, userId: 'u1'), isNull);
    });
  });
}
