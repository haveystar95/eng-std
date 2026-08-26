import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/app_settings.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/features/word_card/word_card_subject.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

/// The word card — кадры 06 (главный экран), 07 (сохранено), 09 (из папки).
///
/// One layout, three switches: the photo, the ladder strip, and the pair of actions at the bottom.
class _Api implements ApiClient {
  int addCalls = 0;
  String? lastCollectionId;

  /// Which ACT the card asked for, last time. The whole point of Ч.1 is that the card has two and
  /// says which — a fake that dropped this would let either button pass for the other.
  bool? lastEnroll;

  @override
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
    required bool enroll,
  }) async {
    addCalls++;
    lastCollectionId = collectionId;
    lastEnroll = enroll;

    return const SavedSearchResult(
      termId: 'ID',
      collectionId: 'FOLDER',
      collectionTitle: 'Сохранённые',
      collectionIsDefault: true,
      added: true,
      enrolled: true,
    );
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> _pump(
  WidgetTester tester, {
  required WordCardSubject subject,
  WordCardMode mode = WordCardMode.search,
  _Api? api,
  VoidCallback? onTrain,
  VoidCallback? onEnroll,
  VoidCallback? onUnenroll,
  bool showTransliteration = false,
  List<WordCollection> collections = const <WordCollection>[],
}) async {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(db.close);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(db),
        apiClientProvider.overrideWithValue(api ?? _Api()),
        collectionsProvider.overrideWith((ref) => Stream.value(collections)),
        // «Подсказка произношения» stated, not derived: these tests are about the CARD, and the
        // setting's own default (the learner's alphabet) is pinned in its own test.
        transliterationEnabledProvider.overrideWithValue(showTransliteration),
      ],
      child: MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru')],
        home: WordCardScreen(
          subject: subject,
          mode: mode,
          onSpeak: () {},
          onTrain: onTrain,
          onEnroll: onEnroll,
          onUnenroll: onUnenroll,
        ),
      ),
    ),
  );
  await tester.pump();
}

WordCardSubject _fillOut({String? imageUrl, List<SavedFolder> folders = const []}) =>
    WordCardSubject(
      termId: 'ID',
      text: 'fill out',
      type: 'phrase',
      transcription: 'fɪl aʊt',
      translation: 'заполнять (форму)',
      description: 'When you fill out a form, you write your information in the empty spaces.',
      example: 'Please fill out this application to proceed.',
      exampleTranslation: 'Пожалуйста, заполните эту заявку, чтобы продолжить.',
      cefr: 'B1',
      imageUrl: imageUrl,
      folders: folders,
    );

void main() {
  group('кадр 06 · главный экран', () {
    testWidgets('the article is a headword, a level line, a translation and two lifted leaves', (
      tester,
    ) async {
      await _pump(tester, subject: _fillOut(imageUrl: 'https://example.test/p.jpg'));

      expect(find.text('fill out'), findsWidgets);
      expect(find.text('/fɪl aʊt/'), findsOneWidget);
      expect(find.text('B1'), findsOneWidget);
      expect(find.text('заполнять (форму)'), findsOneWidget);
      expect(
        find.text('When you fill out a form, you write your information in the empty spaces.'),
        findsOneWidget,
      );
      expect(find.text('ПРИМЕР'), findsOneWidget);
      expect(find.text('Пожалуйста, заполните эту заявку, чтобы продолжить.'), findsOneWidget);
    });

    testWidgets('the definition is set in the language being LEARNED, in italic', (tester) async {
      await _pump(tester, subject: _fillOut(imageUrl: 'https://example.test/p.jpg'));

      final definition = tester.widget<Text>(
        find.text('When you fill out a form, you write your information in the empty spaces.'),
      );
      expect(definition.style?.fontStyle, FontStyle.italic);
      expect(definition.style?.fontFamily, AppFonts.literata);
    });

    testWidgets('the paper rides UP onto the photo — the term sits on the seam', (tester) async {
      await _pump(tester, subject: _fillOut(imageUrl: 'https://example.test/p.jpg'));

      // The one move that makes this «фото-герой» and not «фото, а под ним текст».
      expect(
        find.byWidgetPredicate(
          (w) => w is SizedBox && w.height == AppWordCard.heroHeight - AppWordCard.heroOverlap,
        ),
        findsOneWidget,
      );
    });

    testWidgets('the example carries the term picked out, never as flat text', (tester) async {
      await _pump(tester, subject: _fillOut());

      final rich = tester.widget<Text>(
        find.byWidgetPredicate(
          (w) =>
              w is Text &&
              w.textSpan != null &&
              w.textSpan!.toPlainText() == 'Please fill out this application to proceed.',
        ),
      );
      final spans = (rich.textSpan! as TextSpan).children!.cast<TextSpan>();
      expect(spans[1].text, 'fill out');
      expect(spans[1].style?.fontWeight, FontWeight.w500);
    });

    testWidgets('the pair of actions is a save and a folder picker, with one grey line under it', (
      tester,
    ) async {
      await _pump(tester, subject: _fillOut());

      expect(find.text('+ Сохранённые'), findsOneWidget);
      expect(find.text('Справа — выбрать другую коллекцию'), findsOneWidget);
    });

    testWidgets('a word with no photo degrades to a lower plate, not to a hole', (tester) async {
      await _pump(tester, subject: _fillOut());

      // The block shrinks and goes neutral; every other part of the composition is unchanged.
      expect(
        find.byWidgetPredicate(
          (w) => w is SizedBox && w.height == AppWordCard.heroHeightPlate - AppWordCard.heroOverlap,
        ),
        findsOneWidget,
      );
      expect(find.text('заполнять (форму)'), findsOneWidget);
      expect(find.text('+ Сохранённые'), findsOneWidget);
    });
  });

  /// Ч.2 — the three additive products of ядро v15 on the expanded card. All three obey one rule:
  /// present → a block, absent → NO block. An empty «также:» or a bare pair of brackets would be
  /// the card making a claim about the word that nobody made.
  group('ядро v15 · чтение, доп-переводы, синонимы', () {
    WordCardSubject knife({
      String? transliteration,
      List<String> translations = const [],
      List<String> synonyms = const [],
    }) => WordCardSubject(
      termId: 'ID',
      text: 'knife',
      type: 'word',
      transcription: 'naɪf',
      transliteration: transliteration,
      translation: 'нож',
      translations: translations,
      synonyms: synonyms,
      cefr: 'A2',
    );

    testWidgets('the reading stands under the term, beside the IPA, in brackets', (tester) async {
      await _pump(tester, subject: knife(transliteration: 'найф'), showTransliteration: true);

      expect(find.text('/naɪf/'), findsOneWidget); // IPA is untouched — the two coexist
      expect(find.text('[найф]'), findsOneWidget);
      expect(find.text('A2'), findsOneWidget); // and the level keeps its place on the same line
    });

    testWidgets('the reading is muted dictionary type, one step quieter than the IPA', (
      tester,
    ) async {
      await _pump(tester, subject: knife(transliteration: 'найф'), showTransliteration: true);

      final reading = tester.widget<Text>(find.text('[найф]'));
      expect(reading.style?.color, AppColors.tertiary);
      expect(reading.style?.fontFamily, AppFonts.inter);
      final ipa = tester.widget<Text>(find.text('/naɪf/'));
      expect(ipa.style?.color, AppColors.secondary);
    });

    testWidgets('the setting switched OFF removes the reading and leaves the IPA', (tester) async {
      await _pump(tester, subject: knife(transliteration: 'найф'));

      expect(find.text('[найф]'), findsNothing);
      expect(find.text('/naɪf/'), findsOneWidget);
      expect(find.text('нож'), findsOneWidget);
    });

    testWidgets('a word the server sent no reading for draws no brackets', (tester) async {
      await _pump(tester, subject: knife(), showTransliteration: true);

      expect(find.textContaining('['), findsNothing);
    });

    testWidgets('extra translations follow the primary one through « / », primary first', (
      tester,
    ) async {
      await _pump(
        tester,
        subject: WordCardSubject(
          termId: 'ID',
          text: 'purpose',
          type: 'word',
          translation: 'цель',
          translations: const ['цель', 'задача', 'назначение'],
        ),
      );

      expect(find.text('цель / задача / назначение'), findsOneWidget);
    });

    testWidgets('a one-reading word prints the translation exactly as before', (tester) async {
      await _pump(tester, subject: knife(translations: const ['нож']));

      expect(find.text('нож'), findsOneWidget);
    });

    testWidgets('an empty translations list falls back to the pinned translation', (tester) async {
      // The ordinary state of every term the станок has not been over. It is not an error and it
      // is not «no translation» — it is «one reading».
      await _pump(tester, subject: knife());

      expect(find.text('нож'), findsOneWidget);
    });

    testWidgets('synonyms are a «также: …» line under the translation', (tester) async {
      await _pump(tester, subject: knife(synonyms: const ['blade', 'dagger']));

      expect(find.text('также: blade, dagger'), findsOneWidget);
    });

    testWidgets('no synonyms — no block at all, not an empty one', (tester) async {
      await _pump(tester, subject: knife());

      expect(find.textContaining('также'), findsNothing);
    });
  });

  group('кадр 07 · сохранено', () {
    testWidgets('the button goes out to a STATE that names the folder — and the card stays open', (
      tester,
    ) async {
      final api = _Api();
      await _pump(tester, subject: _fillOut(), api: api);

      await tester.tap(find.text('+ Сохранённые'));
      await tester.pump();
      await tester.pump();

      expect(api.addCalls, 1);
      expect(api.lastCollectionId, isNull, reason: 'the one-tap save goes to the default folder');
      // Straight after the save the line reports what HAPPENED, the ACT included — «В коллекции»
      // is what a card opened later says instead (A-3, ч.3). The toast says the same sentence, so
      // the text is on screen twice by design.
      expect(
        find.descendant(
          of: find.byType(SavedStateLine),
          matching: find.text('Сохранено в «Сохранённые» · в очереди на разбор'),
        ),
        findsOneWidget,
      );
      expect(find.text('+ Сохранённые'), findsNothing);
      // The learner opened the card to READ it. A save that closed it would take the article away
      // at the exact moment they decided they wanted it.
      expect(find.byType(WordCardScreen), findsOneWidget);
      expect(find.text('заполнять (форму)'), findsOneWidget);
    });

    testWidgets('the second action moves under it as a line, and stays live', (tester) async {
      await _pump(
        tester,
        subject: _fillOut(
          folders: const [SavedFolder(id: 'FOLDER', title: 'Сохранённые', isDefault: true)],
        ),
      );

      expect(find.text('Добавить в другую коллекцию'), findsOneWidget);
      expect(find.text('Справа — выбрать другую коллекцию'), findsNothing);

      // Ink, not terracotta: adding a word to one more collection destroys nothing, and the delete
      // colour on the safest action read as a warning (QA-OBS-19).
      final link = tester.widget<Text>(find.text('Добавить в другую коллекцию'));
      expect(link.style?.color, AppColors.ink);
      expect(link.style?.color, isNot(AppColors.destructiveText));
    });

    /// Ч.1 — «полка ≠ очередь». The card offers TWO acts and the server is told which one was
    /// pressed; the confirmation names it in the app's own words for the two states.
    group('два действия · сохранить vs учить сразу', () {
      testWidgets('«+ Сохранённые» files the word and does NOT put it in the queue', (
        tester,
      ) async {
        final api = _Api();
        await _pump(tester, subject: _fillOut(), api: api);

        await tester.tap(find.text('+ Сохранённые'));
        await tester.pump();
        await tester.pump();

        expect(api.lastEnroll, isFalse);
        expect(
          find.descendant(
            of: find.byType(SavedStateLine),
            matching: find.text('Сохранено в «Сохранённые» · в очереди на разбор'),
          ),
          findsOneWidget,
        );
      });

      testWidgets('«Учить сразу» is offered beside it, quieter, and enrols', (tester) async {
        final api = _Api();
        await _pump(tester, subject: _fillOut(), api: api);

        expect(find.text('Учить сразу'), findsOneWidget);
        await tester.ensureVisible(find.text('Учить сразу'));
        await tester.pump();
        await tester.tap(find.text('Учить сразу'));
        await tester.pump();
        await tester.pump();

        expect(api.addCalls, 1);
        expect(api.lastEnroll, isTrue);
        expect(api.lastCollectionId, isNull, reason: 'it saves into the same default folder');
        expect(
          find.descendant(
            of: find.byType(SavedStateLine),
            matching: find.text('Сохранено в «Сохранённые» · учится'),
          ),
          findsOneWidget,
        );
      });

      testWidgets('the toast says the same sentence as the line, for both acts', (tester) async {
        // The toast is what reaches a learner whose eye is still on the article. It must not
        // contradict the line under the button, so both read off the same act.
        await _pump(tester, subject: _fillOut(), api: _Api());

        await tester.tap(find.text('+ Сохранённые'));
        await tester.pump();
        await tester.pump();

        expect(
          find.descendant(
            of: find.byType(SnackBar),
            matching: find.text('Сохранено в «Сохранённые» · в очереди на разбор'),
          ),
          findsOneWidget,
        );
      });

      testWidgets('adding an owned word to a SECOND collection never re-decides the queue', (
        tester,
      ) async {
        // «Добавить в другую коллекцию» is filing, and filing only: whether this word is being
        // studied was decided elsewhere, and a second shelf must not quietly re-make that decision.
        final api = _Api();
        await _pump(
          tester,
          subject: _fillOut(
            folders: const [SavedFolder(id: 'FOLDER', title: 'Сохранённые', isDefault: true)],
          ),
          api: api,
          collections: [
            WordCollection(
              id: 'BANK',
              title: 'Банк',
              source: 'user',
              type: 'custom',
              wordsCount: 0,
              sourceLang: 'ru',
              targetLang: 'en',
            ),
          ],
        );

        await tester.ensureVisible(find.text('Добавить в другую коллекцию'));
        await tester.pump();
        await tester.tap(find.text('Добавить в другую коллекцию'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Банк'));
        await tester.pumpAndSettle();

        expect(api.addCalls, 1);
        expect(api.lastCollectionId, 'BANK');
        expect(api.lastEnroll, isFalse);
      });
    });

    testWidgets('a word that arrives already saved opens in the saved state', (tester) async {
      await _pump(
        tester,
        subject: _fillOut(
          folders: const [SavedFolder(id: 'F', title: 'Мои находки', isDefault: false)],
        ),
      );

      expect(find.text('В коллекции «Мои находки»'), findsOneWidget);
    });
  });

  group('кадр 09 · открыто из папки', () {
    WordCardSubject fromFolder({int? step = 1, bool enrolled = true}) => WordCardSubject(
      termId: 'ID',
      text: 'hole',
      type: 'word',
      transcription: 'hoʊl',
      translation: 'дыра',
      description: 'This is a space or opening in a solid object or surface.',
      example: 'I found a hole in my shirt.',
      ladderStep: step,
      enrolled: enrolled,
      folders: const [SavedFolder(id: 'F', title: 'Сохранённые', isDefault: true)],
    );

    testWidgets('the ladder is cut in as a band under the head', (tester) async {
      await _pump(tester, subject: fromFolder(), mode: WordCardMode.folder);

      expect(find.text('ПРОГРЕСС СЛОВА'), findsOneWidget);
      expect(find.text('2 из 5'), findsOneWidget);
      expect(find.byType(LadderTrack), findsOneWidget);
      // The current rung is captioned in ink; the rest stay quiet.
      expect(find.text('узнавание'), findsOneWidget);
    });

    testWidgets('the main action becomes the training run, with the folder move as a line', (
      tester,
    ) async {
      var trained = 0;
      await _pump(
        tester,
        subject: fromFolder(),
        mode: WordCardMode.folder,
        onTrain: () => trained++,
      );

      expect(find.text('Тренировать слово'), findsOneWidget);
      expect(find.text('+ Сохранённые'), findsNothing);
      expect(find.text('Добавить в другую коллекцию'), findsOneWidget);

      await tester.ensureVisible(find.text('Тренировать слово'));
      await tester.pump();
      await tester.tap(find.text('Тренировать слово'));
      await tester.pumpAndSettle();
      expect(trained, 1);
    });

    testWidgets('a word still in the catalogue is offered the DECISION, not a drill', (
      tester,
    ) async {
      await _pump(
        tester,
        subject: fromFolder(step: 0, enrolled: false),
        mode: WordCardMode.folder,
        onEnroll: () {},
      );

      expect(find.text('Учить это слово'), findsOneWidget);
      expect(find.text('Тренировать слово'), findsNothing);
      // No ladder — it has not started one. And no sentence about being «in the catalogue» either:
      // the button says that by existing, and the note used to push the translation down the page
      // to repeat it.
      expect(find.byType(LadderTrack), findsNothing);
      expect(find.textContaining('каталоге'), findsNothing);
    });

    testWidgets('a word at rung 0 cannot be drilled, and the button says why', (tester) async {
      await _pump(tester, subject: fromFolder(step: 0), mode: WordCardMode.folder, onTrain: () {});

      final button = tester.widget<PrimaryButton>(find.byType(PrimaryButton));
      expect(button.enabled, isFalse);
      expect(
        find.text('Слово откроется для практики после знакомства с ним в учебной тренировке.'),
        findsOneWidget,
      );
    });

    testWidgets('tapping the disabled action starts nothing', (tester) async {
      var trained = 0;
      await _pump(
        tester,
        subject: fromFolder(step: 0),
        mode: WordCardMode.folder,
        onTrain: () => trained++,
      );

      await tester.tap(find.text('Тренировать слово'), warnIfMissed: false);
      await tester.pumpAndSettle();

      expect(trained, 0);
    });

    testWidgets('a «знаю» word is OUTSIDE the ladder, not at the bottom of it — and it trains', (
      tester,
    ) async {
      // Five pale dots would say «at the very beginning», which is the opposite of what «знаю»
      // means. A dash says it in one mark, and the training run stays available.
      var trained = 0;
      await _pump(
        tester,
        subject: WordCardSubject(
          termId: 'ID',
          text: 'hole',
          type: 'word',
          translation: 'дыра',
          ladderStep: null,
          isKnown: true,
          enrolled: true,
        ),
        mode: WordCardMode.folder,
        onTrain: () => trained++,
      );

      expect(find.byType(LadderTrack), findsNothing);
      expect(find.byType(LadderKnownDash), findsOneWidget);

      await tester.ensureVisible(find.text('Тренировать слово'));
      await tester.pump();
      await tester.tap(find.text('Тренировать слово'));
      await tester.pumpAndSettle();
      expect(trained, 1);
    });

    group('«Убрать из изучения»', () {
      Future<void> openMenu(WidgetTester tester) async {
        await tester.tap(find.bySemanticsLabel('Ещё'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Убрать из изучения'));
        await tester.pumpAndSettle();
      }

      testWidgets('is confirmed, and the confirmation says it is a PAUSE', (tester) async {
        // The word keeps its rung and its due date. Wording that reads as a delete is wording the
        // learner never presses.
        var unenrolled = 0;
        await _pump(
          tester,
          subject: fromFolder(),
          mode: WordCardMode.folder,
          onTrain: () {},
          onUnenroll: () => unenrolled++,
        );

        await openMenu(tester);
        expect(find.textContaining('Прогресс и история сохранятся'), findsOneWidget);

        await tester.tap(find.text('Убрать'));
        await tester.pumpAndSettle();
        expect(unenrolled, 1);
      });

      testWidgets('cancelling leaves the word in the pool', (tester) async {
        var unenrolled = 0;
        await _pump(
          tester,
          subject: fromFolder(),
          mode: WordCardMode.folder,
          onTrain: () {},
          onUnenroll: () => unenrolled++,
        );

        await openMenu(tester);
        await tester.tap(find.text('Отмена'));
        await tester.pumpAndSettle();

        expect(unenrolled, 0);
      });

      testWidgets('a word outside the pool has nothing to pause — no menu at all', (tester) async {
        await _pump(
          tester,
          subject: fromFolder(step: 0, enrolled: false),
          mode: WordCardMode.folder,
          onEnroll: () {},
          onUnenroll: () {},
        );

        expect(find.bySemanticsLabel('Ещё'), findsNothing);
      });
    });
  });
}
