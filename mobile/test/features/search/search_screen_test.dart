import 'dart:async';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/dictionary_row.dart';
import 'package:eng_std/features/search/search_history.dart';
import 'package:eng_std/features/search/search_result_card.dart';
import 'package:eng_std/features/search/search_screen.dart';
import 'package:eng_std/features/search/search_states.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The six faces of the search screen (кадры 01–05, 08), plus the one rule that outranks all of
/// them: typing is free and may run on a debounce, the model costs money and runs ONLY on a tap.
class _SpyApi implements ApiClient {
  _SpyApi({this.hits = const [], LookupOutcome? outcome, this.holdLookup = false})
      : outcome = outcome ?? const LookupOutcome(dailyCap: 5);

  final List<SearchHit> hits;
  final LookupOutcome outcome;

  /// Keeps the model call in flight so кадр 05 can be observed.
  final bool holdLookup;
  final lookupGate = Completer<void>();

  int searchCalls = 0;
  int lookupCalls = 0;

  @override
  Future<List<SearchHit>> search(String query, {int limit = 20}) async {
    searchCalls++;

    return hits;
  }

  @override
  Future<InstantHint> instantHint(String query) async => InstantHint(query: query);

  @override
  Future<LookupOutcome> lookupWord(String query) async {
    lookupCalls++;
    if (holdLookup) await lookupGate.future;

    return outcome;
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

late AppDatabase _db;

/// Opens the local mirror WITHOUT pumping, so a test can seed it (the search history lives there)
/// before the screen reads it on its first frame.
AppDatabase _open() {
  _db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(_db.close);

  return _db;
}

Future<void> _pump(WidgetTester tester, _SpyApi api, {AppDatabase? db}) async {
  final database = db ?? _open();
  await tester.pumpWidget(ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWithValue(database),
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
  // Three pumps: the first frame, the lazily-read dictionary, the history read.
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

Future<void> _submit(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField), text);
  await tester.testTextInput.receiveAction(TextInputAction.search);
  await tester.pump();
  await tester.pump();
  await tester.pump();
}

SearchHit _hit({String text = 'invoice', String? translation = 'счёт'}) => SearchHit(
      termId: 'ID-$text',
      text: text,
      type: 'word',
      transcription: 'ˈɪnvɔɪs',
      translation: translation,
      description: 'A paper that says how much money you must pay.',
      cefr: 'B1',
    );

void main() {
  group('кадр 01 · пустой поиск', () {
    testWidgets('says how big the database is, and what happens when a word is not in it',
        (tester) async {
      await _pump(tester, _SpyApi());

      expect(find.textContaining('В базе'), findsOneWidget);
      expect(find.textContaining('карточку соберёт модель'), findsOneWidget);
    });

    testWidgets('«Вы искали» lists what was searched before, and re-searching is one tap',
        (tester) async {
      final api = _SpyApi(hits: [_hit()]);
      final db = _open();
      await db.setMeta(SearchHistory.metaKey, '[{"w":"hollow","t":"пустой","c":"B2"}]');
      await _pump(tester, api, db: db);

      expect(find.text('Вы искали'.toUpperCase()), findsOneWidget);
      expect(find.text('hollow'), findsOneWidget);
      expect(find.text('пустой'), findsOneWidget);
      expect(find.text('B2'), findsOneWidget);

      await tester.tap(find.text('hollow'));
      await tester.pump();
      await tester.pump();
      expect(api.searchCalls, 1, reason: 'a recent line re-runs the search, it does not just fill the field');
    });

    testWidgets('a search is remembered', (tester) async {
      await _pump(tester, _SpyApi(hits: [_hit()]));
      await _submit(tester, 'invoice');

      final stored = await _db.getMeta(SearchHistory.metaKey);
      expect(stored, contains('invoice'));
    });
  });

  group('кадр 02 · набор', () {
    testWidgets('the words are ROWS now, not pills — and each carries its translation',
        (tester) async {
      await _pump(tester, _SpyApi(hits: [_hit()]));
      await _type(tester, 'invoice');

      expect(find.byType(DictionaryRow), findsWidgets);
      expect(find.text('счёт'), findsOneWidget);
      expect(find.text('Слова в базе'.toUpperCase()), findsOneWidget);
    });

    testWidgets('offers dictionary words before any server has answered', (tester) async {
      await _pump(tester, _SpyApi());

      await tester.enterText(find.byType(TextField), 'incom');
      await tester.pump();

      // No debounce has fired and no request has been made — this comes entirely from the asset.
      expect(find.widgetWithText(DictionaryRow, 'income'), findsOneWidget);
    });

    testWidgets('the screen still says how to ask for the whole word', (tester) async {
      await _pump(tester, _SpyApi());
      await _type(tester, 'holl');

      expect(find.textContaining('Enter'), findsOneWidget);
      expect(find.textContaining('holl'), findsWidgets);
    });

    testWidgets('typing never spends the model', (tester) async {
      final api = _SpyApi(hits: [_hit()]);
      await _pump(tester, api);
      await _type(tester, 'invoice');

      expect(api.searchCalls, 1);
      expect(api.lookupCalls, 0);
    });
  });

  group('кадр 03 · найдено в базе', () {
    testWidgets('the asked-for word becomes the one raised leaf', (tester) async {
      await _pump(tester, _SpyApi(hits: [_hit()]));
      await _submit(tester, 'invoice');

      expect(find.byType(SearchResultCard), findsOneWidget);
      expect(find.text('Открыть карточку'), findsOneWidget);
    });

    testWidgets('the other matches are demoted to flat lines under «Ещё в базе»', (tester) async {
      await _pump(tester, _SpyApi(hits: [
        _hit(),
        _hit(text: 'invoicing', translation: 'выставление счетов'),
      ]));
      await _submit(tester, 'invoice');

      expect(find.byType(SearchResultCard), findsOneWidget);
      expect(find.text('Ещё в базе'.toUpperCase()), findsOneWidget);
      expect(find.widgetWithText(DictionaryRow, 'invoicing'), findsOneWidget);
    });

    testWidgets('«Открыть карточку» pushes the word card', (tester) async {
      await _pump(tester, _SpyApi(hits: [_hit()]));
      await _submit(tester, 'invoice');

      await tester.tap(find.text('Открыть карточку'));
      await tester.pump();
      await tester.pump();
      await tester.pumpAndSettle();

      expect(find.byType(WordCardScreen), findsOneWidget);
    });
  });

  group('кадр 04 · нет в базе', () {
    testWidgets('names the missing word and offers the model — but only a tap spends it',
        (tester) async {
      final api = _SpyApi(hits: const []);
      await _pump(tester, api);
      await _submit(tester, 'reimbursement');

      expect(find.text('«reimbursement» ещё нет в базе'), findsOneWidget);
      expect(find.text('Find with AI'), findsOneWidget);
      expect(find.textContaining('Один вызов модели'), findsOneWidget);
      expect(api.lookupCalls, 0);
    });

    testWidgets('a near miss is «Похожее в базе», never the answer', (tester) async {
      // Deliberately not fuzzy: «invoicing» is not an answer to «invoic», and presenting it as one
      // would stop the learner generating the word they actually meant.
      await _pump(tester, _SpyApi(hits: [_hit(text: 'invoicing', translation: 'выставление счетов')]));
      await _submit(tester, 'invoic');

      expect(find.byType(SearchResultCard), findsNothing);
      expect(find.text('Похожее в базе'.toUpperCase()), findsOneWidget);
      expect(find.widgetWithText(DictionaryRow, 'invoicing'), findsOneWidget);
    });
  });

  group('кадр 05 · сборка', () {
    testWidgets('is a card writing itself, not a spinner', (tester) async {
      final api = _SpyApi(hits: const [], holdLookup: true);
      await _pump(tester, api);
      await _submit(tester, 'reimbursement');

      await tester.tap(find.text('Find with AI'));
      await tester.pump();

      expect(find.byType(AssemblingCard), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      // The word is already known and stands still; the checklist is what fills in.
      expect(find.text('reimbursement'), findsWidgets);
      expect(find.text('перевод'), findsOneWidget);
      expect(find.text('значение'), findsOneWidget);
      expect(find.text('пример'), findsOneWidget);

      api.lookupGate.complete();
      await tester.pumpAndSettle();
    });

    testWidgets('nothing is ticked before the answer lands', (tester) async {
      final api = _SpyApi(hits: const [], holdLookup: true);
      await _pump(tester, api);
      await _submit(tester, 'reimbursement');
      await tester.tap(find.text('Find with AI'));
      await tester.pump();

      // The wave has moved twice by now; with a single non-streaming call the app cannot know that
      // any row is DONE, so not one of them carries a tick.
      await tester.pump(AssemblingCard.step);
      await tester.pump(AssemblingCard.step);
      expect(find.byIcon(Icons.check), findsNothing);

      api.lookupGate.complete();
      await tester.pumpAndSettle();
    });

    testWidgets('a finished lookup opens the word card', (tester) async {
      final api = _SpyApi(
        hits: const [],
        outcome: const LookupOutcome(
          card: LookupCard(
            lookupId: '01KZETAAF37FWHW8WKDRGK71WN',
            text: 'reimbursement',
            type: 'word',
            translation: 'возмещение',
            description: 'Money you get back after you paid for something at work.',
          ),
          dailyCap: 5,
        ),
      );
      await _pump(tester, api);
      await _submit(tester, 'reimbursement');

      await tester.tap(find.text('Find with AI'));
      await tester.pumpAndSettle();

      expect(api.lookupCalls, 1);
      expect(find.byType(WordCardScreen), findsOneWidget);
      expect(find.text('Money you get back after you paid for something at work.'), findsOneWidget);
    });
  });

  group('кадр 08 · дневной лимит', () {
    testWidgets('states the fact in grey dots and says when the model comes back', (tester) async {
      final api = _SpyApi(
        hits: const [],
        outcome: const LookupOutcome(limitReached: true, dailyCap: 5, usedToday: 5),
      );
      await _pump(tester, api);
      await _submit(tester, 'reimbursement');
      await tester.tap(find.text('Find with AI'));
      await tester.pumpAndSettle();

      expect(find.byType(AiLimitCard), findsOneWidget);
      expect(find.text('5 из 5 на сегодня'), findsOneWidget);
      expect(find.text('Сборки с моделью вернутся в полночь'), findsOneWidget);
      expect(find.textContaining('Поиск по базе работает'), findsOneWidget);
    });

    testWidgets('promises nothing the app does not have', (tester) async {
      // Neither «Отложить на завтра» (there is no queue) nor a subscription line (there is no
      // subscription). A limit screen that offers either is a screen that lies.
      final api = _SpyApi(
        hits: const [],
        outcome: const LookupOutcome(limitReached: true, dailyCap: 5, usedToday: 5),
      );
      await _pump(tester, api);
      await _submit(tester, 'reimbursement');
      await tester.tap(find.text('Find with AI'));
      await tester.pumpAndSettle();

      expect(find.textContaining('Отложить'), findsNothing);
      expect(find.textContaining('подписк'), findsNothing);
    });
  });
}
