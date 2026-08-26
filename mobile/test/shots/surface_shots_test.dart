@Skip('Camera, not a test: run with --update-goldens to retake the shots (see below).')
library;

import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/app_settings.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/collection_detail_screen.dart';
import 'package:eng_std/features/collections/ladder_legend.dart';
import 'package:eng_std/features/collections/my_words_screen.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/word_card/word_card_screen.dart';
import 'package:eng_std/features/word_card/word_card_subject.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// SHOT HARNESS — renders the surfaces this наряд changed, with the app's own fonts, and writes
/// them to `docs/shots/input1/`.
///
/// WHAT THESE ARE, exactly: pixels produced by the real widgets, on the real theme, in the real
/// type. What they are NOT is a live pass — the data is stated here, not fetched, so nothing in
/// them proves the SERVER's half of anything. The one live shot of this наряд
/// (`ch3-home-words-cards.png`) was taken off the simulator against backend2 and is the evidence
/// for `session.cards`; these cover the client surfaces that the simulator panel died before
/// reaching.
///
/// Opt-in, and skipped by default, because a golden is machine-dependent and has no business
/// failing an ordinary `flutter test` run on somebody else's Mac. To produce the files:
///
/// ```bash
/// flutter test --update-goldens test/shots/surface_shots_test.dart
/// ```
///
/// (drop the `@Skip` line for that run, or delete this file once the shots are taken — it is a
/// camera, not a test.)
void main() {
  setUpAll(() async {
    final binding = TestWidgetsFlutterBinding.ensureInitialized();
    // The collection screen warms the synthesizer on entry (F20-r2). There is no engine here, so
    // the channel answers politely instead of throwing MissingPluginException into the shot.
    binding.defaultBinaryMessenger.setMockMethodCallHandler(
      const MethodChannel('flutter_tts'),
      (call) async => 1,
    );
    for (final family in [
      (AppFonts.literata, ['Literata-Regular.ttf', 'Literata-Italic.ttf', 'Literata-Medium.ttf']),
      (
        AppFonts.inter,
        ['Inter-Regular.ttf', 'Inter-SemiBold.ttf', 'Inter-Bold.ttf', 'Inter-ExtraBold.ttf'],
      ),
    ]) {
      final loader = FontLoader(family.$1);
      for (final file in family.$2) {
        loader.addFont(
          File('assets/fonts/$file').readAsBytes().then((b) => ByteData.sublistView(b)),
        );
      }
      await loader.load();
    }
    // The icon font too, or every control in the shot is an empty box. It ships inside the package
    // rather than in `assets/`, so it is resolved through the pub cache the same way the build does.
    final icons = FontLoader('packages/lucide_icons_flutter/Lucide')
      ..addFont(
        File(_lucideTtf).readAsBytes().then((b) => ByteData.sublistView(b)),
      );
    await icons.load();
  });

  /// A phone-shaped viewport, so the shots read like the screens they are.
  Future<void> phone(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1206, 2622);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
  }


  Future<void> shoot(WidgetTester tester, String name) async {
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../docs/shots/input1/$name'));
  }

  // ── Ч.1 — два действия и оба тоста ────────────────────────────────────────

  group('Ч.1 · переводчик', () {
    final subject = WordCardSubject(
      termId: 'T1',
      text: 'reimbursement',
      type: 'word',
      transcription: 'ˌriːɪmˈbɜːsmənt',
      translation: 'возмещение расходов',
      description: 'Money paid back to someone who has spent it on your behalf.',
      example: 'Submit the receipts and ask for reimbursement.',
      exampleTranslation: 'Приложи чеки и попроси возмещение.',
      cefr: 'B2',
    );

    testWidgets('ch1-two-actions', (tester) async {
      await phone(tester);
      await tester.pumpWidget(
        _cardApp(tester, WordCardScreen(subject: subject, onSpeak: () {})),
      );
      await tester.pump();
      await shoot(tester, 'ch1-two-actions.png');
    });

    for (final act in [(false, 'shelf'), (true, 'learning')]) {
      testWidgets('ch1-toast-${act.$2}', (tester) async {
        await phone(tester);
        final api = _ShotApi();
        await tester.pumpWidget(
          _cardApp(tester, WordCardScreen(subject: subject, onSpeak: () {}), api: api),
        );
        await tester.pump();

        await tester.tap(find.text(act.$1 ? 'Учить сразу' : '+ Сохранённые'));
        await tester.pump();
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 300));

        await shoot(tester, 'ch1-toast-${act.$2}.png');
      });
    }
  });

  // ── Ч.2 — подсказка про раскладку ─────────────────────────────────────────

  testWidgets('ch2-wrong-keyboard', (tester) async {
    await phone(tester);
    await tester.pumpWidget(
      _plainApp(
        tester,
        Scaffold(
          backgroundColor: AppColors.paper,
          body: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.screenH),
              child: SingleChildScrollView(
                child: SessionExerciseCard(
                  card: SessionCard(
                    termId: 'T1',
                    mode: ExerciseMode.typing,
                    type: 'phrase',
                    prompt: 'заранее',
                    answer: 'in advance',
                    example: 'Please book your seat in advance.',
                    ladderStep: LearningLadder.stepTyping,
                  ),
                  speechLocaleId: 'en_US',
                  answerLang: 'en',
                  autoPronounce: false,
                  onAnswered: (_) {},
                  onSpeak: (text, {bool slow = false}) async {},
                  showDue: false,
                ),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    // The keyboard left on Russian — «ФЕЬ» is what «in advance» looks like through it.
    await tester.enterText(find.byType(TextField), 'ФЕЬ');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pump();

    await shoot(tester, 'ch2-wrong-keyboard.png');
  });

  // ── Ч.4 — словарь статусов ────────────────────────────────────────────────

  testWidgets('ch4-collection-legend', (tester) async {
    await phone(tester);
    await tester.pumpWidget(
      _collectionApp(tester),
    );
    await tester.pumpAndSettle();
    await shoot(tester, 'ch4-collection-legend.png');
  });

  testWidgets('ch4-my-words', (tester) async {
    await phone(tester);
    await tester.pumpWidget(_poolApp(tester));
    await tester.pumpAndSettle();
    await shoot(tester, 'ch4-my-words.png');
  });

  testWidgets('ch4-ladder-legend', (tester) async {
    await phone(tester);
    await tester.pumpWidget(_poolApp(tester));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(ladderDotsLegendKey).first);
    await tester.pumpAndSettle();
    await shoot(tester, 'ch4-ladder-legend.png');
  });
}

// ── fixtures ────────────────────────────────────────────────────────────────

/// Where `lucide_icons_flutter` keeps its icon font inside the pub cache.
///
/// Found by scanning rather than pinned to a version, so a `pub upgrade` does not turn every
/// control in the shots back into an empty box.
String get _lucideTtf {
  final cache = Directory('${Platform.environment['HOME']}/.pub-cache/hosted/pub.dev');
  final package = cache.listSync().whereType<Directory>().firstWhere(
    (d) => d.path.split('/').last.startsWith('lucide_icons_flutter-'),
  );

  return '${package.path}/assets/lucide.ttf';
}

AppDatabase _memoryDb(WidgetTester tester) {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(db.close);

  return db;
}

class _ShotApi implements ApiClient {
  @override
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
    required bool enroll,
  }) async => const SavedSearchResult(
    termId: 'T1',
    collectionId: 'F',
    collectionTitle: 'Сохранённые',
    collectionIsDefault: true,
    added: true,
    enrolled: true,
  );

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// The app shell every shot is taken through — locale, deck, theme.
Widget _shell(Widget home) => MaterialApp(
  debugShowCheckedModeBanner: false,
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  theme: buildAppTheme(),
  home: home,
);

Widget _plainApp(WidgetTester tester, Widget home) => ProviderScope(
  overrides: [appDatabaseProvider.overrideWithValue(_memoryDb(tester))],
  child: _shell(home),
);

Widget _cardApp(WidgetTester tester, Widget home, {ApiClient? api}) => ProviderScope(
  overrides: [
    appDatabaseProvider.overrideWithValue(_memoryDb(tester)),
    apiClientProvider.overrideWithValue(api ?? _ShotApi()),
    collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
    transliterationEnabledProvider.overrideWithValue(false),
  ],
  child: _shell(home),
);

WordCollection _interview() => WordCollection(
  id: 'c1',
  title: 'Собеседование',
  source: 'ai',
  type: 'custom',
  wordsCount: 24,
  sourceLang: 'ru',
  targetLang: 'en',
);

Widget _collectionApp(WidgetTester tester) => ProviderScope(
  overrides: [
  appDatabaseProvider.overrideWithValue(_memoryDb(tester)),
  connectivityProvider.overrideWith((ref) => Stream.value(true)),
  collectionsProvider.overrideWith((ref) => Stream.value([_interview()])),
  collectionWordsProvider('c1').overrideWith(
    (ref) => Stream.value([
      Word(
        termId: 't1',
        term: 'cover letter',
        translation: 'сопроводительное письмо',
        type: 'phrase',
        enrolled: true,
        ladderStep: LearningLadder.stepAssembly,
      ),
      Word(
        termId: 't2',
        term: 'notice period',
        translation: 'срок отработки',
        type: 'phrase',
        enrolled: false,
      ),
      Word(
        termId: 't3',
        term: 'shortlist',
        translation: 'короткий список',
        type: 'word',
        enrolled: true,
        ladderStep: LearningLadder.stepRecognitionForward,
      ),
    ]),
  ),
  collectionDensityProvider(
    'c1',
  ).overrideWith((ref) => Stream.value(const CollectionDensity(mastered: 6, inWork: 11, toSort: 7))),
  collectionsProgressProvider.overrideWith(
    (ref) => Stream.value({
      'c1': CollectionProgress(collectionId: 'c1', total: 24, learned: 11, mastered: 6, due: 4),
    }),
  ),
  collectionCardCostProvider.overrideWith(
    (ref) => Stream.value({
      'c1': (due: 7, learn: 0),
    }),
  ),
  untriagedByCollectionProvider.overrideWith((ref) => Stream.value({'c1': 7})),
  learnableByCollectionProvider.overrideWith((ref) => Stream.value({'c1': 0})),
    statsProvider.overrideWith(
      (ref) => Stream.value(
        Stats(
          totalWords: 24,
          learned: 11,
          mastered: 6,
          dueToday: 4,
          reviewsTotal: 120,
          streakDays: 3,
          newGoal: 20,
          newRemaining: 20,
        ),
      ),
    ),
  ],
  child: _shell(const CollectionDetailScreen(collectionId: 'c1', title: 'Собеседование')),
);

PoolWordRow _poolRow(String id, String term, String tr, int step, {bool known = false}) =>
    PoolWordRow(
      term: Term(id: id, termText: term, translation: tr, type: 'word', updatedAt: DateTime(2026)),
      position: LadderPosition(
        acquisition: step <= LearningLadder.stepRecognitionReverse
            ? Acquisition.learning
            : Acquisition.graduated,
        learningStep: step,
        successfulReviews: step >= LearningLadder.stepDictation
            ? LearningLadder.dictationMinSuccesses
            : step >= LearningLadder.stepTyping
            ? LearningLadder.typingMinSuccesses
            : 0,
        isKnown: known,
        enrolled: true,
      ),
      collectionIds: const ['c1'],
      enrolledAt: DateTime(2026),
    );

Widget _poolApp(WidgetTester tester) => ProviderScope(
  overrides: [
  appDatabaseProvider.overrideWithValue(_memoryDb(tester)),
  collectionsProvider.overrideWith((ref) => Stream.value([_interview()])),
  poolProvider.overrideWith(
    (ref) => Stream.value([
      _poolRow('t1', 'cover letter', 'сопроводительное письмо', LearningLadder.stepAssembly),
      _poolRow('t2', 'shortlist', 'короткий список', LearningLadder.stepRecognitionForward),
      _poolRow('t3', 'notice period', 'срок отработки', LearningLadder.stepDictation),
      _poolRow('t4', 'severance', 'выходное пособие', LearningLadder.stepTyping),
      _poolRow('t5', 'headhunter', 'хедхантер', LearningLadder.stepIntro, known: true),
      ]),
    ),
  ],
  child: _shell(const MyWordsScreen()),
);
