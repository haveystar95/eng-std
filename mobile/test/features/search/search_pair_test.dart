import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/features/search/search_pair.dart';

/// Which way the search field points, and how that survives a restart.
///
/// The rule that matters most here is the LAST one: a remembered pair must never become a request
/// the server refuses. The stored value outlives the deployment that wrote it, so a language
/// dropped from the config has to degrade to the default pair rather than to a 422 the learner has
/// to read on a screen that was working yesterday.
void main() {
  // The RS-3 contract, roughly as the live deployment answers it: seven taught languages, and every
  // language of the catalogue readable — so most codes appear in BOTH lists.
  const languages = SearchLanguages(
    targets: ['en', 'ro', 'es'],
    natives: ['ru', 'en', 'ro', 'es'],
    defaultTaught: 'en',
    defaultNative: 'ru',
  );

  group('the pair itself', () {
    test('a tap is the same two languages, the other way', () {
      const pair = SearchPair(source: 'en', target: 'ru');

      expect(pair.swapped, const SearchPair(source: 'ru', target: 'en'));
      expect(pair.swapped.swapped, pair);
    });

    test('it knows which side is the headline', () {
      // «RU → EN»: the answer is the word being taught, so the small card sets it large.
      expect(const SearchPair(source: 'ru', target: 'en').reversedFor('en'), isTrue);
      expect(const SearchPair(source: 'en', target: 'ru').reversedFor('en'), isFalse);
    });

  });

  group('which side is being learned', () {
    test('the pair names it when only one side is taught', () {
      // «ru → ro»: Russian is not a language this deployment teaches, so Romanian is the term side
      // whichever way round the learner typed it.
      expect(languages.taughtSideOf(const SearchPair(source: 'ru', target: 'ro')), 'ro');
      expect(languages.taughtSideOf(const SearchPair(source: 'ro', target: 'ru')), 'ro');
    });

    test('two taught sides are read from the DIRECTION: you ask for what you do not have', () {
      // «en → es» — «translate this English word into Spanish» — is somebody studying SPANISH. The
      // learner types what they already have and asks for what they do not, so the TARGET side is
      // the taught one (DECISIONS п. 147, amended 24.08).
      expect(languages.taughtSideOf(const SearchPair(source: 'en', target: 'es')), 'es');
      // …and the same sentence backwards is just as literal.
      expect(languages.taughtSideOf(const SearchPair(source: 'es', target: 'en')), 'en');
      expect(languages.taughtSideOf(const SearchPair(source: 'ro', target: 'es')), 'es');
    });

    test('the reported bug: «English → Español» must not mean «studying English»', () {
      // From the phone: with the pill on en → es the card put the ENGLISH word in the headline with
      // the Spanish in small type, and the save sheet offered to file it under «English ← Spanish».
      // The old rule broke the tie with the legacy `target` (frozen at `en`), which is right exactly
      // while somebody studies one language.
      final pair = LearningPair.of(const SearchPair(source: 'en', target: 'es'), languages);

      expect(pair, const LearningPair(learned: 'es', support: 'en'));
    });

    test('it reads en → es the same way it reads ru → en, which was never wrong', () {
      // The report was an INEQUALITY: «ru → en behaves right, en → es behaves backwards». Both are
      // «typed the support language, got the word being studied», so both must answer alike.
      final spanish = LearningPair.of(const SearchPair(source: 'en', target: 'es'), languages);
      final english = LearningPair.of(const SearchPair(source: 'ru', target: 'en'), languages);

      expect(spanish.learned, 'es');
      expect(english.learned, 'en');
      expect(spanish.support, 'en');
      expect(english.support, 'ru');
    });

    test('the roles a save obeys come from that, not from the direction', () {
      // «поддержка es, учу en» — the pair кадр A-3.1 was run in. The collection born from this save
      // is `en ← es`, whichever way the pill happened to be pointing.
      expect(
        LearningPair.of(const SearchPair(source: 'es', target: 'en'), languages),
        const LearningPair(learned: 'en', support: 'es'),
      );
      expect(
        LearningPair.of(const SearchPair(source: 'ro', target: 'ru'), languages),
        const LearningPair(learned: 'ro', support: 'ru'),
      );
    });
  });

  group('what a pill may offer', () {
    test('a support-only neighbour leaves the taught half to this side', () {
      // Russian is not taught here, so the other pill has to hold a language that is — the whole
      // «На какой» list, which is what became a real picker when the server named more than one.
      expect(languages.optionsAgainst('ru'), ['en', 'ro', 'es']);
    });

    test('a taught neighbour covers the taught half, so this side may be anything readable', () {
      expect(languages.optionsAgainst('en'), ['ru', 'ro', 'es']);
    });

    test('the neighbour itself is never on offer', () {
      // «en → en» is not a pair. The way to say «the other way round» is the arrow between the
      // pills, so the language opposite is left out of the sheet rather than shown greyed out.
      for (final code in ['en', 'ru', 'ro', 'es']) {
        expect(languages.optionsAgainst(code), isNot(contains(code)));
      }
    });

    test('a pair the pills can build is one the server serves', () {
      for (final opposite in ['en', 'ru', 'ro', 'es']) {
        for (final code in languages.optionsAgainst(opposite)) {
          expect(
            languages.serves(SearchPair(source: code, target: opposite)),
            isTrue,
            reason: '$code→$opposite was offered',
          );
        }
      }
    });

    test('the arrow can never make the two sides the same', () {
      // By construction: a swap keeps the two languages and only exchanges the slots, and the rule
      // for a served pair is symmetric.
      for (final pair in [
        const SearchPair(source: 'en', target: 'ru'),
        const SearchPair(source: 'es', target: 'en'),
        const SearchPair(source: 'ro', target: 'ru'),
      ]) {
        expect(pair.swapped.source, isNot(pair.swapped.target));
        expect(languages.serves(pair.swapped), languages.serves(pair));
      }
    });
  });

  group('what the device remembers', () {
    late AppDatabase db;
    late SearchPairStore store;

    setUp(() {
      db = AppDatabase.forTesting(NativeDatabase.memory());
      store = SearchPairStore(db);
    });

    tearDown(() => db.close());

    test('a fresh device opens on the taught language into the learner\'s own', () async {
      // Looking up a word they have just met is the commoner half of the job, so that is the way
      // round it opens — and it is their PROFILE language, not the first entry of the list.
      expect(await store.load(languages), const SearchPair(source: 'en', target: 'ru'));
    });

    test('a chosen pair survives a restart', () async {
      await store.save(const SearchPair(source: 'ru', target: 'en'));

      expect(await store.load(languages), const SearchPair(source: 'ru', target: 'en'));
    });

    test('a remembered language the server no longer serves falls back', () async {
      await store.save(const SearchPair(source: 'en', target: 'ro'));

      // Romanian dropped from the deployment. Without this the screen would open on a pair the
      // server refuses, and the learner would meet a 422 on a screen that worked yesterday.
      const shrunk = SearchLanguages(
        targets: ['en'],
        natives: ['ru'],
        defaultTaught: 'en',
        defaultNative: 'ru',
      );
      expect(await store.load(shrunk), const SearchPair(source: 'en', target: 'ru'));
    });

    test('a pair whose two sides became the same language falls back, and does not throw', () async {
      // Not reachable from the pills — but the key is a string on a device, and the lists it was
      // written against move underneath it. It degrades to the opening pair like anything else.
      await db.setMeta(SearchPairStore.metaKey, '{"s":"es","t":"es"}');

      expect(await store.load(languages), languages.initialPair);
      expect(languages.initialPair.source, isNot(languages.initialPair.target));
    });

    test('a learner whose own language is also taught still opens on a pair', () async {
      // `natives` is the whole catalogue now, so an English speaker's `default_native` is `en` —
      // and «en → en» is the one pair the server refuses. The opening position steps around it.
      const englishSpeaker = SearchLanguages(
        targets: ['en', 'es'],
        natives: ['en', 'es', 'ru'],
        defaultTaught: 'en',
        defaultNative: 'en',
      );

      final opening = await store.load(englishSpeaker);
      expect(opening.source, isNot(opening.target));
      expect(englishSpeaker.serves(opening), isTrue);
      expect(opening, const SearchPair(source: 'es', target: 'en'));
    });

    test('a corrupted key reads as the default pair, never as a crash', () async {
      await db.setMeta(SearchPairStore.metaKey, '{"s":"en"');
      expect(await store.load(languages), languages.initialPair);

      await db.setMeta(SearchPairStore.metaKey, '{"s":"en","t":"en"}');
      expect(
        await store.load(languages),
        languages.initialPair,
        reason: 'a language paired with itself is not a pair',
      );
    });
  });

  group('what the server offered', () {
    test('the body the live deployment answers with, verbatim', () {
      // Copied from `GET /api/v1/search/languages` on 2026-08-24, after RS-3. Pinned as it stands
      // rather than paraphrased: this is the shape the pills are being asked to draw, and the two
      // lists OVERLAP — every taught language is also readable — which is the fact the whole of
      // A-3.1 turns on.
      final languages = SearchLanguages.fromJson(const {
        'target': 'en',
        'targets': ['en', 'ro', 'es', 'de', 'fr', 'it', 'pl'],
        'natives': ['ru', 'en', 'uk', 'ro', 'es', 'de', 'fr', 'it', 'pt', 'pl', 'tr', 'zh', 'ja'],
        'default_native': 'ru',
      });

      // The pair the screen opens on, and the two sheets it opens from there.
      expect(languages.initialPair, const SearchPair(source: 'en', target: 'ru'));
      expect(languages.optionsAgainst('ru'), ['en', 'ro', 'es', 'de', 'fr', 'it', 'pl']);
      expect(languages.optionsAgainst('en').length, 12, reason: 'the catalogue, less English');
      expect(languages.optionsAgainst('en'), isNot(contains('en')));

      // «поддержка es, учу en» — the pair the live run was done in. The server answered that same
      // request with `reversed: true`, which is it saying the same thing: the taught side is `en`.
      const pair = SearchPair(source: 'es', target: 'en');
      expect(languages.serves(pair), isTrue);
      expect(languages.taughtSideOf(pair), 'en');
      expect(
        LearningPair.of(pair, languages),
        const LearningPair(learned: 'en', support: 'es'),
      );
    });

    test('it reads the two roles the RS-3 body names', () {
      final languages = SearchLanguages.fromJson(const {
        'target': 'en',
        'targets': ['en', 'ro', 'es', 'de', 'fr', 'it', 'pl'],
        'natives': ['ru', 'uk', 'en', 'ro', 'es'],
        'default_native': 'ru',
      });

      expect(languages.targets, ['en', 'ro', 'es', 'de', 'fr', 'it', 'pl']);
      expect(languages.natives, ['ru', 'uk', 'en', 'ro', 'es']);
      expect(languages.teaches('pl'), isTrue);
      expect(languages.teaches('uk'), isFalse, reason: 'readable is not the same as teachable');
      // Both lists are read as ROLES: `ru → pl` is a pair now, with no English anywhere in it.
      expect(languages.serves(const SearchPair(source: 'ru', target: 'pl')), isTrue);
      expect(languages.serves(const SearchPair(source: 'ru', target: 'uk')), isFalse);
    });

    test('a body without the new lists still leaves the pill with a pair', () {
      // Compatibility with a pre-RS-3 server is not owed, but the parse must not fall over: one
      // taught language named the old way is a one-entry `targets`.
      final languages = SearchLanguages.fromJson(const {
        'target': 'en',
        'natives': ['ro', 'ru'],
        'default_native': 'ru',
      });

      expect(languages.targets, ['en']);
      expect(languages.initialPair, const SearchPair(source: 'en', target: 'ru'));
    });

    test('it degrades to something usable rather than to nothing', () {
      // A body from a server that is older, newer or briefly confused must still leave the pill
      // with a pair it can draw.
      final languages = SearchLanguages.fromJson(const {});

      expect(languages.defaultTaught, 'en');
      expect(languages.targets, isNotEmpty);
      expect(languages.natives, isNotEmpty);
      expect(languages.initialPair.source, 'en');
      expect(languages.initialPair.source, isNot(languages.initialPair.target));
    });

    test('a body of rubbish is read as an empty list, not as a crash', () {
      final languages = SearchLanguages.fromJson(const {
        'targets': ['en', 7, '', '  ro  ', null],
        'natives': 'ru',
      });

      expect(languages.targets, ['en', 'ro']);
      expect(languages.natives, ['ru']);
    });

    test('it takes the default from the profile, not from the head of the list', () {
      final languages = SearchLanguages.fromJson(const {
        'target': 'en',
        'targets': ['en', 'ro'],
        'natives': ['ro', 'ru'],
        'default_native': 'ru',
      });

      expect(languages.initialPair, const SearchPair(source: 'en', target: 'ru'));
    });
  });
}
