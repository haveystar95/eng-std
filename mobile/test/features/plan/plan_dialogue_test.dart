import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/data/session_completion_sync.dart';
import 'package:eng_std/features/plan/plan_dialogue.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// THE SCENE IS PLAYED AS A CONVERSATION — наряд DAY-2, Ч.3, серия «Диалог v1».
///
/// The card machinery is untouched and deliberately so; what is under test is the SHELL around it:
/// the opening screen, the feed, the bubble that sounds before the learner's move, the rescue
/// button that never leaves, and the finale.
void main() {
  const planId = '01PLAN';

  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  SessionCard reply(String termId) => SessionCard(
    termId: termId,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: 'Вас спросили про опыт.',
    answer: 'My background is in backend development.',
    options: const [
      'My background is in backend development.',
      "I'm building a learning app.",
    ],
  );

  PlanSessionTask dialogueTask(SessionCard card) => PlanSessionTask(
    card: card,
    stage: PlanStage.b,
    ordinal: 1,
    ofSteps: 1,
    // Scene 1's conversation, dealt inside day 2 — «диалог по сцене открывается на следующий
    // календарный день после знакомства» (канон §10).
    fromDayIndex: 1,
    softened: false,
    section: PlanSessionTask.sectionReview,
    sectionCode: PlanSessionTask.sectionCodeDialogue,
    kind: 'line',
    shelf: PlanTermRow.shelfSay,
  );

  const chain = PlanDialogue(
    dayIndex: 1,
    sceneTitle: 'Рассказ о прошлом опыте',
    sceneIntro: 'Онлайн-собеседование. Вас поприветствуют и попросят рассказать о себе.',
    turns: [
      PlanDialogueTurn(
        turn: 'role',
        termId: '01HEAR',
        text: 'Could you tell me about your background?',
        translation: 'Расскажете о своём опыте?',
        shelf: 'hear',
      ),
      PlanDialogueTurn(
        turn: 'you',
        termId: '01SAY',
        text: 'My background is in backend development.',
        translation: 'Мой опыт — бэкенд-разработка.',
        shelf: 'say',
      ),
    ],
  );

  Widget host({
    required List<PlanSessionTask> tasks,
    List<PlanDialogue> dialogues = const [chain],
  }) => ProviderScope(
    overrides: [
      apiClientProvider.overrideWithValue(_PlanApi()),
      appDatabaseProvider.overrideWith((ref) {
        final database = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(database.close);
        return database;
      }),
      reviewSyncProvider.overrideWith((ref) => _SilentReviewSync(ref)),
      sessionCompletionSyncProvider.overrideWithValue(_SilentCompletion()),
      planSessionProvider.overrideWith(
        (ref, args) async => PlanSession(
          sessionId: args.sessionId,
          planId: planId,
          dayIndex: args.dayIndex ?? 2,
          strict: true,
          tasks: tasks,
          dialogues: dialogues,
          raw: const {'session_id': 'S', 'plan_id': planId, 'day_index': 2, 'strict': true},
        ).asStudySession(),
      ),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru')],
      home: SessionScreen(title: 'День 2', planId: planId, planDayIndex: 2, targetLang: 'en'),
    ),
  );

  Future<void> teardownTree(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  }

  testWidgets('opens the conversation on its own screen — the scene, not an exercise (DL·01)', (
    tester,
  ) async {
    await tester.pumpWidget(host(tasks: [dialogueTask(reply('01SAY'))]));
    await tester.pumpAndSettle();

    // The scene by name, its вводка, the honest warning about sound — and no card yet.
    expect(find.text('Рассказ о прошлом опыте'), findsOneWidget);
    expect(find.textContaining('со звуком'), findsOneWidget);
    expect(find.text('Начать диалог'), findsOneWidget);
    expect(find.text('My background is in backend development.'), findsNothing);

    await teardownTree(tester);
  });

  /// The shell on its own — the speech engine belongs to the session, so the widget takes «the
  /// voice is ready» and «say this» as inputs and can be driven without one.
  Widget shell({required bool voiceReady, required List<String> spoken}) => MaterialApp(
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru')],
    home: Scaffold(
      body: SingleChildScrollView(
        child: PlanDialogueShell(
          dialogue: chain,
          turnIndex: 1,
          speechLocaleId: 'en_US',
          voiceReady: voiceReady,
          onSpeak: spoken.add,
          card: const Text('card'),
        ),
      ),
    ),
  );

  testWidgets('plays the line before the move, with its text hidden until asked for (DL·02)', (
    tester,
  ) async {
    final spoken = <String>[];
    await tester.pumpWidget(shell(voiceReady: true, spoken: spoken));
    await tester.pumpAndSettle();

    // The bubble stands where its text would stand and says what it is doing — не тишина.
    expect(find.textContaining('говорит собеседник'), findsOneWidget);
    expect(find.text('Could you tell me about your background?'), findsNothing);
    // …and it has actually SOUNDED: «реплика звучит голосом, текста нет».
    expect(spoken, ['Could you tell me about your background?']);

    // «Показать текст» is available at any moment: it is a hint, not a step.
    await tester.tap(find.text('Показать текст'));
    await tester.pumpAndSettle();
    expect(find.text('Could you tell me about your background?'), findsOneWidget);
    expect(find.text('Скрыть текст'), findsOneWidget);

    // «Ещё раз» says it again — the same line, not a second one.
    await tester.tap(find.text('Ещё раз'));
    await tester.pumpAndSettle();
    expect(spoken, hasLength(2));
  });

  testWidgets('says nothing and offers nothing while the voice is not ready (DL·08)', (
    tester,
  ) async {
    // Канон §7: «никакая реплика не подаётся на слух, пока озвучка не готова» — and no countdown,
    // because the wait has no known length.
    final spoken = <String>[];
    await tester.pumpWidget(shell(voiceReady: false, spoken: spoken));
    await tester.pumpAndSettle();

    expect(find.text('Готовим озвучку'), findsOneWidget);
    expect(find.text('Показать текст'), findsNothing);
    expect(spoken, isEmpty);
  });

  testWidgets('keeps the rescue button on screen for the whole conversation (DL·09)', (
    tester,
  ) async {
    await tester.pumpWidget(
      host(
        tasks: [
          PlanSessionTask(
            card: SessionCard(
              termId: '01RESCUE',
              mode: ExerciseMode.multipleChoice,
              type: 'phrase',
              prompt: 'Повторите, пожалуйста.',
              answer: 'Could you repeat that, please?',
              options: const ['Could you repeat that, please?', 'See you tomorrow.'],
            ),
            stage: null,
            ordinal: 0,
            ofSteps: 0,
            fromDayIndex: 1,
            softened: false,
            section: PlanSessionTask.sectionWarmup,
            sectionCode: PlanSessionTask.sectionCodeWarmup,
            kind: 'line',
            shelf: PlanTermRow.shelfRescue,
          ),
          dialogueTask(reply('01SAY')),
        ],
      ),
    );
    await tester.pumpAndSettle();

    // Answer the warm-up card and step into the conversation.
    await tester.tap(find.text('Could you repeat that, please?'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Начать диалог'));
    await tester.pumpAndSettle();

    // КНОПКА, а не подпись: с наряда DAY-GATE-1 (доработка, п. 3) секция разогрева зовётся тем же
    // именем — это один и тот же набор, — и в шапке присеста оно тоже стоит. Проверяем ту, по
    // которой можно постучать.
    final rescueButton = find.ancestor(
      of: find.text('На всякий случай'),
      matching: find.byType(InkWell),
    );
    expect(rescueButton, findsOneWidget);

    await tester.tap(rescueButton);
    await tester.pumpAndSettle();

    // The panel names the phrase and says out loud that asking is not a mistake.
    expect(find.text('Could you repeat that, please?'), findsWidgets);
    expect(find.textContaining('не ошибка'), findsOneWidget);

    await tester.tap(find.text('Вернуться к диалогу'));
    await tester.pumpAndSettle();

    await teardownTree(tester);
  });

  testWidgets('ends the conversation with the feed and three facts, no percentage (DL·10)', (
    tester,
  ) async {
    await tester.pumpWidget(host(tasks: [dialogueTask(reply('01SAY'))]));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Начать диалог'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('My background is in backend development.'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();

    // The over-title is set in caps by [PlanLabel], like every надзаголовок of the series.
    expect(find.textContaining('ДИАЛОГ ПРОЙДЕН'), findsOneWidget);
    expect(find.text('ИТОГ ПО СЦЕНЕ'), findsOneWidget);
    // The whole conversation is in the feed — including the turn the sitting never asked for.
    expect(find.text('Could you tell me about your background?'), findsOneWidget);
    expect(find.text('Отвечал сам'), findsOneWidget);
    // Словами, без «N из M» (наряд DAY-FIX-2, Ч.5.6).
    expect(find.text('на все'), findsOneWidget);
    expect(find.text('1 из 1'), findsNothing);
    // «Разобрал реплику на слух» is absent, and that is the rule rather than an omission: the hear
    // card was not owed today, so there is no number to print and nothing is invented.
    expect(find.text('Разобрал реплику на слух'), findsNothing);
    // Not one percentage: «дизайн не обещает процентов, которых нет».
    expect(find.textContaining('%'), findsNothing);
    expect(find.text('Вернуться к сессии'), findsOneWidget);

    await teardownTree(tester);
  });

  testWidgets('falls back to the ordinary card when the chain never arrived', (tester) async {
    // An older server, or a scene whose chain the day lost: the sitting is played card by card,
    // exactly as it was before this наряд, rather than opening a conversation with nothing in it.
    await tester.pumpWidget(host(tasks: [dialogueTask(reply('01SAY'))], dialogues: const []));
    await tester.pumpAndSettle();

    expect(find.text('Начать диалог'), findsNothing);
    expect(find.text('My background is in backend development.'), findsWidgets);

    await teardownTree(tester);
  });
}

class _PlanApi implements ApiClient {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _SilentCompletion implements SessionCompletionSync {
  @override
  Future<void> record({required String sessionId, DateTime? endedAt}) async {}

  @override
  Future<void> flush() async {}

  @override
  Future<int> pendingCount() async => 0;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _SilentReviewSync extends ReviewSync {
  _SilentReviewSync(Ref ref)
    : super(
        ref.read(apiClientProvider),
        ref.read(reviewQueueProvider),
        ref.read(seqCounterProvider),
        ref,
      );

  @override
  Future<void> record({
    required String termId,
    required String exerciseMode,
    required String response,
    bool usedHint = false,
    bool isPractice = false,
    int? latencyMs,
    String? sessionId,
    int? ladderStep,
  }) async {}

  @override
  Future<void> flush() async {}
}
