import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show MethodChannel;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/ui/mic_button.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/data/speech/speech_diagnostics.dart';
import 'package:eng_std/data/speech/speech_turn.dart';
import 'package:eng_std/features/training/session/intro_card.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The intro card's optional echo: listen, say something kind, write nothing.
///
/// The tests here are almost all about ABSENCE, because that is what the feature is. The echo has
/// no verdict, no grade and no queue behind it — the intro card's contract is that it asks for
/// nothing, and an echo that could be failed would quietly make it the app's first exercise.
class _FakeRecognizer implements SpeechRecognizer {
  _FakeRecognizer({
    required this.attempt,
    required this.isReady,
    bool? hasPermission,
    this.completeOnStop = false,
    bool? grantsOnPrepare,
  }) : _hasPermission = hasPermission ?? isReady,
       _grantsOnPrepare = grantsOnPrepare ?? isReady;

  final SpeechAttempt attempt;

  @override
  final bool isReady;

  /// When true, `listenOnce` does not settle on its own — only [stop] resolves it, mirroring the
  /// real plugin. Needed to observe the card WHILE it thinks it is recording, which is the whole
  /// subject of the listening-indicator tests (QA-21).
  final bool completeOnStop;
  Completer<SpeechAttempt>? _pending;

  /// The OS-level answer (QA-21) — independent of [isReady], which is only "has *this process*
  /// already prepared". Defaults to matching [isReady] so every OLDER test in this file (written
  /// before [hasPermission] existed) keeps its original meaning unchanged.
  final bool _hasPermission;

  int calls = 0;
  int prepares = 0;
  int permissionChecks = 0;
  int stops = 0;

  /// What the echo handed over, per call — the recording window and the vocabulary hint it should
  /// be sending exactly as the speaking word form does (QA-21).
  final List<Duration> timeoutsPerCall = [];
  final List<Duration> pauseForsPerCall = [];
  final List<List<String>> contextualStringsPerCall = [];

  /// Feeds a live partial into the card mid-attempt, the way the real plugin streams them.
  void emitPartial(String text) => _onPartial?.call(text);
  ValueChanged<String>? _onPartial;

  /// What the iOS prompt comes back with when the intro card's microphone invitation is tapped
  /// (наряд A-4.1 Ч.5). Defaults to [isReady] so every older test in this file keeps its meaning.
  final bool _grantsOnPrepare;

  @override
  Future<bool> prepare() async {
    prepares++;

    return _grantsOnPrepare;
  }

  @override
  Future<bool> get hasPermission async {
    permissionChecks++;

    return _hasPermission;
  }

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) async {
    calls++;
    timeoutsPerCall.add(timeout);
    pauseForsPerCall.add(pauseFor);
    contextualStringsPerCall.add(contextualStrings);
    _onPartial = onPartial;

    if (completeOnStop) {
      final completer = Completer<SpeechAttempt>();
      _pending = completer;

      return completer.future;
    }

    return attempt;
  }

  @override
  Future<void> stop() async {
    stops++;
    final pending = _pending;
    _pending = null;
    if (pending != null && !pending.isCompleted) pending.complete(attempt);
  }

  @override
  Future<void> cancel() async {}
}

/// КНОПКА МИКРОФОНА ЭХА — та же, что на двух других карточках говорения (наряд SPEECH-2, Ч.1.3).
///
/// По СЕМАНТИКЕ, а не по подписи: подпись меняется вместе с состоянием («Твоя очередь» → «Готово»),
/// а кнопка одна, и тест, ищущий её по тексту, ломается ровно там, где текст и должен меняться.

void main() {
  SessionCard introCard() => SessionCard(
    termId: 'T1',
    mode: ExerciseMode.intro,
    type: 'word',
    prompt: 'бронь',
    answer: 'reservation',
    transcription: 'ˌrezərˈveɪʃn',
    example: 'I have a reservation for tonight.',
    exampleTranslation: 'У меня бронь на сегодня.',
    ladderStep: 0,
  );

  /// Журнал канала, тот же, что у карточек говорения (наряд DAY-GATE-1, доработка Ч.0.1). Живёт на
  /// весь тест, чтобы проверку «эхо пишет сюда» можно было задать после хода.
  late SpeechDiagnostics diagnostics;
  setUp(() => diagnostics = SpeechDiagnostics(channel: const MethodChannel('test/absent')));

  Finder micButton() => find.byType(MicButton);

  Widget host(_FakeRecognizer recognizer) => ProviderScope(
    overrides: [
      speechRecognizerProvider.overrideWithValue(recognizer),
      speechDiagnosticsProvider.overrideWithValue(diagnostics),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: Scaffold(
        body: SingleChildScrollView(
          child: SessionIntroCard(
            card: introCard(),
            speechLocaleId: 'en_US',
            autoPronounce: false,
            onSpeak: (String text, {bool slow = false}) async {},
          ),
        ),
      ),
    ),
  );

  // ПРАВИЛО: наряд SPEECH-2, Ч.1.4 — разрешение спрашивается ПРИ ПЕРВОМ ТАПЕ, не при открытии
  // карточки.
  // ЛОВИТ: карточку знакомства, которая просит микрофон, едва появившись. Её контракт — не просить
  // ничего, и системное окно на первом же новом слове это ровно то, чего он не допускает.
  testWidgets('asks for nothing until the microphone is actually tapped', (tester) async {
    final recognizer = _FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: false);
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    // Кнопка стоит на месте — приглашения, которое человеку приходилось разгадывать, больше нет.
    expect(micButton(), findsOneWidget);
    expect(recognizer.prepares, 0, reason: 'разрешение спрошено до нажатия');
    expect(recognizer.calls, 0);
    // Сама карточка не тронута: слово, транскрипция и «Понятно» на месте.
    expect(find.text('reservation'), findsOneWidget);
  });

  // ПРАВИЛО: Ч.1.4 — тап и есть запрос разрешения.
  // ЛОВИТ: кнопку, которая на отказанном разрешении молча ничего не делает. Отказ — обычное
  // состояние этой карточки, но человек должен видеть, что его нажатие дошло.
  testWidgets('the first tap is what asks the OS — and a refusal says so', (tester) async {
    final recognizer = _FakeRecognizer(
      attempt: const SpeechAttempt.silent(),
      isReady: false,
      grantsOnPrepare: false,
    );
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    await tester.tap(micButton());
    await tester.pumpAndSettle();

    expect(recognizer.prepares, 1);
    expect(recognizer.calls, 0, reason: 'без разрешения микрофон не открывается');
    expect(find.text('Попробуй ещё'), findsOneWidget);
  });

  // ПРАВИЛО: Ч.3.1 / Ч.3.5 — эхо говорит, что вышло: «верно», «почти — не хватило: …», «не то».
  // ЛОВИТ: молчащее эхо. До наряда оно говорило «Услышал тебя» на любую попытку, и человек,
  // прочитавший слово неправильно, узнавал об этом только на карточке говорения через два дня.
  // Порог — чтения с экрана: слово стоит перед глазами.
  testWidgets('says what came out — right, almost, or not that', (tester) async {
    for (final (heard, expected) in [
      ('reservation', 'Верно'),
      ('completely different words', 'Не то'),
    ]) {
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpWidget(
        host(_FakeRecognizer(attempt: SpeechAttempt.heard(heard), isReady: true)),
      );
      await tester.pumpAndSettle();

      await tester.tap(micButton());
      await tester.pumpAndSettle();

      expect(find.text(expected), findsOneWidget, reason: heard);
    }
  });

  group('the echo makes the recording legible (QA-21)', () {
    testWidgets('tapping it shows that the phone is recording, and offers a way to stop', (
      tester,
    ) async {
      // Held open, so the card can be observed WHILE it thinks it is listening.
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
        completeOnStop: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(micButton());
      await tester.pump();

      // Before this, a tap changed nothing at all on screen — the button just went disabled, so a
      // live microphone looked exactly like a dead one.
      expect(find.text('Слушаю…'), findsOneWidget);
      // «Готово» — СЕМАНТИКА кнопки, а не её подпись: подпись говорит человеку, что делать («Пишу
      // — скажи и нажми „Готово“»), а имя элемента остаётся коротким для читалки экрана.
      expect(find.bySemanticsLabel('Готово'), findsOneWidget, reason: 'and the tap became a stop');
      expect(find.text('Повторить вслух'), findsNothing);
    });

    testWidgets('a live partial appears while still recording', (tester) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
        completeOnStop: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(micButton());
      await tester.pump();
      recognizer.emitPartial('reser');
      await tester.pump();

      // Seeing your own words appear is the clearest possible «yes, it hears you».
      expect(find.text('Услышали: «reser»'), findsOneWidget);
    });

    testWidgets('the second tap stops the recording and settles on what was heard', (tester) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
        completeOnStop: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(micButton());
      await tester.pump();
      await tester.tap(micButton()); // второй тап — «Готово»
      await tester.pumpAndSettle();

      expect(recognizer.stops, 1);
      expect(find.text('Услышали: «reservation»'), findsOneWidget);
      expect(find.text('Слушаю…'), findsNothing);
    });

    testWidgets('an empty result invites another go instead of printing nothing', (tester) async {
      await tester.pumpWidget(
        host(_FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: true)),
      );
      await tester.pumpAndSettle();

      await tester.tap(micButton());
      await tester.pumpAndSettle();

      expect(find.text('Попробуй ещё'), findsOneWidget);
      expect(find.textContaining('Услышали'), findsNothing);
    });

    testWidgets('tapping again after a result starts over — the old text is replaced', (
      tester,
    ) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(micButton());
      await tester.pumpAndSettle();
      expect(find.text('Услышали: «reservation»'), findsOneWidget);

      await tester.tap(micButton());
      await tester.pumpAndSettle();

      // A second attempt, not a second line: the old transcript is gone, replaced by this one.
      // Открытий плагина при этом больше двух — попытку закрывает движок, а не плагин (см. выше).
      expect(find.text('Услышали: «reservation»'), findsOneWidget);
    });

    testWidgets('it sends the same window and vocabulary hint the speaking word form does', (
      tester,
    ) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(micButton());
      await tester.pumpAndSettle();

      // ПЛАГИНУ ОТДАЮТСЯ ЧИСЛА ДВИЖКА — то же правило, что у карточки говорения (DAY-FIX-3, Ч.1.3):
      // окно плагина это ПОТОЛОК, а не правило, и закрывает попытку движок. Пауза после речи
      // остаётся той, которую просит длина слова, — это и есть «то же окно, что у говорения».
      const engine = SpeechTurnConfig();
      expect(recognizer.timeoutsPerCall.first, engine.effectiveMaxRecording);
      expect(recognizer.pauseForsPerCall.first, SpokenAnswer.wordFormPauseFor);
      expect(recognizer.contextualStringsPerCall.first, ['reservation']);
    });

    // ПРАВИЛО: наряд DAY-GATE-1, доработка Ч.0.1 — один движок слушания на все карточки говорения,
    // и одна диагностика.
    // ЛОВИТ: эхо, которое ходит в плагин напрямую. Так и было: эхо знакомства — очень часто ПЕРВЫЙ
    // микрофон в запуске, то есть первое место, где поломка канала видна, — и именно оно молчало в
    // служебной строке. Плюс запинка длиннее паузы плагина обрывала его попытку на полуслове, хотя
    // у всех остальных карточек склейка это чинит.
    testWidgets('эхо ходит через общий движок и пишет в общий журнал', (tester) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      expect(diagnostics.phase, SpeechPhase.idle, reason: 'до хода журнал молчит');

      await tester.tap(micButton());
      await tester.pumpAndSettle();

      expect(diagnostics.phase, SpeechPhase.closedBySilence);
      expect(diagnostics.log, isNotEmpty);
      // …и по-прежнему НИЧЕГО не пишет в лестницу: у карточки нет ни ответа, ни вердикта.
      expect(find.textContaining('Не то'), findsNothing);
    });
  });

  // ПРИГЛАШЕНИЕ УБРАНО ВМЕСТЕ С ПРЕДВАРИТЕЛЬНЫМ ВОПРОСОМ (наряд SPEECH-2, Ч.1.4).
  //
  // Группа «the microphone invitation (наряд A-4.1 Ч.5)» проверяла экран из двух состояний:
  // тусклый глиф-приглашение до разрешения и кнопку эха после. Она была права для своего наряда —
  // эхо было недоступно и его нечем было найти, — но решала это ВТОРЫМ элементом, который человеку
  // приходилось разгадывать. Теперь кнопка одна и всегда, а разрешение спрашивает первый тап; что
  // от той группы осталось живым, проверяют два теста выше («asks for nothing until…» и «the first
  // tap is what asks the OS…»).

  testWidgets('the intro still requires nothing — «Понятно» never waited on any of this', (
    tester,
  ) async {
    final recognizer = _FakeRecognizer(
      attempt: const SpeechAttempt.silent(),
      isReady: false,
      hasPermission: false,
    );
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    expect(find.text('reservation'), findsOneWidget);
    expect(recognizer.prepares, 0);
  });
}
