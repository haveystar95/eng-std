import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/features/search/search_history.dart';

/// «Вы искали» — three lines on the empty search screen, and nothing else in the app depends on
/// them. Which is exactly why every read here has to survive a key that is missing, truncated, or
/// written by a build that is no longer installed: a broken history must cost the screen its three
/// lines, never its ability to open.
void main() {
  late AppDatabase db;
  late SearchHistory history;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    history = SearchHistory(db);
  });

  tearDown(() => db.close());

  test('a fresh device has no history and does not mind', () async {
    expect(await history.load(), isEmpty);
  });

  test('a search comes back with what it turned out to mean', () async {
    await history.remember(const RecentSearch(word: 'hollow', translation: 'пустой', cefr: 'B2'));

    final recent = await history.load();
    expect(recent.single.word, 'hollow');
    expect(recent.single.translation, 'пустой');
    expect(recent.single.cefr, 'B2');
  });

  test('the newest search is first', () async {
    await history.remember(const RecentSearch(word: 'hollow'));
    await history.remember(const RecentSearch(word: 'invoice'));

    expect((await history.load()).map((r) => r.word), ['invoice', 'hollow']);
  });

  test('re-searching a word MOVES it instead of listing it twice', () async {
    await history.remember(const RecentSearch(word: 'hollow'));
    await history.remember(const RecentSearch(word: 'invoice'));
    await history.remember(const RecentSearch(word: 'HOLLOW', translation: 'пустой'));

    final recent = await history.load();
    expect(recent.map((r) => r.word), ['HOLLOW', 'invoice']);
    expect(recent.first.translation, 'пустой', reason: 'the newer answer wins');
  });

  test('it keeps three lines, because the screen is built around three', () async {
    for (final word in ['a', 'b', 'c', 'd']) {
      await history.remember(RecentSearch(word: word));
    }

    expect((await history.load()).map((r) => r.word), ['d', 'c', 'b']);
  });

  test('a blank query is not a search', () async {
    await history.remember(const RecentSearch(word: '   '));

    expect(await history.load(), isEmpty);
  });

  test('a word that «means itself» is not a line', () async {
    // «случай — случай» is a hint that failed, not a search result: the translator was asked to
    // turn a Russian word into Russian and handed it back. Nothing to salvage — the word alone
    // would be a log entry, and this section is a way back in.
    await history.remember(const RecentSearch(word: 'случай', translation: 'случай'));

    expect(await history.load(), isEmpty);
  });

  test('the same junk already on the device is filtered on the way out', () async {
    // Written by a build that did not know which way it was translating. Filtered rather than
    // migrated: three lines of local cache have no history worth preserving, and the filter also
    // catches whatever a bad answer writes tomorrow.
    await db.setMeta(
      SearchHistory.metaKey,
      '[{"w":"случай","t":"случай"},{"w":"invoice","t":"счёт"}]',
    );

    expect((await history.load()).map((r) => r.word), ['invoice']);
  });

  test('a word with no translation at all still earns its line', () async {
    // The rule is «it does not say the word means itself», not «it has an answer». A word opened
    // from the catalogue with no translation on hand is still somewhere the learner has been.
    await history.remember(const RecentSearch(word: 'hollow'));

    expect((await history.load()).single.word, 'hollow');
  });

  test('a corrupted key reads as «no history», never as a crash', () async {
    await db.setMeta(SearchHistory.metaKey, '{"not":"a list"');
    expect(await history.load(), isEmpty);

    await db.setMeta(SearchHistory.metaKey, '[1, null, {"t":"перевод без слова"}]');
    expect(await history.load(), isEmpty);
  });
}
