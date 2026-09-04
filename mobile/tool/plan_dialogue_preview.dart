import 'package:drift/native.dart';
import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// РАЗГОВОР СЦЕНЫ — серия «Диалог v1», на образцовой посадке.
///
/// Экран настоящий: это тот же [SessionScreen], те же ситуационные карточки и та же оболочка
/// диалога, что играют живой день. Подменена ровно одна вещь — ответ сервера, и это единственный
/// способ увидеть ВСЕ состояния такта подряд: чтобы дойти до них живьём, надо ответить полсотни
/// карточек знакомства, а чтобы увидеть «Скажи вслух» и хвостовую карточку — ещё и попасть в
/// раскладку, где лестница часть ходов сегодня не спрашивает.
///
/// Реплики — из фикстуры, а не из живой генерации: наряд DAY-2-FIX проверяет ПОДАЧУ, и текст
/// реплики к ней отношения не имеет.
///
///     flutter run -d <simulator> --target tool/plan_dialogue_preview.dart
void main() {
  runApp(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(_SilentApi()),
        appDatabaseProvider.overrideWith((ref) {
          final database = AppDatabase.forTesting(NativeDatabase.memory());
          ref.onDispose(database.close);
          return database;
        }),
        planSessionProvider.overrideWith((ref, args) async => _session(args.sessionId)),
      ],
      child: const _DialoguePreviewApp(),
    ),
  );
}

class _DialoguePreviewApp extends StatelessWidget {
  const _DialoguePreviewApp();

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    home: const SessionScreen(
      title: 'День 2',
      planId: '01PLAN',
      planDayIndex: 2,
      targetLang: 'en',
    ),
  );
}

/// Сцена 1 из четырёх обменов. Ход 5 («Sure, happy to.») карточки сегодня НЕ имеет — его человек
/// просто говорит вслух (Ч.1.4); хвостовая карточка в цепочке не стоит вовсе (Ч.1.6).
const _chain = PlanDialogue(
  dayIndex: 1,
  sceneTitle: 'Рассказ о прошлом опыте',
  sceneIntro:
      'Онлайн-собеседование. Вас поприветствуют и попросят рассказать о себе и о том, чем вы '
      'заняты сейчас. Реплики звучат голосом — текста не будет, пока сами не откроете.',
  turns: [
    PlanDialogueTurn(
      turn: 'role',
      termId: 'H1',
      text: 'Hi, thanks for joining today.',
      shelf: 'hear',
    ),
    PlanDialogueTurn(turn: 'you', termId: 'S1', text: 'Sure, happy to.', shelf: 'say'),
    PlanDialogueTurn(
      turn: 'role',
      termId: 'H2',
      text: 'Could you tell me about your background?',
      shelf: 'hear',
    ),
    PlanDialogueTurn(
      turn: 'you',
      termId: 'S2',
      text: 'My background is in backend development.',
      shelf: 'say',
    ),
    PlanDialogueTurn(
      turn: 'role',
      termId: 'H3',
      text: 'What are you working on now?',
      shelf: 'hear',
    ),
    PlanDialogueTurn(
      turn: 'you',
      termId: 'S3',
      text: "I'm building a learning app.",
      shelf: 'say',
    ),
  ],
);

StudySession _session(String sessionId) => PlanSession(
  sessionId: sessionId,
  planId: '01PLAN',
  dayIndex: 2,
  strict: true,
  dialogues: const [_chain],
  tasks: [
    // Такт понимания — реплика пузырём, три смысла по-русски (кадр DL·02).
    _task(
      SessionCard(
        termId: 'H2',
        mode: ExerciseMode.situationalHear,
        type: 'phrase',
        prompt: 'Could you tell me about your background?',
        answer: 'Could you tell me about your background?',
        options: const [
          'Просят рассказать о вашем опыте',
          'Спрашивают, удобно ли вам время',
          'Просят подождать на линии',
        ],
      ),
      shelf: PlanTermRow.shelfHear,
    ),
    // Такт ответа — три реальные фразы плана (кадр DL·03).
    _task(
      SessionCard(
        termId: 'S2',
        mode: ExerciseMode.situationalSay,
        type: 'phrase',
        answer: 'My background is in backend development.',
        options: const [
          'My background is in backend development.',
          "I'm building a learning app.",
          'Sure, happy to.',
        ],
      ),
    ),
    // …и карточка, которой в цепочке нет вовсе — хвост сцены (кадр вне ленты, Ч.1.6).
    _task(
      SessionCard(
        termId: 'TAIL',
        mode: ExerciseMode.situationalAsk,
        type: 'phrase',
        answer: 'Could you tell me more about the team?',
        options: const [
          'Could you tell me more about the team?',
          'What does the onboarding look like?',
        ],
      ),
      shelf: PlanTermRow.shelfAsk,
    ),
  ],
  raw: const {'session_id': 'S', 'plan_id': '01PLAN', 'day_index': 2, 'strict': true},
).asStudySession();

PlanSessionTask _task(SessionCard card, {String shelf = PlanTermRow.shelfSay}) => PlanSessionTask(
  card: card,
  stage: PlanStage.b,
  ordinal: 1,
  ofSteps: 1,
  fromDayIndex: 1,
  softened: false,
  section: PlanSessionTask.sectionReview,
  sectionCode: PlanSessionTask.sectionCodeDialogue,
  kind: 'line',
  shelf: shelf,
);

class _SilentApi implements ApiClient {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
