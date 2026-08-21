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

/// The grey line's contract in one sentence: it appears when there is something to say, it never
/// appears under a word it is not about, and it can never produce an error the learner sees.
class _Api implements ApiClient {
  _Api({this.hint, this.throwOnHint = false});

  final InstantHint? hint;
  final bool throwOnHint;

  int hintCalls = 0;
  int lookupCalls = 0;

  @override
  Future<List<SearchHit>> search(String query, {int limit = 20}) async => const [];

  @override
  Future<InstantHint> instantHint(String query) async {
    hintCalls++;
    if (throwOnHint) throw Exception('offline');

    return hint ?? InstantHint(query: query);
  }

  @override
  Future<LookupOutcome> lookupWord(String query) async {
    lookupCalls++;

    return const LookupOutcome(dailyCap: 30);
  }

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
  await tester.pump();
  await tester.pump();
}

Future<void> _type(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField), text);
  await tester.pump(const Duration(milliseconds: 400)); // past the debounce
  await tester.pump();
  await tester.pump();
}

void main() {
  testWidgets('shows «слово — перевод» once the answer arrives', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', translation: 'значительный'));
    await _pump(tester, api);

    await _type(tester, 'significant');

    expect(find.text('significant — значительный'), findsOneWidget);
    expect(api.hintCalls, 1);
  });

  testWidgets('says nothing when the feature is off server-side', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', featureDisabled: true));
    await _pump(tester, api);

    await _type(tester, 'significant');

    // No key configured. Not an error, not a placeholder — the line is simply not drawn, and the
    // rest of the screen is untouched.
    expect(find.textContaining('—'), findsNothing);
    expect(find.byType(TextField), findsOneWidget);
  });

  testWidgets('says nothing when the monthly budget is spent', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', limitReached: true));
    await _pump(tester, api);

    await _type(tester, 'significant');

    expect(find.textContaining('—'), findsNothing);
  });

  testWidgets('a failed request is silent — never an error the learner sees', (tester) async {
    final api = _Api(throwOnHint: true);
    await _pump(tester, api);

    await _type(tester, 'significant');

    expect(tester.takeException(), isNull);
    expect(find.textContaining('—'), findsNothing);
  });

  testWidgets('the line never sits under a word it is not about', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', translation: 'значительный'));
    await _pump(tester, api);

    await _type(tester, 'significant');
    expect(find.text('significant — значительный'), findsOneWidget);

    // Typing on invalidates the answer immediately, without waiting for the next one to land.
    await tester.enterText(find.byType(TextField), 'significantl');
    await tester.pump();

    expect(find.text('significant — значительный'), findsNothing);
  });

  testWidgets('tapping the line opens the full card', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', translation: 'значительный'));
    await _pump(tester, api);

    await _type(tester, 'significant');
    expect(api.lookupCalls, 0, reason: 'a hint must never spend the lookup by itself');

    await tester.tap(find.text('significant — значительный'));
    await tester.pump();
    await tester.pump();

    // The learner has just been told what the word means; «show me the rest» is the obvious next
    // question, and it is the same paid flow the «Найти с ИИ» button runs.
    expect(api.lookupCalls, 1);
  });
}
