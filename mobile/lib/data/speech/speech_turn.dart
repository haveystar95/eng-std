import 'dart:async';

import 'package:flutter/foundation.dart';

import 'speech_diagnostics.dart';
import 'speech_recognizer.dart';

/// ЧИСЛА ОДНОГО ХОДА ГОЛОСОМ — наряд DAY-FIX-3, Ч.1.3. Конфиг, не литералы в карточке: первый раз,
/// когда одно из них окажется неверным, оно должно сдвинуться в одном месте.
///
///   [silenceAfterSpeech]   тишина ПОСЛЕ последнего слова, которая закрывает попытку;
///   [maxSpeech]            сколько речи от ПЕРВОГО слова попытка вмещает — дальше она закрыта тем,
///                          что есть;
///   [silenceBeforeSkip]    сторож: столько тишины ОТ ОТКРЫТИЯ микрофона, если человек так и не
///                          начал говорить, — исход [SpeechTurnOutcome.silent];
///   [echoCoverage]         доля слов реплики роли в склейке, при которой склейка — эхо динамика,
///                          а не ответ человека.
@immutable
class SpeechTurnConfig {
  const SpeechTurnConfig({
    this.silenceAfterSpeech = const Duration(seconds: 2),
    this.maxSpeech = const Duration(seconds: 15),
    this.silenceBeforeSkip = const Duration(seconds: 15),
    this.echoCoverage = 0.7,
    this.reopenGap = const Duration(milliseconds: 120),
    this.deadChannelWindow = const Duration(seconds: 1),
    this.deadChannelStrikes = 3,
  });

  final Duration silenceAfterSpeech;
  final Duration maxSpeech;
  final Duration silenceBeforeSkip;
  final double echoCoverage;

  /// ПОЛ ОЖИДАНИЯ ПЕРВОГО СЛОВА (наряд DAY-GATE-1, Ч.0.2).
  ///
  /// [silenceBeforeSkip] приезжает с сервера (`listen_seconds`), и это правильно: сколько человек
  /// думает над репликой — суждение продукта. Но окно, в котором человек ещё НЕ НАЧАЛ говорить,
  /// имеет физический низ: между «микрофон открылся» и первым звуком лежит вдох, взгляд на
  /// подсказку и решение, а сторож, сработавший раньше, делает ход за человека пустым ответом
  /// (в прогоне — `again` в append-only журнал). Один неверный конфиг на сервере не должен уметь
  /// превратить прогон в конвейер промахов, поэтому пол стоит здесь, в движке, а не в вызывающем.
  ///
  /// Потолок речи ([maxSpeech]) пола не имеет и иметь не должен: он считается ОТ ПЕРВОГО СЛОВА,
  /// то есть человек к этому моменту уже говорит.
  static const minSilenceBeforeSkip = Duration(seconds: 5);

  /// Сколько ход реально ждёт первого слова — см. [minSilenceBeforeSkip].
  Duration get effectiveSilenceBeforeSkip =>
      silenceBeforeSkip < minSilenceBeforeSkip ? minSilenceBeforeSkip : silenceBeforeSkip;

  /// Пауза между закрытием плагина и его переоткрытием. Не про человека — про очередь событий:
  /// плагин, ответивший мгновенно, без неё крутил бы цикл, не давая таймерам попытки сработать.
  final Duration reopenGap;

  /// МЁРТВЫЙ КАНАЛ: плагин закрывается пустым быстрее [deadChannelWindow] [deadChannelStrikes] раз
  /// подряд — это не тишина человека, а микрофон, которого нет (симулятор, отозванное
  /// разрешение). Ход кончается [SpeechTurnOutcome.unavailable], а не сторожем через 15 с: сторож
  /// в прогоне делает ход за человека пустым ответом, и мёртвый микрофон писал бы в журнал по
  /// промаху на каждую реплику сцены (живой стенд 07.09).
  final Duration deadChannelWindow;
  final int deadChannelStrikes;

  SpeechTurnConfig copyWith({
    Duration? silenceAfterSpeech,
    Duration? maxSpeech,
    Duration? silenceBeforeSkip,
    double? echoCoverage,
    Duration? reopenGap,
    Duration? deadChannelWindow,
    int? deadChannelStrikes,
  }) => SpeechTurnConfig(
    silenceAfterSpeech: silenceAfterSpeech ?? this.silenceAfterSpeech,
    maxSpeech: maxSpeech ?? this.maxSpeech,
    silenceBeforeSkip: silenceBeforeSkip ?? this.silenceBeforeSkip,
    echoCoverage: echoCoverage ?? this.echoCoverage,
    reopenGap: reopenGap ?? this.reopenGap,
    deadChannelWindow: deadChannelWindow ?? this.deadChannelWindow,
    deadChannelStrikes: deadChannelStrikes ?? this.deadChannelStrikes,
  );
}

/// Чем кончился ОДИН ХОД — полная попытка, а не один `finalResult` плагина.
enum SpeechTurnOutcome {
  /// Полная попытка: человек договорил (тишина после последнего слова, потолок речи, «Готово» или
  /// ключ узнан по дороге). [SpeechTurnResult.transcript] — вся склейка. Что в ней — судит карточка:
  /// мимо ключа — это честная ошибка, а не поломка канала.
  heard,

  /// Обрыв: канал упал ПОСЛЕ того, как человек начал говорить. Не ответ и не ошибка —
  /// «Не расслышали до конца — скажи ещё раз», журнал не пишется, вторая попытка (Ч.1.4).
  incomplete,

  /// Сторож: [SpeechTurnConfig.silenceBeforeSkip] тишины от открытия, человек не начал говорить.
  /// Выход — «Пропустить» (в прогоне его нажимает сам сторож).
  silent,

  /// Канал не поднялся вовсе: нет разрешения, нет движка. Как и раньше — состояние тренажёра.
  unavailable,
}

@immutable
class SpeechTurnResult {
  const SpeechTurnResult(this.outcome, {this.transcript = '', this.speechStartedAt, this.echoes = 0});

  final SpeechTurnOutcome outcome;

  /// Склейка всего, что было услышано за попытку, через пробел.
  final String transcript;

  /// Когда прозвучало ПЕРВОЕ слово — от него меряется «сразу» (канон §4). Null — слов не было.
  final DateTime? speechStartedAt;

  /// Сколько раз склейка была выброшена как эхо реплики роли (Ч.1.5) — для журнала и тестов.
  final int echoes;

  bool get isHeard => outcome == SpeechTurnOutcome.heard && transcript.trim().isNotEmpty;
}

/// ОДИН ДВИЖОК СЛУШАНИЯ НА ВСЕ КАРТОЧКИ ГОВОРЕНИЯ — прогон сцены, микрофон в диалоге, говорение
/// слов, спасатели (наряд DAY-FIX-3, Ч.1).
///
/// Плагин распознавания закрывает попытку на первом же `finalResult`, а iOS ставит его на любой
/// запинке короче трёх секунд — так «My… back hurts» приходило как «My» и писалось ошибкой
/// (диагностика 06.09, п. 2). Движок стоит НАД плагином: результаты копятся в склейку, микрофон
/// переоткрывается, а закрывает попытку не плагин, а ПРАВИЛО:
///
///   * тишина [SpeechTurnConfig.silenceAfterSpeech] после последнего слова — договорил;
///   * [SpeechTurnConfig.maxSpeech] речи от первого слова — хватит;
///   * ключ узнан по дороге — договорил, ждать тишину незачем;
///   * «Готово» ([stop]) — договорил тем, что есть.
///
/// Сторож [SpeechTurnConfig.silenceBeforeSkip] считает от ОТКРЫТИЯ микрофона, а не от появления
/// карточки (п. 3 диагностики), и только пока человек не начал говорить.
///
/// ЭХО-ЗАМОК (Ч.1.5): микрофон, открытый рядом с динамиком, слышит реплику собеседника. Склейка,
/// в которой узнана реплика роли (по [echoOf]) и не узнан ни один ключ, — не ответ: она
/// выбрасывается, микрофон переоткрывается, попытка продолжается с чистого листа.
///
/// Движок ничего не знает о том, что такое правильный ответ: [isAnswer] и [echoOf] приходят
/// снаружи, и это то, что держит его вне слоя тренажёров. Таймеры — обычные [Timer], поэтому
/// виджет-тест гоняет его `pump(Duration)`, а юнит-тест — `fakeAsync`.
class SpeechTurn {
  SpeechTurn(
    this._recognizer, {
    this.config = const SpeechTurnConfig(),
    this.diagnostics,
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now;

  final SpeechRecognizer _recognizer;
  final SpeechTurnConfig config;

  /// КУДА ПИШЕТСЯ СОСТОЯНИЕ КАНАЛА (наряд DAY-GATE-1, Ч.0.1). Null — некуда, и это законно:
  /// движок ничего не читает обратно, поэтому его поведение от журнала не зависит.
  final SpeechDiagnostics? diagnostics;

  /// Часы — инъекция, чтобы «сразу» можно было измерить в тесте с поддельным временем.
  final DateTime Function() _now;

  Completer<SpeechTurnResult>? _turn;
  final List<String> _chunks = [];
  String _partial = '';
  DateTime? _firstWordAt;
  int _echoes = 0;
  Timer? _silenceTimer;
  Timer? _maxSpeechTimer;
  Timer? _skipTimer;
  bool _closing = false;
  bool _manualStop = false;

  bool get isListening => _turn != null && !_turn!.isCompleted;

  /// Всё, что услышано на этот момент — склейка плюс текущий кусок.
  String get transcript => [..._chunks, if (_partial.trim().isNotEmpty) _partial.trim()].join(' ').trim();

  /// Один ход. Возвращается ОДИН раз, когда попытка закрыта по правилу — см. класс.
  ///
  /// [expected] и [contextualStrings] едут в плагин подсказкой; [isAnswer] — «в склейке узнан
  /// ключ» (любой из speaking_keys, Ч.1.6); [echoOf] — «в склейке узнана реплика роли»
  /// (покрытие ≥ [SpeechTurnConfig.echoCoverage] считает вызывающий, движок только спрашивает).
  Future<SpeechTurnResult> listen({
    required List<String> expected,
    required String localeId,
    List<String> contextualStrings = const [],
    bool Function(String transcript)? isAnswer,
    bool Function(String transcript)? echoOf,
    ValueChanged<String>? onPartial,
    VoidCallback? onSpeechStarted,
  }) async {
    if (isListening) return _turn!.future;
    final turn = Completer<SpeechTurnResult>();
    _turn = turn;
    _chunks.clear();
    _partial = '';
    _firstWordAt = null;
    _echoes = 0;
    _closing = false;
    _manualStop = false;
    diagnostics?.turnStarted();

    _armSkip();

    // ЦИКЛ КРУТИТСЯ В СТОРОНЕ ОТ ХОДА, и это значит, что его исключение НЕ доходит до того, кто
    // ждёт исход (наряд DAY-GATE-1, Ч.0.2, находка F4): плагин, бросивший `PlatformException`,
    // оставлял `turn.future` незавершённым навсегда — карточка стояла на «Слушаю…», микрофона за
    // этим не было, и выхода тоже. Ход обязан закрыться, чем бы ни кончился цикл.
    unawaited(
      _loop(
        expected: expected,
        localeId: localeId,
        contextualStrings: contextualStrings,
        isAnswer: isAnswer,
        echoOf: echoOf,
        onPartial: onPartial,
        onSpeechStarted: onSpeechStarted,
      ).catchError((Object error) {
        _settle(
          // Транскрипт, который успел набраться, делает это обрывом на полуслове, а не отказом
          // канала: разница между «не расслышали до конца» и «микрофон недоступен».
          transcript.isEmpty ? SpeechTurnOutcome.unavailable : SpeechTurnOutcome.incomplete,
          phase: SpeechPhase.failed,
          code: 'engine_threw: $error',
        );
      }),
    );

    return turn.future;
  }

  Future<void> _loop({
    required List<String> expected,
    required String localeId,
    required List<String> contextualStrings,
    required bool Function(String)? isAnswer,
    required bool Function(String)? echoOf,
    required ValueChanged<String>? onPartial,
    required VoidCallback? onSpeechStarted,
  }) async {
    final turn = _turn!;
    var reopening = false;
    var instantSilences = 0;
    while (!turn.isCompleted && !_closing) {
      if (reopening) {
        await Future<void>.delayed(config.reopenGap);
        if (turn.isCompleted || _closing) return;
      }
      reopening = true;
      _partial = '';
      final openedAt = _now();
      final attempt = await _recognizer.listenOnce(
        expected: expected,
        localeId: localeId,
        // Окно плагина — не правило, а потолок: попытку закрывает движок. Плагину отдаётся
        // столько, сколько попытка вообще может длиться, и его же пауза после речи.
        timeout: config.maxSpeech + config.silenceBeforeSkip,
        pauseFor: config.silenceAfterSpeech,
        contextualStrings: contextualStrings,
        onPartial: (text) {
          if (turn.isCompleted || _closing) return;
          final trimmed = text.trim();
          if (trimmed.isEmpty) return;
          final changed = trimmed != _partial;
          _partial = trimmed;
          if (!changed) return;
          if (_firstWordAt == null) {
            _firstWordAt = _now();
            _skipTimer?.cancel();
            _armMaxSpeech();
            diagnostics?.phaseIs(SpeechPhase.listening);
            onSpeechStarted?.call();
          }
          _armSilence();
          diagnostics?.partial(transcript);
          onPartial?.call(transcript);
          // КЛЮЧ УЗНАН ПО ДОРОГЕ — договорил. Ждать две секунды тишины после ответа, который уже
          // прозвучал, значит держать человека у микрофона ради ничего.
          if (isAnswer != null && isAnswer(transcript)) {
            _settle(SpeechTurnOutcome.heard, phase: SpeechPhase.closedByAnswer);
          }
        },
      );
      if (turn.isCompleted || _closing) return;

      switch (attempt.outcome) {
        case SpeechOutcome.heard:
          final chunk = attempt.text.trim();
          _partial = '';
          // Тот же кусок дважды подряд — плагин отдал старый результат на переоткрытии, а не
          // человек повторил фразу: второй раз в склейку не идёт и тишину не сбрасывает.
          final added = chunk.isNotEmpty && (_chunks.isEmpty || _chunks.last != chunk);
          if (added) {
            _chunks.add(chunk);
            _firstWordAt ??= _now();
          }
          final joined = transcript;
          if (isAnswer != null && joined.isNotEmpty && isAnswer(joined)) {
            _settle(SpeechTurnOutcome.heard, phase: SpeechPhase.closedByAnswer);

            return;
          }
          // ЭХО: реплика собеседника узнана, ключ — нет. Склейка вон, микрофон переоткрыт.
          if (echoOf != null && joined.isNotEmpty && echoOf(joined)) {
            _echoes++;
            _chunks.clear();
            _firstWordAt = null;
            _silenceTimer?.cancel();
            _maxSpeechTimer?.cancel();
            _armSkip();
            // «Слушаю…» БЕЗ ТЕКСТА выглядит одинаково у мёртвого микрофона и у исправного, который
            // честно выбросил эхо динамика. Журнал — единственное, что их различает.
            diagnostics?.echoDropped();
            onPartial?.call('');

            continue;
          }
          // ПОПЫТКА ЕЩЁ ОТКРЫТА: плагин закрылся на запинке, а тишина [silenceAfterSpeech] после
          // последнего слова ещё не набежала — переоткрываем и слушаем дальше. Таймер тишины
          // уже идёт с последнего слова и закроет попытку сам.
          if (_manualStop) {
            _settle(SpeechTurnOutcome.heard, phase: SpeechPhase.closedBySilence);

            return;
          }
          // Тишина считается от ПОСЛЕДНЕГО НОВОГО слова: новый кусок сдвигает её, повтор — нет, а
          // без таймера вовсе (кусок пришёл без partial) она заводится сейчас.
          if (added || _silenceTimer == null) _armSilence();

          continue;

        case SpeechOutcome.silent:
          // Плагин отдал пустоту. Слова были — это запинка длиннее его окна, тишина закроет сама;
          // слов не было — сторож ждёт своего срока. В обоих случаях микрофон переоткрывается.
          if (_manualStop) {
            _settle(
              transcript.isEmpty ? SpeechTurnOutcome.silent : SpeechTurnOutcome.heard,
              phase: SpeechPhase.closedBySilence,
            );

            return;
          }
          // …КРОМЕ МЁРТВОГО КАНАЛА: пустота, вернувшаяся быстрее окна несколько раз подряд без
          // единого слова, — это не человек молчит, это микрофона нет.
          if (transcript.isEmpty && _now().difference(openedAt) < config.deadChannelWindow) {
            instantSilences++;
            if (instantSilences >= config.deadChannelStrikes) {
              _settle(
                SpeechTurnOutcome.unavailable,
                phase: SpeechPhase.failed,
                code: 'dead_channel',
              );

              return;
            }
          } else {
            instantSilences = 0;
          }

          continue;

        case SpeechOutcome.unavailable:
          _settle(
            transcript.isEmpty ? SpeechTurnOutcome.unavailable : SpeechTurnOutcome.incomplete,
            phase: SpeechPhase.failed,
            code: transcript.isEmpty ? 'channel_down' : 'cut_off',
          );

          return;
      }
    }
  }

  void _armSilence() {
    _silenceTimer?.cancel();
    _silenceTimer = Timer(config.silenceAfterSpeech, () {
      if (!isListening) return;
      _settle(
        transcript.isEmpty ? SpeechTurnOutcome.silent : SpeechTurnOutcome.heard,
        phase: SpeechPhase.closedBySilence,
      );
    });
  }

  void _armMaxSpeech() {
    _maxSpeechTimer?.cancel();
    _maxSpeechTimer = Timer(config.maxSpeech, () {
      if (!isListening) return;
      _settle(
        transcript.isEmpty ? SpeechTurnOutcome.silent : SpeechTurnOutcome.heard,
        phase: SpeechPhase.closedByTimeout,
      );
    });
  }

  void _armSkip() {
    _skipTimer?.cancel();
    // ПОЛ — см. [SpeechTurnConfig.minSilenceBeforeSkip]: до первого слова ход ждёт не меньше пяти
    // секунд, каким бы маленьким ни приехал `listen_seconds`.
    _skipTimer = Timer(config.effectiveSilenceBeforeSkip, () {
      if (!isListening || _firstWordAt != null) return;
      _settle(SpeechTurnOutcome.silent, phase: SpeechPhase.closedByTimeout);
    });
  }

  /// «Готово»: закрыть попытку тем, что есть. Плагин отдаст свой последний кусок, и он войдёт в
  /// склейку — поэтому не [_settle] сразу, а метка и `stop()`.
  Future<void> stop() async {
    if (!isListening) return;
    _manualStop = true;
    await _recognizer.stop();
    // Плагин, который на `stop` ничего не отдал (фейк в тестах, мёртвый движок), не должен
    // держать ход: закрываем сами тем, что услышали.
    if (isListening) {
      _settle(
        transcript.isEmpty ? SpeechTurnOutcome.silent : SpeechTurnOutcome.heard,
        phase: SpeechPhase.closedBySilence,
      );
    }
  }

  /// Бросить ход и ничего не хранить (карточку покинули).
  Future<void> cancel() async {
    _closing = true;
    _cancelTimers();
    final turn = _turn;
    _turn = null;
    await _recognizer.cancel();
    if (turn != null && !turn.isCompleted) {
      diagnostics?.phaseIs(SpeechPhase.idle);
      turn.complete(const SpeechTurnResult(SpeechTurnOutcome.silent));
    }
  }

  void _settle(SpeechTurnOutcome outcome, {required SpeechPhase phase, String? code}) {
    final turn = _turn;
    if (turn == null || turn.isCompleted) return;
    diagnostics?.phaseIs(phase, code: code);
    _closing = true;
    _cancelTimers();
    final text = transcript;
    turn.complete(SpeechTurnResult(
      outcome,
      transcript: text,
      speechStartedAt: _firstWordAt,
      echoes: _echoes,
    ));
    // Микрофон закрывается ПОСЛЕ того, как ход отдан: ждать плагин здесь нечего.
    unawaited(_recognizer.cancel());
  }

  void _cancelTimers() {
    _silenceTimer?.cancel();
    _maxSpeechTimer?.cancel();
    _skipTimer?.cancel();
    _silenceTimer = null;
    _maxSpeechTimer = null;
    _skipTimer = null;
  }
}
