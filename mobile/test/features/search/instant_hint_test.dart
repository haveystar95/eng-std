import 'dart:async';

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
import 'package:eng_std/theme/theme.dart';

/// The instant translation, after «Фаза 3» moved it INSIDE the field.
///
/// Its contract in one sentence: it appears when there is something to say, it never appears beside
/// a word it is not about, it can never produce an error the learner sees — and it is feedback about
/// the INPUT, so it is set smaller, italic and grey, and it never competes with the list of results.
class _Api implements ApiClient {
  _Api({this.hint, this.throwOnHint = false, this.holdLookup = false});

  final InstantHint? hint;
  final bool throwOnHint;

  /// Keeps the model call in flight, so the assembling frame can be observed.
  final bool holdLookup;
  final lookupGate = Completer<void>();

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
    if (holdLookup) await lookupGate.future;

    return const LookupOutcome(dailyCap: 5);
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> _pump(WidgetTester tester, _Api api) async {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(db.close);
  await tester.pumpWidget(ProviderScope(
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
  ));
  await tester.pump();
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
  testWidgets('the echo is the translation alone, set italic and grey', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', translation: 'значительный'));
    await _pump(tester, api);

    await _type(tester, 'significant');

    final echo = tester.widget<Text>(find.text('значительный'));
    expect(echo.style?.fontStyle, FontStyle.italic);
    expect(echo.style?.color, AppColors.tertiary);
    expect(api.hintCalls, 1);
  });

  testWidgets('it lives INSIDE the field, not on a line of its own', (tester) async {
    // The mockup's own summary: while somebody types, the only thing that leads anywhere is the
    // list of words. An instant gloss on its own line would sit at the same level as a result.
    final api = _Api(hint: const InstantHint(query: 'significant', translation: 'значительный'));
    await _pump(tester, api);

    await _type(tester, 'significant');

    final field = tester.getRect(find.byType(TextField));
    final echo = tester.getRect(find.text('значительный'));
    expect(echo.left, greaterThan(field.left), reason: 'it sits to the RIGHT of the input');
    expect(echo.center.dy, closeTo(field.center.dy, 12));
  });

  testWidgets('says nothing when the feature is off server-side', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', featureDisabled: true));
    await _pump(tester, api);

    await _type(tester, 'significant');

    // No key configured. Not an error, not a placeholder — nothing is drawn, and the rest of the
    // screen is untouched.
    expect(find.byType(TextField), findsOneWidget);
    expect(find.text('значительный'), findsNothing);
  });

  testWidgets('says nothing when the monthly budget is spent', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', limitReached: true));
    await _pump(tester, api);

    await _type(tester, 'significant');

    expect(find.text('значительный'), findsNothing);
  });

  testWidgets('a failed request is silent — never an error the learner sees', (tester) async {
    final api = _Api(throwOnHint: true);
    await _pump(tester, api);

    await _type(tester, 'significant');

    expect(tester.takeException(), isNull);
  });

  testWidgets('the echo never sits beside a word it is not about', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', translation: 'значительный'));
    await _pump(tester, api);

    await _type(tester, 'significant');
    expect(find.text('значительный'), findsOneWidget);

    // Typing on invalidates the answer immediately, without waiting for the next one to land.
    await tester.enterText(find.byType(TextField), 'significantl');
    await tester.pump();

    expect(find.text('значительный'), findsNothing);
  });

  testWidgets('submitting does not throw the answer away — кадр 05 shows it', (tester) async {
    // The guard is the WORD, not a counter. Keyed on the search's generation counter it looked
    // equivalent and was not: submit fires the hint first and the free search second, the search
    // bumps the counter, and the hint that arrived afterwards was discarded every time — which
    // left the assembling frame's «перевод» row empty with the answer already in hand.
    final api = _Api(
      hint: const InstantHint(query: 'significant', translation: 'значительный'),
      holdLookup: true,
    );
    await _pump(tester, api);

    await tester.enterText(find.byType(TextField), 'significant');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pump();
    await tester.pump();
    await tester.pump();

    await tester.tap(find.text('Собрать карточку'));
    await tester.pump();
    await tester.pump();

    // The one row the app CAN honestly tick before the model answers, because it already has it.
    expect(find.text('значительный'), findsOneWidget);

    api.lookupGate.complete();
    await tester.pumpAndSettle();
  });

  group('кадр 04 · слово, которого у нас ещё нет', () {
    Future<void> ask(WidgetTester tester, String word) async {
      await tester.enterText(find.byType(TextField), word);
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pump();
      await tester.pump();
      await tester.pump();
    }

    testWidgets('the translation is a RESULT here, set as the card sets it', (tester) async {
      // Not a draft, not a garnish, not a grey aside: the learner asked what a word means and has
      // been answered, free, in the time it took to press Enter. So it is typeset like an answer —
      // the term in the antiqua the word card uses, the translation under it in ink.
      await _pump(tester, _Api(hint: const InstantHint(query: 'root', translation: 'корень')));
      await ask(tester, 'root');

      // «root» is on screen twice — the field's own editable text and the headword. It is the
      // headword that has to be typeset like a card's term.
      expect(
        find.byWidgetPredicate(
          (w) => w is Text && w.data == 'root' && w.style?.fontSize == AppText.cardTerm.fontSize,
        ),
        findsOneWidget,
      );

      final translation = tester.widget<Text>(find.text('корень'));
      expect(translation.style?.fontSize, AppText.cardTranslation.fontSize);
      expect(translation.style?.color, AppColors.ink);
      expect(translation.style?.fontStyle, isNot(FontStyle.italic));
    });

    testWidgets('the word «черновой» is gone from the screen entirely', (tester) async {
      await _pump(tester, _Api(hint: const InstantHint(query: 'root', translation: 'корень')));
      await ask(tester, 'root');

      expect(find.textContaining('черновой'), findsNothing);
      expect(find.textContaining('Черновой'), findsNothing);
    });

    testWidgets('the button sells what is MISSING, not what was already given', (tester) async {
      await _pump(tester, _Api(hint: const InstantHint(query: 'root', translation: 'корень')));
      await ask(tester, 'root');

      expect(find.text('Собрать карточку'), findsOneWidget);
      expect(find.textContaining('Значение, пример и фото'), findsOneWidget);
    });

    testWidgets('no answer, no line — the frame does not reserve space for it', (tester) async {
      await _pump(tester, _Api(hint: const InstantHint(query: 'root')));
      await ask(tester, 'root');

      expect(find.text('root'), findsWidgets, reason: 'the term still stands');
      expect(find.text('Собрать карточку'), findsOneWidget);
    });
  });

  testWidgets('the echo never spends a model call by itself', (tester) async {
    final api = _Api(hint: const InstantHint(query: 'significant', translation: 'значительный'));
    await _pump(tester, api);

    await _type(tester, 'significant');

    // It is input feedback, not an offer. The paid call has exactly one door and it is «Find with
    // AI» on кадр 04.
    expect(api.lookupCalls, 0);
  });
}
