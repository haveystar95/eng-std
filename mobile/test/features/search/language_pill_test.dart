import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/search_pair.dart';
import 'package:eng_std/features/search/search_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

/// The two pills — the control that says which pair the answer comes back in.
///
/// It replaced automatic language detection, and these tests pin the difference: the pair is
/// STATED, it is sent on every call, the arrow between the pills flips it, and nothing on the
/// screen ever explains it. Since A-3 the two halves are pickers in their own right, and since
/// A-3.1 each offers what the SERVER says may stand beside the other pill — so nothing about which
/// language goes where is written in the app.
///
/// What the deployment serves in these tests: Spanish and Romanian are taught alongside English,
/// and every language of the catalogue is readable — which is what makes «обе стороны изучаемые»
/// reachable at all.
const _served = SearchLanguages(
  targets: ['en', 'ro', 'es'],
  natives: ['ru', 'en', 'ro', 'es'],
  defaultTaught: 'en',
  defaultNative: 'ru',
);

class _Api implements ApiClient {
  _Api({this.languages, this.hint, this.hits = const []});

  final SearchLanguages? languages;
  final String? hint;

  /// What the free search answers with. Mutable so a test can change the answer between two pairs.
  List<SearchHit> hits;

  /// Every pair the screen asked for, in order: `['en→ru', 'ru→en']`.
  final List<String> pairs = [];
  final List<String> searchPairs = [];

  @override
  Future<SearchLanguages> searchLanguages() async => languages ?? _served;

  @override
  Future<List<SearchHit>> search(
    String query, {
    int limit = 20,
    String? source,
    String? target,
  }) async {
    searchPairs.add('$source→$target');

    return hits;
  }

  @override
  Future<InstantHint> instantHint(String query, {String? source, String? target}) async {
    pairs.add('$source→$target');

    return InstantHint(query: query, translation: hint, reversed: target == 'en');
  }

  @override
  Future<LookupOutcome> lookupWord(String query, {String? source, String? target}) async {
    pairs.add('lookup $source→$target');

    return const LookupOutcome(dailyCap: 5);
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> _pump(WidgetTester tester, _Api api, {AppDatabase? db}) async {
  final database = db ?? AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(database.close);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(database),
        apiClientProvider.overrideWithValue(api),
        collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
      ],
      child: MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru')],
        theme: buildAppTheme(),
        home: const SearchScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _type(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField), text);
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump();
  await tester.pump();
}

/// The arrow between the two pills.
Finder get _swap => find.bySemanticsLabel('Поменять языки местами');

void main() {
  testWidgets('opens on the taught language into the learner\'s own, named in their own languages', (
    tester,
  ) async {
    await _pump(tester, _Api());

    // Endonyms, not codes and not names in the interface language (DECISIONS п. 135).
    expect(find.text('English'), findsOneWidget);
    expect(find.text('Русский'), findsOneWidget);
  });

  testWidgets('each pill says which side of the pair it is', (tester) async {
    await _pump(tester, _Api());

    // The arrow answers «which way»; only these two answer «which of them am I learning», which is
    // the question a swap re-opens every time.
    expect(find.text('С КАКОГО'), findsOneWidget);
    expect(find.text('НА КАКОЙ'), findsOneWidget);
  });

  testWidgets('sends the pair on every call', (tester) async {
    final api = _Api(hint: 'счёт');
    await _pump(tester, api);
    await _type(tester, 'invoice');

    // Both halves, every time. The server never guesses and the vendor is never asked to detect.
    expect(api.pairs, ['en→ru']);
    expect(api.searchPairs, ['en→ru']);
  });

  testWidgets('the arrow flips it, and the screen re-asks in the new direction', (tester) async {
    final api = _Api(hint: 'счёт');
    await _pump(tester, api);
    await _type(tester, 'invoice');
    api.pairs.clear();
    api.searchPairs.clear();

    await tester.tap(_swap);
    await tester.pumpAndSettle();

    // Not just a label change: anything on screen answers the OLD question, so it is asked again.
    expect(api.pairs, ['ru→en']);
    expect(api.searchPairs, ['ru→en']);
  });

  testWidgets('the flipped pair survives a rebuild of the screen', (tester) async {
    final db = AppDatabase.forTesting(NativeDatabase.memory());
    addTearDown(db.close);

    await _pump(tester, _Api(), db: db);
    await tester.tap(_swap);
    await tester.pumpAndSettle();

    // A fresh screen over the same device store — the app restarting, in effect.
    await _pump(tester, _Api(), db: db);

    expect(await SearchPairStore(db).load(_served), const SearchPair(source: 'ru', target: 'en'));
  });

  testWidgets('the support pill offers the languages the server says may read', (tester) async {
    final api = _Api(hint: 'счёт');
    await _pump(tester, api);
    await _type(tester, 'invoice');
    api.pairs.clear();
    api.searchPairs.clear();

    await tester.tap(find.text('Русский'));
    await tester.pumpAndSettle();

    // Both on offer, named in their own language — the way this app names languages everywhere.
    // «Română», the language; `România` (the country) was the bug HYG-1 fixed.
    expect(find.text('Русский'), findsWidgets);
    expect(find.text('Română'), findsOneWidget);

    await tester.tap(find.text('Română'));
    await tester.pumpAndSettle();

    expect(find.text('Română'), findsOneWidget);
    expect(find.text('English'), findsOneWidget);
    // A picked language is a new pair, so the query on screen is asked again in it — exactly as a
    // swap is.
    expect(api.pairs, ['en→ro']);
    expect(api.searchPairs, ['en→ro']);
  });

  testWidgets('the taught pill offers the languages the server says may be LEARNED', (tester) async {
    // It was a label while `GET /search/languages` named one taught language (DECISIONS п. 134,
    // lifted by RS-3). Now the endpoint names several, so the pill is a picker like the other one —
    // and it offers `targets`, not the catalogue: «С какого» reads Russian, so this side has to
    // carry the taught half of the pair.
    final api = _Api(hint: 'счёт');
    await _pump(tester, api);
    await _type(tester, 'invoice');
    api.pairs.clear();
    api.searchPairs.clear();

    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();

    expect(find.text('Română'), findsOneWidget);
    expect(find.text('Español'), findsOneWidget);
    // Russian is readable but not taught, so it cannot stand on this side — and it is the other
    // pill's language besides.
    expect(find.text('Русский'), findsOneWidget, reason: 'the other pill, not a row in the sheet');

    await tester.tap(find.text('Español'));
    await tester.pumpAndSettle();

    expect(api.pairs, ['es→ru']);
    expect(api.searchPairs, ['es→ru']);
  });

  testWidgets('a pill still opens no sheet when one language is left to offer', (tester) async {
    // A sheet with a single row is a dead end that still costs a tap to close. This is the shape a
    // deployment that teaches one language keeps — and the shape any pill takes when the pair has
    // used up the list.
    await _pump(
      tester,
      _Api(
        languages: const SearchLanguages(
          targets: ['en'],
          natives: ['ru', 'en'],
          defaultTaught: 'en',
          defaultNative: 'ru',
        ),
      ),
    );

    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();

    expect(find.byType(AppSheetRow), findsNothing);
  });

  testWidgets('neither sheet offers the language the other pill is holding', (tester) async {
    // «en → en» is not a pair, and the server refuses it. It is left OUT of the sheet rather than
    // shown greyed out: the only thing to do about «I want English on both sides» is nothing, and
    // the way to say «the other way round» is the arrow.
    final api = _Api();
    await _pump(tester, api);

    // The support side first: English is readable, and it is what «С какого» holds.
    await tester.tap(find.text('Русский'));
    await tester.pumpAndSettle();
    expect(find.byType(AppSheetRow), findsWidgets);
    expect(find.descendant(of: find.byType(AppSheetRow), matching: find.text('English')), findsNothing);
    await tester.tap(find.text('Română').last);
    await tester.pumpAndSettle();

    // And the taught side, now that the pair is «en → ro» and Romanian is itself taught.
    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();
    expect(find.descendant(of: find.byType(AppSheetRow), matching: find.text('Română')), findsNothing);
    expect(find.descendant(of: find.byType(AppSheetRow), matching: find.text('Español')), findsOneWidget);
  });

  testWidgets('a new pair takes the OLD pair\'s results off the screen', (tester) async {
    // «invoice» found EN→RU is not an answer to the same word asked EN→RO. Leaving the old hits up
    // while the new ones are in flight is the one thing this control must never do.
    final api = _Api(hits: [
      const SearchHit(termId: 'T1', text: 'invoice', type: 'word', translation: 'накладная'),
    ]);
    await _pump(tester, api);
    await _type(tester, 'invoice');
    expect(find.text('накладная'), findsWidgets);

    api.hits = const [];
    await tester.tap(_swap);
    await tester.pump();

    expect(find.text('накладная'), findsNothing);
  });

  testWidgets('says nothing about detection, direction or languages beyond the two codes', (
    tester,
  ) async {
    await _pump(tester, _Api(hint: 'счёт'));
    await _type(tester, 'invoice');

    for (final word in ['язык', 'Язык', 'направлен', 'определ', 'перевод с']) {
      expect(find.textContaining(word), findsNothing);
    }
  });

  testWidgets('a server that cannot be reached costs the pill, never the screen', (tester) async {
    // No pill this session, and every request omits the pair — which the server reads as «the
    // learner's own». A search screen that refused to work because it could not draw a language
    // label would be trading the feature for the setting.
    final api = _Api(languages: null);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWithValue(AppDatabase.forTesting(NativeDatabase.memory())),
          apiClientProvider.overrideWithValue(_BrokenLanguages(api)),
          collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
        ],
        child: MaterialApp(
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru')],
          theme: buildAppTheme(),
          home: const SearchScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('EN'), findsNothing);
    expect(find.byType(TextField), findsOneWidget);
  });
}

/// The pair endpoint is down; everything else works.
class _BrokenLanguages implements ApiClient {
  _BrokenLanguages(this._inner);

  final _Api _inner;

  @override
  Future<SearchLanguages> searchLanguages() async => throw Exception('offline');

  @override
  Future<List<SearchHit>> search(String query, {int limit = 20, String? source, String? target}) =>
      _inner.search(query, limit: limit, source: source, target: target);

  @override
  Future<InstantHint> instantHint(String query, {String? source, String? target}) =>
      _inner.instantHint(query, source: source, target: target);

  @override
  Future<LookupOutcome> lookupWord(String query, {String? source, String? target}) =>
      _inner.lookupWord(query, source: source, target: target);

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
