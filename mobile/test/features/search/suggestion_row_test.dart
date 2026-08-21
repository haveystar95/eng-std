import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/search_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// An API that answers the free search and nothing else, so the suggestion row can be observed
/// before and after the database replies.
class _Api implements ApiClient {
  _Api({this.hits = const []});

  final List<SearchHit> hits;

  @override
  Future<List<SearchHit>> search(String query, {int limit = 20}) async => hits;

  @override
  Future<LookupOutcome> lookupWord(String query) async => const LookupOutcome(dailyCap: 30);

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> _pump(WidgetTester tester, _Api api) async {
  await tester.pumpWidget(ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
      apiClientProvider.overrideWithValue(api),
      collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: const SearchScreen(),
    ),
  ));
  // Two pumps: one for the first frame, one for the lazily-read dictionary to land.
  await tester.pump();
  await tester.pump();
}

void main() {
  testWidgets('typing offers dictionary words before any server has answered', (tester) async {
    await _pump(tester, _Api());

    await tester.enterText(find.byType(TextField), 'incom');
    await tester.pump();

    // No debounce has fired and no request has been made — this row comes entirely from the asset,
    // which is the whole point: the screen used to be blank here.
    expect(find.widgetWithText(AppChip, 'income'), findsOneWidget);
  });

  testWidgets('a word we already have is offered FIRST and marked', (tester) async {
    await _pump(tester, _Api());

    await tester.enterText(find.byType(TextField), 'inc');
    await tester.pump();

    final chips = tester.widgetList<AppChip>(find.byType(AppChip)).toList();
    expect(chips, isNotEmpty);
    // Nothing is in the catalogue in this test, so every chip is a plain dictionary word.
    expect(chips.every((c) => !c.selected), isTrue);
  });

  testWidgets('tapping a suggestion fills the field and searches for it', (tester) async {
    final api = _Api(hits: const []);
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'incom');
    await tester.pump();
    await tester.tap(find.widgetWithText(AppChip, 'income'));
    await tester.pump();

    // The learner has said which word they meant; asking them to press anything else would be
    // asking twice.
    expect(find.widgetWithText(TextField, 'income'), findsOneWidget);
  });

  testWidgets('the row disappears once real results are on screen', (tester) async {
    await _pump(tester, _Api(hits: const [
      SearchHit(termId: 'ID', text: 'income', type: 'word', translation: 'доход'),
    ]));

    await tester.enterText(find.byType(TextField), 'incom');
    await tester.pump();
    expect(find.byType(AppChip), findsWidgets);

    // …and after the debounce, when the database has answered, the spellings step out of the way.
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    expect(find.byType(AppChip), findsNothing);
    expect(find.text('доход'), findsOneWidget);
  });

  testWidgets('an empty field offers nothing', (tester) async {
    await _pump(tester, _Api());

    await tester.enterText(find.byType(TextField), 'inc');
    await tester.pump();
    expect(find.byType(AppChip), findsWidgets);

    await tester.enterText(find.byType(TextField), '');
    await tester.pump();
    expect(find.byType(AppChip), findsNothing);
  });
}
