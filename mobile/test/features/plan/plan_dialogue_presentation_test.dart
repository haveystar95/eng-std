/// ПОДАЧА РАЗГОВОРА — наряд DAY-2-FIX, Ч.1.
///
/// Механика диалога работала уже после DAY-2; живой прогон владельца нашёл, что подача не работает:
/// два такта не различались с одного взгляда, часть пузырей молчала до тапа, свой ход появлялся в
/// ленте сам собой, а карточки, не попавшие в цепочку, тянули за собой всю ленту.
///
/// Здесь проверяется ровно это — что человек видит и что он должен сделать. Оценка, лестница и
/// раздача не трогались ни строкой и здесь не проверяются.
library;

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

void main() {
  const planId = '01PLAN';

  // Голос движка поднимается через канал `flutter_tts`; без заглушки посадка навсегда остаётся в
  // состоянии «Готовим озвучку», и проверять подачу было бы не на чем.
  const ttsChannel = MethodChannel('flutter_tts');

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      ttsChannel,
      (call) async => 1,
    );
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      ttsChannel,
      null,
    );
  });

  /// Сцена из четырёх ходов: два обмена. Ровно та форма, на которой живой прогон и споткнулся.
  const chain = PlanDialogue(
    dayIndex: 1,
    sceneTitle: 'Рассказ о прошлом опыте',
    sceneIntro: 'Онлайн-собеседование. Вас поприветствуют и попросят рассказать о себе.',
    turns: [
      PlanDialogueTurn(
        turn: 'role',
        termId: '01HEAR',
        text: 'Could you tell me about your background?',
        shelf: 'hear',
      ),
      PlanDialogueTurn(
        turn: 'you',
        termId: '01SAY',
        text: 'My background is in backend development.',
        shelf: 'say',
      ),
      PlanDialogueTurn(
        turn: 'role',
        termId: '01HEAR2',
        text: 'What are you working on now?',
        shelf: 'hear',
      ),
      PlanDialogueTurn(
        turn: 'you',
        termId: '01SAY2',
        text: "I'm building a learning app.",
        shelf: 'say',
      ),
    ],
  );

  SessionCard hearCard() => SessionCard(
    termId: '01HEAR',
    mode: ExerciseMode.situationalHear,
    type: 'phrase',
    prompt: 'Could you tell me about your background?',
    answer: 'Could you tell me about your background?',
    options: const ['Просят рассказать об опыте', 'Спрашивают, удобно ли время'],
  );

  SessionCard sayCard(String termId, String answer) => SessionCard(
    termId: termId,
    mode: ExerciseMode.situationalSay,
    type: 'phrase',
    answer: answer,
    options: [answer, 'See you tomorrow.'],
  );

  SessionCard askCard(String termId, String answer) => SessionCard(
    termId: termId,
    mode: ExerciseMode.situationalAsk,
    type: 'phrase',
    answer: answer,
    options: [answer, 'See you tomorrow.'],
  );

  PlanSessionTask task(
    SessionCard card, {
    String shelf = PlanTermRow.shelfSay,
    String section = PlanSessionTask.sectionCodeDialogue,
  }) => PlanSessionTask(
    card: card,
    stage: PlanStage.b,
    ordinal: 1,
    ofSteps: 1,
    fromDayIndex: 1,
    softened: false,
    section: PlanSessionTask.sectionReview,
    sectionCode: section,
    kind: 'line',
    shelf: shelf,
  );

  Widget host(List<PlanSessionTask> tasks, {List<String>? log}) => ProviderScope(
    overrides: [
      apiClientProvider.overrideWithValue(_PlanApi()),
      appDatabaseProvider.overrideWith((ref) {
        final database = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(database.close);
        return database;
      }),
      reviewSyncProvider.overrideWith((ref) => _RecordingReviewSync(ref, log ?? <String>[])),
      sessionCompletionSyncProvider.overrideWithValue(_SilentCompletion()),
      planSessionProvider.overrideWith(
        (ref, args) async => PlanSession(
          sessionId: args.sessionId,
          planId: planId,
          dayIndex: args.dayIndex ?? 2,
          strict: true,
          tasks: tasks,
          dialogues: const [chain],
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

  Future<void> open(WidgetTester tester, Widget tree) async {
    await tester.pumpWidget(tree);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Начать диалог'));
    await tester.pumpAndSettle();
  }

  Future<void> teardownTree(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  }

  testWidgets('Ч.1.1 — такт понимания спрашивает по-русски и крупно, без серой английской строки', (
    tester,
  ) async {
    await open(tester, host([task(hearCard(), shelf: PlanTermRow.shelfHear)]));

    expect(find.text('Что тебе сейчас сказали?'), findsOneWidget);
    // Служебная строка карточки внутри разговора исчезает: такт уже сказал, что делать.
    expect(find.textContaining('выбери, что он сказал'), findsNothing);
    // …и такт ответа с ней не путается, потому что вопрос другой.
    expect(find.text('Что ты ответишь?'), findsNothing);

    await teardownTree(tester);
  });

  testWidgets('Ч.1.1 — такт ответа спрашивает своё, и служебной строки под ним нет', (
    tester,
  ) async {
    await open(tester, host([task(sayCard('01SAY', 'My background is in backend development.'))]));
    expect(find.text('Что ты ответишь?'), findsOneWidget);
    expect(find.text('Что тебе сейчас сказали?'), findsNothing);
    expect(find.textContaining('выбери, что ответишь'), findsNothing);
    await teardownTree(tester);
  });

  testWidgets('Ч.1.1 — ход-вопрос спрашивает «Что ты спросишь?»', (tester) async {
    await open(
      tester,
      host([
        task(
          askCard('01SAY', 'My background is in backend development.'),
          shelf: PlanTermRow.shelfAsk,
        ),
      ]),
    );
    expect(find.text('Что ты спросишь?'), findsOneWidget);
    await teardownTree(tester);
  });

  testWidgets('Ч.1.3 — реплика такта понимания подаётся пузырём, а не кнопкой воспроизведения', (
    tester,
  ) async {
    await open(tester, host([task(hearCard(), shelf: PlanTermRow.shelfHear)]));

    // Тот же пузырь и та же кнопка повтора, что у реплики перед своим ходом.
    expect(find.textContaining('говорит собеседник'), findsOneWidget);
    expect(find.text('Ещё раз'), findsOneWidget);
    // Текст реплики скрыт, пока его не попросят, — и «Показать текст» живёт на пузыре.
    expect(find.text('Показать текст'), findsOneWidget);
    expect(find.text('Could you tell me about your background?'), findsNothing);
    // Своей кнопки воспроизведения у карточки внутри разговора больше нет.
    expect(find.text('Медленнее'), findsNothing);

    await teardownTree(tester);
  });

  testWidgets('Ч.1.4 — свой ход без карточки просят сказать вслух и не пишут ревью', (
    tester,
  ) async {
    final log = <String>[];
    // Посадка выдаёт только ВТОРОЙ свой ход; первый (01SAY) лестница сегодня не спрашивает.
    await open(tester, host([task(sayCard('01SAY2', "I'm building a learning app."))], log: log));

    // Ход не проматывается в ленту сам: экран останавливается на нём и отдаёт его человеку.
    expect(find.text('ВАШ ОТВЕТ'), findsOneWidget);
    expect(find.text('My background is in backend development.'), findsOneWidget);
    expect(find.textContaining('Мы не оцениваем'), findsOneWidget);
    // Пока ход не отдан, следующая реплика собеседника не звучит и такта ответа нет.
    expect(find.text('Что ты ответишь?'), findsNothing);

    await tester.tap(find.text('Сказал вслух'));
    await tester.pumpAndSettle();

    // Пузырь встал в ленту, разговор пошёл дальше — и ни одного ревью за это не написано.
    expect(find.text('Сказал вслух'), findsNothing);
    expect(find.text('Что ты ответишь?'), findsOneWidget);
    expect(log, isEmpty);

    await teardownTree(tester);
  });

  testWidgets('Ч.1.6 — карточка вне цепочки подаётся обычной, с вводкой и без ленты', (
    tester,
  ) async {
    // `01TAIL` в цепочке сцены не стоит вовсе — это `plan_day_dialogue_uncovered`, хвост.
    await open(
      tester,
      host([
        task(sayCard('01SAY2', "I'm building a learning app.")),
        task(sayCard('01TAIL', 'That experience is relevant.')),
      ]),
    );

    // Отдать первый ход, потом ответить на свой — и выйти из разговора в хвост.
    await tester.tap(find.text('Сказал вслух'));
    await tester.pumpAndSettle();
    await tester.tap(find.text("I'm building a learning app."));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();

    // Лента кончилась финалом цепочки — он приходит ДО хвоста, а не после него.
    expect(find.text('ИТОГ ПО СЦЕНЕ'), findsOneWidget);
    await tester.tap(find.text('Вернуться к сессии'));
    await tester.pumpAndSettle();

    // Хвост — обычная карточка с честной вводкой: ни пузырей, ни счётчика обменов.
    expect(find.text('ЕЩЁ РАЗ ОТВЕТ ЭТОЙ СЦЕНЫ'), findsOneWidget);
    expect(find.textContaining('говорит собеседник'), findsNothing);
    expect(find.textContaining('обмен'), findsNothing);
    expect(find.text('That experience is relevant.'), findsWidgets);

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

/// Записывает всё, что посадка сочла ответом. «Скажи вслух» обязан оставить его пустым.
class _RecordingReviewSync extends ReviewSync {
  _RecordingReviewSync(Ref ref, this.log)
    : super(
        ref.read(apiClientProvider),
        ref.read(reviewQueueProvider),
        ref.read(seqCounterProvider),
        ref,
      );

  final List<String> log;

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
  }) async {
    log.add('$termId/$exerciseMode');
  }

  @override
  Future<void> flush() async {}
}
