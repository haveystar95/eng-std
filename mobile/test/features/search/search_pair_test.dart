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
  const languages = SearchLanguages(taught: 'en', natives: ['ru', 'ro'], defaultNative: 'ru');

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

    test('changing the other language keeps the direction', () {
      // The learner was asking «from Russian into English» and picks Romanian: they are still
      // asking from their own language into English, not the other way about.
      const reversed = SearchPair(source: 'ru', target: 'en');
      expect(reversed.withOther('en', 'ro'), const SearchPair(source: 'ro', target: 'en'));

      const forward = SearchPair(source: 'en', target: 'ru');
      expect(forward.withOther('en', 'ro'), const SearchPair(source: 'en', target: 'ro'));
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
      const shrunk = SearchLanguages(taught: 'en', natives: ['ru'], defaultNative: 'ru');
      expect(await store.load(shrunk), const SearchPair(source: 'en', target: 'ru'));
    });

    test('a corrupted key reads as the default pair, never as a crash', () async {
      await db.setMeta(SearchPairStore.metaKey, '{"s":"en"');
      expect(await store.load(languages), languages.initialPair);

      await db.setMeta(SearchPairStore.metaKey, '{"s":"en","t":"en"}');
      expect(await store.load(languages), languages.initialPair,
          reason: 'a language paired with itself is not a pair');
    });
  });

  group('what the server offered', () {
    test('it degrades to something usable rather than to nothing', () {
      // A body from a server that is older, newer or briefly confused must still leave the pill
      // with a pair it can draw.
      final languages = SearchLanguages.fromJson(const {});

      expect(languages.taught, 'en');
      expect(languages.natives, isNotEmpty);
      expect(languages.initialPair.source, 'en');
    });

    test('it takes the default from the profile, not from the head of the list', () {
      final languages = SearchLanguages.fromJson(const {
        'target': 'en',
        'natives': ['ro', 'ru'],
        'default_native': 'ru',
      });

      expect(languages.initialPair, const SearchPair(source: 'en', target: 'ru'));
    });
  });
}
