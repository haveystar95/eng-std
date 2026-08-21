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

/// An API whose two searches are counted separately — which is the only thing this screen must
/// never get wrong. Typing is free and may run on a debounce; the model call costs money and must
/// happen ONLY on an explicit tap.
class _SpyApi implements ApiClient {
  _SpyApi({this.hits = const [], LookupOutcome? outcome})
      : outcome = outcome ?? const LookupOutcome(limitReached: false, dailyCap: 30);

  final List<SearchHit> hits;
  final LookupOutcome outcome;

  int searchCalls = 0;
  int lookupCalls = 0;

  @override
  Future<List<SearchHit>> search(String query, {int limit = 20}) async {
    searchCalls++;
    return hits;
  }

  @override
  Future<LookupOutcome> lookupWord(String query) async {
    lookupCalls++;
    return outcome;
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> _pump(WidgetTester tester, _SpyApi api) async {
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
}

SearchHit _hit() => const SearchHit(
      termId: '01KZETAAA50EMHCN6SP80T8DHC',
      text: 'invoice',
      type: 'word',
      translation: 'счёт',
      description: 'A paper that says how much money you must pay.',
    );

void main() {
  testWidgets('typing runs the FREE search and never the model', (tester) async {
    final api = _SpyApi(hits: [_hit()]);
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'invoice');
    await tester.pump(const Duration(milliseconds: 400)); // past the debounce
    await tester.pump();

    expect(api.searchCalls, 1);
    // The whole reason «Найти с ИИ» is a button: a debounce that generated would spend the daily
    // cap while the learner was still typing, and they would never see it happen.
    expect(api.lookupCalls, 0);
    // Asserted on the card's own content, not on the term: the search FIELD holds the word too, so
    // a text finder for it would pass even if no card had rendered at all.
    expect(find.text('A paper that says how much money you must pay.'), findsOneWidget);
  });

  testWidgets('the AI offer appears only when the database has nothing', (tester) async {
    final api = _SpyApi(hits: [_hit()]);
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'invoice');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    expect(find.text('Найти с ИИ'), findsNothing);
  });

  testWidgets('a miss offers the model — and only a tap spends it', (tester) async {
    final api = _SpyApi(
      hits: const [],
      outcome: LookupOutcome(
        card: const LookupCard(
          lookupId: '01KZETAAF37FWHW8WKDRGK71WN',
          text: 'reimbursement',
          type: 'word',
          translation: 'возмещение',
          description: 'Money you get back after you paid for something at work.',
        ),
        dailyCap: 30,
      ),
    );
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'reimbursement');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    expect(find.text('В базе такого нет'), findsOneWidget);
    expect(api.lookupCalls, 0);

    await tester.tap(find.text('Найти с ИИ'));
    await tester.pump();
    await tester.pump();

    expect(api.lookupCalls, 1);
    expect(find.text('Money you get back after you paid for something at work.'), findsOneWidget);
  });

  testWidgets('a spent daily cap is an honest line, not an error', (tester) async {
    final api = _SpyApi(
      hits: const [],
      outcome: const LookupOutcome(limitReached: true, dailyCap: 2, usedToday: 2),
    );
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'reimbursement');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();
    await tester.tap(find.text('Найти с ИИ'));
    await tester.pump();
    await tester.pump();

    // The screen says what happened and keeps the free half working — it does not throw the learner
    // onto an error path where the results it should be showing are not.
    expect(find.textContaining('лимит'), findsOneWidget);
    expect(find.byType(TextField), findsOneWidget);
  });
}
