import 'dart:async';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/search_result_card.dart';
import 'package:eng_std/features/search/search_screen.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// THE LIFE OF A CARD, as the search screen has to tell it (наряд A-4.1 Ч.4).
///
/// Three bugs came off the phone in one sitting (RU→ES, «привет»/hola) and all three were the same
/// omission: the screen knew only «нашли слово» and «не нашли», and worked out which by comparing
/// the query with the hit's own TEXT. That comparison is in the language being LEARNED, so a query
/// typed in the support language — the ordinary case for «как это по-испански» — never matched, and
/// the screen offered «Собрать карточку» for a word it was displaying on the same page. It went on
/// offering it after the card was built, and it offered nothing at all to a word whose collection
/// had been deleted.
///
/// Four states, and the build button belongs to exactly one:
///
///   а) no card                       → «Собрать карточку»
///   б) card, in collection(s)        → «Уже в коллекции „…"» + «Добавить в другую коллекцию»
///   в) card, in none (сирота)        → «Добавить в коллекцию»
///   г) straight after a build        → (в), without passing through (а) again
class _SpyApi implements ApiClient {
  _SpyApi({this.hits = const [], LookupOutcome? outcome, this.hint})
    : outcome = outcome ?? const LookupOutcome(dailyCap: 5);

  final List<SearchHit> hits;
  final LookupOutcome outcome;
  final String? hint;

  int lookupCalls = 0;
  int addCalls = 0;

  /// The `retry` flag as it arrived, per call — «я нажал ещё раз», which is what lets the server
  /// past a refusal it wrote seconds ago.
  final List<bool> retriesPerCall = [];
  String? addedTermId;
  String? addedLookupId;

  /// WHICH ACT the screen asked for — «Сохранить» (false) or «Учить сразу» (true). The two are
  /// different things, and a spy that dropped this would let either button pass for the other.
  bool? addedEnroll;

  @override
  Future<List<SearchHit>> search(
    String query, {
    int limit = 20,
    String? source,
    String? target,
    String? taughtSide,
  }) async => hits;

  @override
  Future<InstantHint> instantHint(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
  }) async => InstantHint(query: query, translation: hint);

  @override
  Future<LookupOutcome> lookupWord(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
    bool retry = false,
  }) async {
    lookupCalls++;
    retriesPerCall.add(retry);

    return outcome;
  }

  @override
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
    required bool enroll,
  }) async {
    addCalls++;
    addedTermId = termId;
    addedLookupId = lookupId;
    addedEnroll = enroll;

    return const SavedSearchResult(
      termId: 'ID-hola',
      collectionId: 'c1',
      collectionTitle: 'Испанский',
      collectionIsDefault: true,
      added: true,
      enrolled: true,
    );
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// A word the learner keeps: `hola`, glossed «привет», exactly as `/search` returns it when the
/// query was typed on the SUPPORT side and matched the translation column.
SearchHit _hola({List<SavedFolder> folders = const []}) => SearchHit(
  termId: 'ID-hola',
  text: 'hola',
  type: 'word',
  translation: 'привет',
  description: 'Un saludo.',
  cefr: 'A1',
  folders: folders,
);

final _collection = WordCollection(
  id: 'c1',
  title: 'Испанский',
  wordsCount: 3,
  type: 'custom',
  source: 'user',
  sourceLang: 'ru',
  targetLang: 'es',
  isDefault: true,
);

Future<void> _pump(WidgetTester tester, _SpyApi api) async {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(db.close);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(db),
        apiClientProvider.overrideWithValue(api),
        collectionsProvider.overrideWith((ref) => Stream.value([_collection])),
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

Future<void> _submit(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField), text);
  await tester.testTextInput.receiveAction(TextInputAction.search);
  await tester.pump();
  await tester.pump();
  await tester.pump();
}

void main() {
  group('а · карточки нет', () {
    testWidgets('the build button is the offer, and it is the only one', (tester) async {
      await _pump(tester, _SpyApi(hint: 'hola'));
      await _submit(tester, 'привет');

      expect(find.text('Собрать карточку'), findsOneWidget);
      expect(find.text('Добавить в коллекцию'), findsNothing);
      expect(find.byType(SearchResultCard), findsNothing);
    });
  });

  group('б · карточка есть и лежит в коллекции', () {
    testWidgets('states where the word lives and offers one more collection — never a build', (
      tester,
    ) async {
      final api = _SpyApi(
        hits: [
          _hola(folders: const [SavedFolder(id: 'c1', title: 'Испанский', isDefault: true)]),
        ],
        hint: 'hola',
      );
      await _pump(tester, api);
      await _submit(tester, 'привет');

      expect(find.byType(SearchResultCard), findsOneWidget);
      expect(find.text('Уже в коллекции «Испанский»'), findsOneWidget);
      expect(find.text('Добавить в другую коллекцию'), findsOneWidget);
      expect(
        find.text('Собрать карточку'),
        findsNothing,
        reason: 'the card exists — offering to build it says it does not',
      );
      expect(api.lookupCalls, 0);
    });
  });

  group('в · карточка есть, коллекций нет (сирота)', () {
    testWidgets('the word is shown, and putting it somewhere is the main action', (tester) async {
      await _pump(tester, _SpyApi(hits: [_hola()], hint: 'hola'));
      await _submit(tester, 'привет');

      expect(find.byType(SearchResultCard), findsOneWidget);
      expect(find.text('hola'), findsWidgets);
      expect(find.text('Добавить в коллекцию'), findsOneWidget);
      expect(
        find.text('Собрать карточку'),
        findsNothing,
        reason: 'a word survives its collection; there is nothing left to build',
      );
      expect(find.textContaining('Уже в коллекции'), findsNothing);
    });

    testWidgets('the sheet opens on the shelf and saves the term by id', (tester) async {
      final api = _SpyApi(hits: [_hola()], hint: 'hola');
      await _pump(tester, api);
      await _submit(tester, 'привет');

      await tester.tap(find.text('Добавить в коллекцию'));
      await tester.pumpAndSettle();

      // The sheet, with the pair's collections and the always-present «create one».
      expect(find.text('Испанский'), findsWidgets);
      await tester.tap(find.text('Испанский').last);
      await tester.pumpAndSettle();

      expect(api.addCalls, 1);
      expect(api.addedTermId, 'ID-hola', reason: 'an existing term is addressed by its id');
      expect(api.addedLookupId, isNull);
      // And the screen has moved to (б) without waiting for the free search to come back.
      expect(find.text('Уже в коллекции «Испанский»'), findsOneWidget);
      expect(find.text('Собрать карточку'), findsNothing);
    });
  });

  group('г · сразу после сборки', () {
    testWidgets('a built card does not offer to be built again', (tester) async {
      final api = _SpyApi(
        hint: 'hola',
        outcome: const LookupOutcome(
          card: LookupCard(
            lookupId: 'LK-1',
            text: 'hola',
            type: 'word',
            translation: 'привет',
            description: 'Un saludo.',
          ),
          dailyCap: 5,
          usedToday: 1,
        ),
      );
      await _pump(tester, api);
      await _submit(tester, 'привет');

      await tester.tap(find.text('Собрать карточку'));
      await tester.pumpAndSettle();

      // The card opened, as it always did.
      expect(find.byType(WordCardScreen), findsOneWidget);

      // Coming back, the screen is in (в): the word is built and belongs to nothing yet.
      tester.state<NavigatorState>(find.byType(Navigator).first).pop();
      await tester.pumpAndSettle();

      expect(find.byType(SearchResultCard), findsOneWidget);
      expect(
        find.text('Собрать карточку'),
        findsNothing,
        reason: 'the build succeeded — showing the button again denies that it happened',
      );
      expect(find.text('Добавить в коллекцию'), findsOneWidget);
      expect(api.lookupCalls, 1);
    });

    testWidgets('saving the built card from the search screen uses the lookup handle', (
      tester,
    ) async {
      final api = _SpyApi(
        hint: 'hola',
        outcome: const LookupOutcome(
          card: LookupCard(
            lookupId: 'LK-1',
            text: 'hola',
            type: 'word',
            translation: 'привет',
            description: 'Un saludo.',
          ),
          dailyCap: 5,
        ),
      );
      await _pump(tester, api);
      await _submit(tester, 'привет');
      await tester.tap(find.text('Собрать карточку'));
      await tester.pumpAndSettle();
      tester.state<NavigatorState>(find.byType(Navigator).first).pop();
      await tester.pumpAndSettle();

      await tester.tap(find.text('Добавить в коллекцию'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Испанский').last);
      await tester.pumpAndSettle();

      expect(api.addedLookupId, 'LK-1', reason: 'a word nobody saved yet has only its lookup');
      // «Добавить в коллекцию» is the SHELVING act: the word goes on a shelf and waits in the swipe
      // pass. It used to enrol silently, which is how a word filed for later became a debt.
      expect(api.addedEnroll, isFalse);
      expect(find.text('Уже в коллекции «Испанский»'), findsOneWidget);
    });

    testWidgets('«Учить сразу» sits beside it and asks for the queue', (tester) async {
      final api = _SpyApi(
        hint: 'hola',
        outcome: const LookupOutcome(
          card: LookupCard(
            lookupId: 'LK-1',
            text: 'hola',
            type: 'word',
            translation: 'привет',
            description: 'Un saludo.',
          ),
          dailyCap: 5,
        ),
      );
      await _pump(tester, api);
      await _submit(tester, 'привет');
      await tester.tap(find.text('Собрать карточку'));
      await tester.pumpAndSettle();
      tester.state<NavigatorState>(find.byType(Navigator).first).pop();
      await tester.pumpAndSettle();

      await tester.tap(find.text('Учить сразу'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Испанский').last);
      await tester.pumpAndSettle();

      expect(api.addedLookupId, 'LK-1');
      expect(api.addedEnroll, isTrue);
    });
  });

  group('the match itself', () {
    testWidgets('a query typed on the SUPPORT side finds its own card', (tester) async {
      // The bug under all three symptoms: `/search` answers «привет» with the term `hola`, and the
      // screen used to compare «привет» with `hola` and conclude there was nothing.
      await _pump(tester, _SpyApi(hits: [_hola()], hint: 'hola'));
      await _submit(tester, 'привет');

      expect(find.byType(SearchResultCard), findsOneWidget);
    });

    testWidgets('a near miss on either side is still «Похожие», never the answer', (tester) async {
      await _pump(
        tester,
        _SpyApi(
          hits: [
            SearchHit(termId: 'ID-holandes', text: 'holandés', type: 'word', translation: 'голландский'),
          ],
          hint: 'hola',
        ),
      );
      await _submit(tester, 'привет');

      expect(find.byType(SearchResultCard), findsNothing);
      expect(find.text('Собрать карточку'), findsOneWidget);
    });
  });

  group('повторный тап — это и есть «повторить» (решение архитектора 25.08)', () {
    // The model refused a word the learner knows exists. Pressing the button again is the retry:
    // the client says so, and the server goes past the verdict it wrote seconds ago instead of
    // re-serving it. Without the flag the second tap is answered from the cache, free and wrong.

    testWidgets('the first tap is not a retry; the one after a refusal is', (tester) async {
      final api = _SpyApi(
        hint: 'hola',
        outcome: const LookupOutcome(dailyCap: 5, usedToday: 1, notRecognized: true),
      );
      await _pump(tester, api);
      await _submit(tester, 'привет');

      await tester.tap(find.text('Собрать карточку'));
      await tester.pumpAndSettle();

      // The refusal is on screen, and the button is still there to press again.
      expect(find.textContaining('Не получилось распознать'), findsOneWidget);
      expect(api.retriesPerCall, [false]);

      await tester.tap(find.text('Собрать карточку'));
      await tester.pumpAndSettle();

      expect(api.retriesPerCall, [false, true]);
    });

    testWidgets('a fresh query is never a retry, whatever came before it', (tester) async {
      final api = _SpyApi(
        hint: 'hola',
        outcome: const LookupOutcome(dailyCap: 5, usedToday: 1, notRecognized: true),
      );
      await _pump(tester, api);
      await _submit(tester, 'привет');
      await tester.tap(find.text('Собрать карточку'));
      await tester.pumpAndSettle();

      // Another word entirely: the refusal was about «привет», and carrying the flag over would
      // spend a call to dispute a verdict nobody has been given yet.
      await _submit(tester, 'спасибо');
      await tester.tap(find.text('Собрать карточку'));
      await tester.pumpAndSettle();

      expect(api.retriesPerCall, [false, false]);
    });
  });
}
