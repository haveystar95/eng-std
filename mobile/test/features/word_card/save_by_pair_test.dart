import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/search_pair.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/features/word_card/word_card_subject.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// «Одна коллекция — одна пара» as the SAVE path sees it (A-3, ч.2; DECISIONS пп. 81, 141, 142).
///
/// The rule is the server's — the client never re-implements the gate. What it does do is refuse to
/// OFFER a collection the gate would refuse, so the learner is not sent down a road that ends in a
/// refusal; and when a refusal arrives anyway (a mirror the server has moved past), say so in words
/// and offer the only way out.
class _Api implements ApiClient {
  _Api({this.mismatch});

  /// `(expected, actual)` — make `/search/add` answer 422 `term_language_mismatch` instead of saving.
  final ({String expected, String actual})? mismatch;

  int addCalls = 0;
  String? lastCollectionId;
  final List<({String title, String? source, String? target})> created = [];

  @override
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
  }) async {
    addCalls++;
    lastCollectionId = collectionId;
    // The refusal lands only on a collection that already existed; one just created for the word's
    // own pair takes it, which is what makes the offered way out an actual way out.
    if (mismatch != null && collectionId != 'MADE') {
      throw DioException(
        requestOptions: RequestOptions(path: '/search/add'),
        response: Response<Object>(
          requestOptions: RequestOptions(path: '/search/add'),
          statusCode: 422,
          data: {
            'code': 'term_language_mismatch',
            'meta': {'expected_lang': mismatch!.expected, 'actual_lang': mismatch!.actual},
          },
        ),
      );
    }

    return SavedSearchResult(
      termId: 'ID',
      collectionId: collectionId ?? 'DEFAULT',
      collectionTitle: collectionId == 'MADE' ? 'Polski → Русский' : 'Сохранённые',
      collectionIsDefault: collectionId == null,
      added: true,
      enrolled: true,
    );
  }

  @override
  Future<WordCollection> createCollection({
    required String title,
    String? sourceLang,
    String? targetLang,
  }) async {
    created.add((title: title, source: sourceLang, target: targetLang));

    return WordCollection(
      id: 'MADE',
      title: title,
      source: 'user',
      type: 'custom',
      wordsCount: 0,
      sourceLang: sourceLang ?? 'ru',
      targetLang: targetLang ?? 'en',
    );
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

WordCollection _collection(
  String id,
  String title, {
  required String source,
  required String target,
  bool isDefault = false,
}) => WordCollection(
  id: id,
  title: title,
  source: 'user',
  type: 'custom',
  wordsCount: 0,
  sourceLang: source,
  targetLang: target,
  isDefault: isDefault,
);

Future<void> _pump(
  WidgetTester tester, {
  required _Api api,
  LearningPair? pair = const LearningPair(learned: 'en', support: 'ru'),
  List<WordCollection> collections = const [],
  List<SavedFolder> folders = const [],
}) async {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(db.close);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(db),
        apiClientProvider.overrideWithValue(api),
        collectionsProvider.overrideWith((ref) => Stream.value(collections)),
      ],
      child: MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru')],
        theme: buildAppTheme(),
        home: WordCardScreen(
          subject: WordCardSubject(
            termId: 'ID',
            text: 'invoice',
            type: 'word',
            translation: 'счёт',
            folders: folders,
          ),
          pair: pair,
          onSpeak: () {},
        ),
      ),
    ),
  );
  // Twice: the collections mirror is a stream, and its first emission lands after the first frame.
  await tester.pump();
  await tester.pump();
}

Future<void> _openSheet(WidgetTester tester) async {
  await tester.tap(find.bySemanticsLabel('Добавить в коллекцию'));
  await tester.pumpAndSettle();
}

void main() {
  group('шит выбора коллекции', () {
    testWidgets('offers the collections of the lookup pair and NOT the others', (tester) async {
      final api = _Api();
      await _pump(
        tester,
        api: api,
        collections: [
          _collection('A', 'Работа', source: 'ru', target: 'en'),
          _collection('B', 'Praca', source: 'ru', target: 'pl'),
          _collection('C', 'Munca', source: 'ro', target: 'en'),
        ],
      );

      await _openSheet(tester);

      expect(find.text('Работа'), findsOneWidget);
      // Not greyed — ABSENT. A collection that cannot take this word is not a choice the learner
      // has to reason about; the Polish one differs by the studied language, the Romanian one by
      // the support language, and neither could show this word's translation.
      expect(find.text('Praca'), findsNothing);
      expect(find.text('Munca'), findsNothing);
    });

    testWidgets('a collection that already holds the word is shown, inert, and says so', (
      tester,
    ) async {
      final api = _Api();
      await _pump(
        tester,
        api: api,
        collections: [_collection('A', 'Работа', source: 'ru', target: 'en')],
        folders: const [SavedFolder(id: 'A', title: 'Работа', isDefault: false)],
      );

      // The word is already in a collection, so the card is in its saved state and the way to the
      // sheet is the quiet line under it (кадр 07).
      await tester.tap(find.text('Добавить в другую коллекцию'));
      await tester.pumpAndSettle();
      expect(find.text('Уже в коллекции «Работа»'), findsOneWidget);

      await tester.tap(find.text('Уже в коллекции «Работа»'));
      await tester.pumpAndSettle();
      expect(api.addCalls, 0, reason: 'a tap on it must not spend a round trip');
    });

    testWidgets('«создать коллекцию» is always there, names the pair and is born in it', (
      tester,
    ) async {
      final api = _Api();
      await _pump(tester, api: api, collections: const []);

      await _openSheet(tester);
      // The learner's first word in this pair: the list above is empty and this is the way forward.
      expect(find.text('Новая коллекция · English → Русский'), findsOneWidget);

      await tester.tap(find.text('Новая коллекция · English → Русский'));
      await tester.pumpAndSettle();

      // The suggested name is the pair, and it is editable — this is a text field, not a label.
      expect(find.text('English → Русский'), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'Счета');
      await tester.tap(find.text('Сохранить'));
      await tester.pumpAndSettle();

      expect(api.created, [(title: 'Счета', source: 'ru', target: 'en')]);
      // …and the word goes straight in, without a second trip through the sheet.
      expect(api.lastCollectionId, 'MADE');
    });

    testWidgets('without a stated pair nothing is filtered — the sheet is what it always was', (
      tester,
    ) async {
      // The card opened from a folder, or the pill not yet loaded. The server's gate is still the
      // truth; this filter only spares the learner a refusal it can see coming.
      final api = _Api();
      await _pump(
        tester,
        api: api,
        pair: null,
        collections: [
          _collection('A', 'Работа', source: 'ru', target: 'en'),
          _collection('B', 'Praca', source: 'ru', target: 'pl'),
        ],
      );

      await _openSheet(tester);

      expect(find.text('Работа'), findsOneWidget);
      expect(find.text('Praca'), findsOneWidget);
      expect(find.text('Новая коллекция'), findsOneWidget);
    });
  });

  group('«Сохранённые»', () {
    testWidgets('one tap saves into it while it matches the pair', (tester) async {
      final api = _Api();
      await _pump(
        tester,
        api: api,
        collections: [_collection('D', 'Сохранённые', source: 'ru', target: 'en', isDefault: true)],
      );

      expect(find.text('+ Сохранённые'), findsOneWidget);
      await tester.tap(find.text('+ Сохранённые'));
      await tester.pump();
      await tester.pump();

      expect(api.lastCollectionId, isNull, reason: 'null means «the default folder», server-side');
    });

    testWidgets('a default of another pair is not offered at all, and the screen says why', (
      tester,
    ) async {
      final api = _Api();
      await _pump(
        tester,
        api: api,
        collections: [_collection('D', 'Сохранённые', source: 'ru', target: 'pl', isDefault: true)],
      );

      // The one-tap save would be refused by the server, so it is not offered: choosing — or
      // making — a collection of this pair becomes the main action instead.
      expect(find.text('+ Сохранённые'), findsNothing);
      expect(
        find.text(
          '«Сохранённые» — коллекция другой пары. '
          'Выберите коллекцию этой пары или создайте новую.',
        ),
        findsOneWidget,
      );
      expect(api.addCalls, 0);
    });

    testWidgets('no default yet: the one tap stands, because the server makes it in this pair', (
      tester,
    ) async {
      final api = _Api();
      await _pump(tester, api: api, collections: const []);

      expect(find.text('+ Сохранённые'), findsOneWidget);
    });
  });

  group('422 term_language_mismatch', () {
    testWidgets('is told in words, with both languages named, and a way out offered', (
      tester,
    ) async {
      // The race the filter cannot close: the mirror still shows a collection the server has since
      // moved to another pair.
      final api = _Api(mismatch: (expected: 'pl', actual: 'en'));
      await _pump(
        tester,
        api: api,
        collections: [_collection('A', 'Работа', source: 'ru', target: 'en')],
      );

      await _openSheet(tester);
      await tester.tap(find.text('Работа'));
      await tester.pumpAndSettle();

      expect(find.text('Слово другого языка'), findsOneWidget);
      expect(
        find.text(
          'Эта коллекция изучает Polski, а слово — на English. '
          'Одна коллекция — одна пара, поэтому нужна коллекция другой пары.',
        ),
        findsOneWidget,
      );
      expect(find.text('Создать коллекцию'), findsOneWidget);
    });

    testWidgets('the offered way out makes a collection of the WORD\'s pair and saves into it', (
      tester,
    ) async {
      final api = _Api(mismatch: (expected: 'pl', actual: 'en'));
      await _pump(
        tester,
        api: api,
        collections: [_collection('A', 'Работа', source: 'ru', target: 'en')],
      );

      await _openSheet(tester);
      await tester.tap(find.text('Работа'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Создать коллекцию'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Сохранить'));
      await tester.pumpAndSettle();

      // `actual_lang` came from the server and is the only half this refusal is certain about; the
      // support side is the pill's.
      expect(api.created, [(title: 'English → Русский', source: 'ru', target: 'en')]);
      expect(api.lastCollectionId, 'MADE');
      expect(find.text('Сохранено в коллекцию «Polski → Русский» — слово учится'), findsOneWidget);
    });

    testWidgets('a refusal is never silent — declining it leaves the card as it was', (
      tester,
    ) async {
      final api = _Api(mismatch: (expected: 'pl', actual: 'en'));
      await _pump(
        tester,
        api: api,
        collections: [_collection('A', 'Работа', source: 'ru', target: 'en')],
      );

      await _openSheet(tester);
      await tester.tap(find.text('Работа'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Отмена'));
      await tester.pumpAndSettle();

      expect(api.created, isEmpty);
      expect(find.textContaining('Сохранено в коллекцию'), findsNothing);
      expect(find.text('+ Сохранённые'), findsOneWidget);
    });
  });
}
