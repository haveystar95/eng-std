import 'dart:async';

import 'package:drift/native.dart';
import 'package:flutter/foundation.dart' show ValueListenable;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../support/speech_probe_channel.dart';

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
  // Ответ ОС про разрешения — {@see mockSpeechProbe}: на карточке прогона есть микрофон.
  mockSpeechProbe();

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
    // Упрощённая форма ответа (GEN-1, `speaking_keys`): засчитывается наравне с ключом.
    speakingKeys: const ['fever'],
    ladderStep: 3,
  );

  Widget host(
    SessionCard c, {
    SceneRunKnobs? run,
    SpeechRecognizer? recognizer,
    AppUser? qa,
    ValueListenable<bool>? roleSpeaking,
    String? roleLineText,
    ValueChanged<SessionAnswer>? onAnswered,
  }) => ProviderScope(
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
              onAnswered: onAnswered ?? (_) {},
              onSpeak: (text, {bool slow = false}) async {},
              showDue: false,
              inDialogue: true,
              sceneRun: run,
              roleSpeaking: roleSpeaking,
              roleLineText: roleLineText,
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

  testWidgets('«Пропустить» доступно всегда, а сторож дожимает ход по тишине от ОТКРЫТИЯ микрофона', (
    tester,
  ) async {
    // В говорении фраз выход появляется после отказа канала: там молчание это железо. В прогоне
    // молчание — законный ход человека, который не вспомнил (SCENE-RUN, Ч.2.4), а с DAY-FIX-3
    // (Ч.1.7) выход не появляется по таймеру — он на экране с первого кадра.
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(listenSeconds: 8), onAnswered: answers.add),
    );
    await tester.pump();
    expect(find.text('Пропустить'), findsOneWidget);

    // Микрофон открывается после слайда; сторож считает от него (Ч.1.3), не от карточки.
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump(const Duration(seconds: 7));
    expect(answers, isEmpty, reason: 'восемь секунд от открытия ещё не прошли');

    // …и сторож дожимает сам: ход делается пустым ответом, разговор идёт дальше, никто не
    // застревает.
    await tester.pump(const Duration(seconds: 2));
    await tester.pumpAndSettle();
    expect(answers, hasLength(1));
    expect(answers.single.response, isEmpty);
  });

  testWidgets('микрофон не открывается, пока динамик говорит реплику собеседника (Ч.1.1)', (
    tester,
  ) async {
    final mic = _SilentRecognizer();
    final speaking = ValueNotifier<bool>(true);
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(), recognizer: mic, roleSpeaking: speaking),
    );
    await tester.pump();
    await tester.pump(const Duration(seconds: 2));

    // Динамик ещё говорит — микрофон закрыт, что бы ни было на слайде.
    expect(mic.calls, 0);

    // Реплика доиграла — микрофон открылся по этому событию, а не по таймеру карточки.
    speaking.value = false;
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(mic.calls, 1);

    speaking.dispose();
  });

  testWidgets('склейка, в которой слышна реплика собеседника, выбрасывается как эхо (Ч.1.5)', (
    tester,
  ) async {
    final mic = _DrivenRecognizer();
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(
      host(
        runCard(),
        run: const SceneRunKnobs(),
        recognizer: mic,
        roleLineText: 'What seems to be the problem today?',
        onAnswered: answers.add,
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(mic.calls, 1);

    // Эхо динамика: реплика роли целиком, ключа нет.
    mic.say('what seems to be the problem today');
    mic.close(const SpeechAttempt.heard('what seems to be the problem today'));
    await tester.pump(const Duration(milliseconds: 300));

    // Не ответ и не ошибка: журнал пуст, микрофон переоткрыт.
    expect(answers, isEmpty);
    expect(mic.calls, 2);

    // Человек: упрощённый ключ (`speaking_keys`) засчитан по дороге — Ч.1.6.
    mic.say('fever');
    await tester.pumpAndSettle();
    expect(answers, hasLength(1));
    expect(answers.single.verdict, LocalCheck.correct);
  });

  testWidgets('обрыв на полуслове — «скажи ещё раз», журнал не пишется, микрофон открывается снова (Ч.1.4)', (
    tester,
  ) async {
    final mic = _DrivenRecognizer();
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(), recognizer: mic, onAnswered: answers.add),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    mic.say('my child has');
    mic.close(const SpeechAttempt.unavailable());
    await tester.pump();

    expect(answers, isEmpty);
    expect(find.textContaining('Не расслышали до конца'), findsOneWidget);

    // Вторая попытка — сама, без нажатий: прогон открыл микрофон, прогону его и переоткрывать.
    await tester.pump(const Duration(seconds: 1));
    expect(mic.calls, 2);
  });
}

/// Плагин под управлением теста — частичные результаты и исход кладёт сам тест.
class _DrivenRecognizer implements SpeechRecognizer {
  int calls = 0;
  Completer<SpeechAttempt>? _pending;
  ValueChanged<String>? _onPartial;

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
    _onPartial = onPartial;
    final completer = Completer<SpeechAttempt>();
    _pending = completer;

    return completer.future;
  }

  void say(String text) => _onPartial?.call(text);

  void close(SpeechAttempt attempt) {
    final pending = _pending;
    _pending = null;
    if (pending != null && !pending.isCompleted) pending.complete(attempt);
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async => close(const SpeechAttempt.silent());
}

/// Аутентификация, отвечающая заранее известным пользователем — дверь QA решает СЕРВЕР.
class _QaAuth extends AuthController {
  _QaAuth(this._user);

  final AppUser _user;

  @override
  Future<AppUser?> build() async => _user;
}
