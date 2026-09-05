import 'dart:async';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Микрофон, которого не бывает на симуляторе: прогон открывает его сам, и без подмены тест мерил
/// бы плагин, а не экран. Молчит всегда — это и есть тот случай, ради которого существуют сторож и
/// «Пропустить».
class _SilentRecognizer implements SpeechRecognizer {
  int calls = 0;
  int cancels = 0;

  @override
  bool get isReady => true;

  @override
  Future<bool> prepare() async => true;

  @override
  Future<bool> get hasPermission async => true;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
  }) {
    calls++;
    // Никогда не отвечает — ровно как микрофон, который не подняли.
    return Completer<SpeechAttempt>().future;
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async => cancels++;
}

/// ХОД ПРОГОНА СЦЕНЫ НА ЭКРАНЕ — наряд SCENE-RUN, Ч.2.
///
/// Ступень C: с экрана убрали всё, кроме подсказки на языке поддержки и микрофона. Здесь прибито
/// именно это «убрали»: реплики нет, ключа нет, вариантов нет, клавиатуры нет — и выход есть.
void main() {
  const line = 'My child has a fever';
  const key = 'a fever';

  SessionCard runCard() => SessionCard(
    termId: '01RUN',
    mode: ExerciseMode.speaking,
    type: 'phrase',
    // Подсказка на языке поддержки — перевод реплики, и это всё, что дают.
    prompt: 'У моего ребёнка температура',
    answer: line,
    speakingKey: key,
    ladderStep: 3,
  );

  Widget host(SessionCard c, {SceneRunKnobs? run, SpeechRecognizer? recognizer, AppUser? qa}) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
      speechRecognizerProvider.overrideWithValue(recognizer ?? _SilentRecognizer()),
      if (qa != null) authControllerProvider.overrideWith(() => _QaAuth(qa)),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: MediaQuery(
        data: const MediaQueryData(disableAnimations: true),
        child: Scaffold(
          body: SingleChildScrollView(
            child: SessionExerciseCard(
              card: c,
              speechLocaleId: 'en_US',
              answerLang: 'en',
              autoPronounce: false,
              onAnswered: (_) {},
              onSpeak: (text, {bool slow = false}) async {},
              showDue: false,
              inDialogue: true,
              sceneRun: run,
            ),
          ),
        ),
      ),
    ),
  );

  testWidgets('не показывает ни реплику, ни её ключ — только подсказку', (tester) async {
    await tester.pumpWidget(host(runCard(), run: const SceneRunKnobs()));
    await tester.pump();

    // Подсказка на языке поддержки есть.
    expect(find.text('У моего ребёнка температура'), findsOneWidget);
    // Реплики нет: иначе ступень C была бы чтением вслух под другим именем.
    expect(find.text(line), findsNothing);
    // И КЛЮЧА нет. Ключ написан на изучаемом языке — строка «главное — a fever» отдала бы половину
    // реплики тому, кого просят вспомнить её целиком.
    expect(find.textContaining(key), findsNothing);
    // Клавиатуры на ступени C не бывает, как и на всех прочих (канон §9).
    expect(find.byType(TextField), findsNothing);
  });

  testWidgets('обычная карточка говорения ключ по-прежнему называет', (tester) async {
    // Тот же тренажёр без прогона: он учит реплику, и назвать, что в ней главное, — его работа.
    await tester.pumpWidget(host(runCard()));
    await tester.pump();

    expect(find.textContaining(key), findsWidgets);
  });

  testWidgets('дев-двери QA нет у обычного аккаунта', (tester) async {
    // Право приезжает с сервера одним полем, и клиент его не складывает сам: инструмент, который
    // засчитывает ход, не открывая рта, не должен зависеть от того, как собрана сборка.
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(), qa: AppUser(id: '01U', name: 'Learner')),
    );
    await tester.pump();

    expect(find.text('QA · fast'), findsNothing);
    expect(find.text('QA · said'), findsNothing);
  });

  testWidgets('QA-аккаунту за открытой дверью подстановка доступна', (tester) async {
    await tester.pumpWidget(
      host(
        runCard(),
        run: const SceneRunKnobs(),
        qa: AppUser(id: '01U', name: 'QA', qaTools: true),
      ),
    );
    await tester.pump();

    expect(find.text('QA · fast'), findsOneWidget);
    expect(find.text('QA · said'), findsOneWidget);
    expect(find.text('QA · miss'), findsOneWidget);
  });

  testWidgets('открывает «Пропустить» по времени, а не по поломке микрофона', (tester) async {
    // В говорении фраз выход появляется после отказа канала: там молчание это железо. В прогоне
    // молчание — законный ход человека, который не вспомнил, и держать его до поломки значило бы
    // наказывать за незнание отсутствием выхода.
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(skipAfterSeconds: 5, listenSeconds: 8)),
    );
    await tester.pump();

    expect(find.text('Пропустить'), findsNothing);

    await tester.pump(const Duration(seconds: 6));
    expect(find.text('Пропустить'), findsOneWidget);

    // …и сторож дожимает сам: через `listenSeconds` ход делается пустым ответом, разговор идёт
    // дальше, никто не застревает (наряд Ч.2.4).
    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();
  });
}

/// Аутентификация, отвечающая заранее известным пользователем — дверь QA решает СЕРВЕР.
class _QaAuth extends AuthController {
  _QaAuth(this._user);

  final AppUser _user;

  @override
  Future<AppUser?> build() async => _user;
}
