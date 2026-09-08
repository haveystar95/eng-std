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
import 'package:eng_std/ui/mic_button.dart';

import '../../support/speech_probe_channel.dart';

/// Микрофон, которого не бывает на симуляторе: без подмены тест мерил бы плагин, а не экран.
/// Молчит всегда — это и есть тот случай, ради которого существуют сторож записи и «Пропустить».
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

  // ПРАВИЛО: наряд SPEECH-2, Ч.1.1 — МИКРОФОН НЕ ОТКРЫВАЕТСЯ САМ, НИГДЕ.
  // ЛОВИТ: возврат автооткрытия. Живьём 08.09 это выглядело так: человек ещё не собрался, а его
  // уже пишут; сторож тикает; первое, что слышит микрофон, — вдох. В тренажёре человек имеет право
  // подумать, и «реалистичность» разговора этого права не стоит.
  testWidgets('микрофон не открывается сам — только по нажатию (Ч.1.1)', (tester) async {
    final mic = _SilentRecognizer();
    await tester.pumpWidget(host(runCard(), run: const SceneRunKnobs(), recognizer: mic));
    await tester.pump();
    await tester.pump(const Duration(seconds: 5));

    expect(mic.calls, 0, reason: 'запись началась без нажатия');
    // …а кнопка ЗОВЁТ — состояние без таймаута (Ч.1.2).
    expect(find.text('Твоя очередь — нажми и говори'), findsOneWidget);

    await tester.tap(find.byType(MicButton));
    await tester.pump();
    expect(mic.calls, 1);
    expect(find.text('Пишу — скажи и нажми «Готово»'), findsOneWidget);
  });

  // ПРАВИЛО: Ч.1.1 — пока звучит реплика собеседника, кнопка не зовёт и не нажимается.
  // ЛОВИТ: тап посреди чужой реплики, который записал бы динамик. Эхо-замок его выбросит, но
  // человек к этому моменту уже потратит попытку и не поймёт, почему.
  testWidgets('кнопка ждёт, пока динамик говорит реплику собеседника (Ч.1.1)', (tester) async {
    final mic = _SilentRecognizer();
    final speaking = ValueNotifier<bool>(true);
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(), recognizer: mic, roleSpeaking: speaking),
    );
    await tester.pump();
    await tester.pump(const Duration(seconds: 2));

    expect(find.text('Собеседник говорит'), findsOneWidget);
    await tester.tap(find.byType(MicButton));
    await tester.pump();
    expect(mic.calls, 0, reason: 'кнопка нажалась посреди чужой реплики');

    // Реплика доиграла — кнопка позвала, и запись начинает человек.
    speaking.value = false;
    await tester.pump();
    expect(find.text('Твоя очередь — нажми и говори'), findsOneWidget);
    await tester.tap(find.byType(MicButton));
    await tester.pump();
    expect(mic.calls, 1);

    speaking.dispose();
  });

  // ПРАВИЛО: SCENE-RUN Ч.2.4 + SPEECH-2 Ч.2.1 — «Пропустить» на экране с первого кадра, а сторож
  // считает ЗАПИСЬ (от нажатия), не ожидание.
  // ЛОВИТ: выход, появляющийся по таймеру, и сторож, делающий ход за человека, который записи ещё
  // не начинал. Пустой ответ — это `again` в append-only журнале, и право его написать сторож
  // получает только после того, как человек нажал.
  testWidgets('«Пропустить» доступно всегда, а сторож считает запись от НАЖАТИЯ', (tester) async {
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(), onAnswered: answers.add),
    );
    await tester.pump();
    expect(find.text('Пропустить'), findsOneWidget);

    // Человек думает — и сколько бы он ни думал, ход остаётся его.
    await tester.pump(const Duration(seconds: 30));
    expect(answers, isEmpty, reason: 'сторож сделал ход за человека, не начавшего запись');

    await tester.tap(find.byType(MicButton));
    await tester.pump();
    await tester.pump(const Duration(seconds: 14));
    expect(answers, isEmpty, reason: 'пятнадцать секунд записи ещё не прошли');

    // …и сторож дожимает: ход делается пустым ответом, разговор идёт дальше.
    await tester.pump(const Duration(seconds: 2));
    await tester.pumpAndSettle();
    expect(answers, hasLength(1));
    expect(answers.single.response, isEmpty);
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
    await tester.tap(find.byType(MicButton));
    await tester.pump();
    expect(mic.calls, 1);

    // Эхо динамика: реплика роли целиком.
    mic.say('what seems to be the problem today');
    mic.close(const SpeechAttempt.heard('what seems to be the problem today'));
    await tester.pump(const Duration(milliseconds: 300));

    // Не ответ и не ошибка: журнал пуст, микрофон переоткрыт, ЗАПИСЬ ПРОДОЛЖАЕТСЯ.
    expect(answers, isEmpty);
    expect(mic.calls, 2);

    // Человек говорит свою реплику — и её закрывает тишина, а не совпадение (SPEECH-2, Ч.2.1).
    mic.say('my child has a fever');
    await tester.pump(const Duration(seconds: 7));
    await tester.pumpAndSettle();
    expect(answers, hasLength(1));
    expect(answers.single.verdict, LocalCheck.correct);
  });

  // ПРАВИЛО: наряд SPEECH-2, Ч.3.2 — ключ обязателен И покрытие остальных слов реплики ≥ 0.6.
  // ЛОВИТ: возврат к «ключ есть — верно». Именно так и было: «fever» вместо «My child has a fever»
  // засчитывалось верно, а движок вдобавок закрывал на этом слове запись — фраза не дослушивалась
  // и не оценивалась.
  testWidgets('одно ключевое слово вместо реплики — не ответ (Ч.3.2)', (tester) async {
    final mic = _DrivenRecognizer();
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(), recognizer: mic, onAnswered: answers.add),
    );
    await tester.pump();
    await tester.tap(find.byType(MicButton));
    await tester.pump();

    mic.say('fever');
    await tester.pump(const Duration(seconds: 7));
    await tester.pumpAndSettle();

    expect(answers, hasLength(1));
    expect(answers.single.verdict, LocalCheck.wrong);
  });

  // ПРАВИЛО: «клиент никогда не строже сервера» (инвариант проекта; сервер судит по ключу только
  // когда ключ ЕСТЬ — `SubmitReviewsHandler`, ветка `speaking_key !== null`, иначе реплика целиком).
  // ЛОВИТ: реплику без ключа, у которой приехали упрощённые формы. Список «что засчитывается»
  // состоял тогда из ОДНИХ альтернатив — а они не куски реплики, а другие способы её сказать, — и
  // человек, произнёсший реплику слово в слово, получал «Не то», пока сервер писал в журнал «верно»
  // (телефон владельца 08.09: «What skills are most important for this role?» при
  // `speaking_keys = ["top skills?", "which skills matter most?"]`).
  testWidgets('реплика без ключа засчитывается целиком, а не по упрощённым формам', (tester) async {
    final mic = _DrivenRecognizer();
    final answers = <SessionAnswer>[];
    final noKey = SessionCard(
      termId: '01ASK',
      mode: ExerciseMode.speaking,
      type: 'phrase',
      prompt: 'Какие навыки наиболее важны для этой роли?',
      answer: 'What skills are most important for this role?',
      speakingKeys: const ['top skills?', 'which skills matter most?'],
      ladderStep: 3,
    );

    await tester.pumpWidget(
      host(noKey, run: const SceneRunKnobs(), recognizer: mic, onAnswered: answers.add),
    );
    await tester.pump();
    await tester.tap(find.byType(MicButton));
    await tester.pump();

    mic.say('what skills are most important for this role');
    await tester.pump(const Duration(seconds: 7));
    await tester.pumpAndSettle();

    expect(answers, hasLength(1));
    expect(answers.single.verdict, LocalCheck.correct);
    expect(find.textContaining('Не то'), findsNothing);
  });

  testWidgets('обрыв на полуслове — «скажи ещё раз», журнал не пишется, вторую попытку начинает человек', (
    tester,
  ) async {
    final mic = _DrivenRecognizer();
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(
      host(runCard(), run: const SceneRunKnobs(), recognizer: mic, onAnswered: answers.add),
    );
    await tester.pump();
    await tester.tap(find.byType(MicButton));
    await tester.pump();

    mic.say('my child has');
    mic.close(const SpeechAttempt.unavailable());
    await tester.pump();

    expect(answers, isEmpty);
    expect(find.textContaining('Не расслышали до конца'), findsOneWidget);

    // ВТОРУЮ ПОПЫТКУ НАЧИНАЕТ ЧЕЛОВЕК (наряд SPEECH-2, Ч.1.1). Запись, начавшаяся сама сразу после
    // «не расслышали», ловит ровно ту же неготовность, из-за которой обрыв и вышел.
    await tester.pump(const Duration(seconds: 2));
    expect(mic.calls, 1, reason: 'микрофон переоткрылся сам');
    expect(find.text('Твоя очередь — нажми и говори'), findsOneWidget);

    await tester.tap(find.byType(MicButton));
    await tester.pump();
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
