import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/dictionary_row.dart';
import 'package:eng_std/features/search/search_screen.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The list under the field while somebody types (кадр 02).
///
/// It has TWO sources and they are not equals: a word from our own catalogue can show what it means
/// and go straight to its card, a word from the offline dictionary is only a spelling and still has
/// to be searched for. The row was pills before the «Фаза 3» reskin; the ordering rule survived it.
class _Api implements ApiClient {
  _Api({this.hits = const []});

  final List<SearchHit> hits;
  int searchCalls = 0;

  @override
  Future<List<SearchHit>> search(
    String query, {
    int limit = 20,
    String? source,
    String? target,
  }) async {
    searchCalls++;

    return hits;
  }

  @override
  Future<InstantHint> instantHint(String query, {String? source, String? target}) async =>
      InstantHint(query: query);

  @override
  Future<LookupOutcome> lookupWord(String query, {String? source, String? target}) async =>
      const LookupOutcome(dailyCap: 5);

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> _pump(WidgetTester tester, _Api api) async {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(db.close);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(db),
        apiClientProvider.overrideWithValue(api),
        collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
      ],
      child: MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru')],
        home: const SearchScreen(),
      ),
    ),
  );
  await tester.pump();
  await tester.pump();
  await tester.pump();
}

void main() {
  testWidgets('a word we already have is offered FIRST and carries its translation', (
    tester,
  ) async {
    await _pump(
      tester,
      _Api(
        hits: const [SearchHit(termId: 'ID', text: 'income', type: 'word', translation: 'доход')],
      ),
    );

    await tester.enterText(find.byType(TextField), 'incom');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    final rows = tester.widgetList<DictionaryRow>(find.byType(DictionaryRow)).toList();
    expect(rows.first.term, 'income');
    expect(rows.first.translation, 'доход');
    // Everything after it came from the asset, so it is a spelling and nothing more.
    expect(rows.skip(1).every((r) => r.translation == null), isTrue);
  });

  testWidgets('a catalogue row goes straight to the card; a dictionary row searches', (
    tester,
  ) async {
    final api = _Api(
      hits: const [SearchHit(termId: 'ID', text: 'income', type: 'word', translation: 'доход')],
    );
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'incom');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    // The catalogue row already showed what the word means — a second stop at a search result would
    // say nothing new.
    await tester.tap(find.widgetWithText(DictionaryRow, 'income'));
    await tester.pumpAndSettle();
    expect(find.byType(WordCardScreen), findsOneWidget);
  });

  testWidgets('tapping a dictionary spelling fills the field and asks for it', (tester) async {
    final api = _Api();
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'incom');
    await tester.pump();
    expect(api.searchCalls, 0);

    await tester.tap(find.widgetWithText(DictionaryRow, 'income'));
    await tester.pump();
    await tester.pump();

    // The learner has said which word they meant; asking them to press anything else would be
    // asking twice.
    expect(find.widgetWithText(TextField, 'income'), findsOneWidget);
    expect(api.searchCalls, 1);
  });

  testWidgets('an empty field offers nothing', (tester) async {
    await _pump(tester, _Api());

    await tester.enterText(find.byType(TextField), 'inc');
    await tester.pump();
    expect(find.byType(DictionaryRow), findsWidgets);

    await tester.enterText(find.byType(TextField), '');
    await tester.pump();
    expect(find.byType(DictionaryRow), findsNothing);
  });
}
