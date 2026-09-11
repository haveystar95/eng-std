import 'dart:async';

import 'package:flutter/foundation.dart';

import 'speech_diagnostics.dart';
import 'speech_recognizer.dart';

/// ЧИСЛА ОДНОГО ХОДА ГОЛОСОМ — наряды DAY-FIX-3 (Ч.1.3) и SPEECH-2 (Ч.2). Конфиг, не литералы в
/// карточке: первый раз, когда одно из них окажется неверным, оно должно сдвинуться в одном месте.
///
///   [silenceAfterSpeech]   тишина ПОСЛЕ последнего слова, которая закрывает запись;
///   [maxRecording]         сторож ОБЩЕЙ ДЛИНЫ ЗАПИСИ — от тапа до закрытия. Пол 15 с
///                          ([minMaxRecording]); серверный `listen_seconds` может его поднять и не
///                          может опустить;
///   [echoCoverage]         доля слов реплики роли в склейке, при которой склейка — эхо динамика,
///                          а не ответ человека.
///
/// ЧЕГО ЗДЕСЬ БОЛЬШЕ НЕТ. `silenceBeforeSkip` («сторож ожидания первого слова») ушёл вместе с
/// автооткрытием микрофона: запись начинается по НАЖАТИЮ (Ч.1.1), и человек, который её начал, уже
/// решил говорить — отдельного окна «он ещё думает» больше не существует. Что от него осталось —
/// [minWaitBeforeSilence], пол, ниже которого тишина не закрывает запись вовсе.
@immutable
class SpeechTurnConfig {
  const SpeechTurnConfig({
    this.silenceAfterSpeech = const Duration(seconds: 2),
    this.maxRecording = const Duration(seconds: 15),
    this.echoCoverage = 0.7,
    this.reopenGap = const Duration(milliseconds: 120),
    this.deadChannelWindow = const Duration(seconds: 1),
    this.deadChannelStrikes = 3,
  });

  final Duration silenceAfterSpeech;
  final Duration maxRecording;
  final double echoCoverage;

  /// ПОЛ СТОРОЖА ЗАПИСИ (наряд SPEECH-2, Ч.2.1).
  ///
  /// [maxRecording] приезжает с сервера (`listen_seconds`), и это правильно: сколько человек
  /// говорит — суждение продукта. Но у записи есть физический низ: реплика сцены — это фраза,
  /// а не слово, и запись, закрытая раньше пятнадцати секунд, режет её на полуслове. Один
  /// неверный конфиг не должен уметь этого сделать, поэтому пол стоит здесь, в движке, а не в
  /// вызывающем — и второй раз на сервере, где число собирается.
  static const minMaxRecording = Duration(seconds: 15);

  /// ДО ПЕРВОГО ЗВУКА ЗАПИСЬ ЖИВЁТ НЕ МЕНЬШЕ ПЯТИ СЕКУНД (Ч.2.2).
  ///
  /// Между «нажал» и первым словом лежит вдох, взгляд на подсказку и решение. Тишина, закрывшая
  /// запись раньше, отдаёт на зачёт пустоту — то есть делает ход за человека.
  static const minWaitBeforeSilence = Duration(seconds: 5);

  /// Сколько запись реально живёт — см. [minMaxRecording].
  Duration get effectiveMaxRecording =>
      maxRecording < minMaxRecording ? minMaxRecording : maxRecording;

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
    Duration? maxRecording,
    double? echoCoverage,
    Duration? reopenGap,
    Duration? deadChannelWindow,
    int? deadChannelStrikes,
  }) => SpeechTurnConfig(
    silenceAfterSpeech: silenceAfterSpeech ?? this.silenceAfterSpeech,
    maxRecording: maxRecording ?? this.maxRecording,
    echoCoverage: echoCoverage ?? this.echoCoverage,
    reopenGap: reopenGap ?? this.reopenGap,
    deadChannelWindow: deadChannelWindow ?? this.deadChannelWindow,
    deadChannelStrikes: deadChannelStrikes ?? this.deadChannelStrikes,
  );
}

/// Чем кончился ОДИН ХОД — полная попытка, а не один `finalResult` плагина.
enum SpeechTurnOutcome {
  /// Полная попытка: человек договорил (тишина после последнего слова, сторож записи или тап
  /// «Готово»). [SpeechTurnResult.transcript] — ВСЯ склейка, а не первый частичный результат
  /// (Ч.2.3). Что в ней — судит карточка: мимо ответа — это честная ошибка, а не поломка канала.
  heard,

  /// Обрыв: канал упал ПОСЛЕ того, как человек начал говорить. Не ответ и не ошибка —
  /// «Не расслышали до конца — скажи ещё раз», журнал не пишется, вторая попытка (Ч.1.4).
  incomplete,

  /// Ни одного слова за всю запись: сторож [SpeechTurnConfig.maxRecording] закрыл её пустой.
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
/// переоткрывается, а закрывает запись не плагин, а ПРАВИЛО — и правил ровно четыре
/// (наряд SPEECH-2, Ч.2.1):
///
///   * тишина [SpeechTurnConfig.silenceAfterSpeech] ПОСЛЕ начала речи — договорил;
///   * тап «Готово» ([stop]) — договорил тем, что есть;
///   * сторож общей длины записи [SpeechTurnConfig.maxRecording] — хватит;
///   * ошибка канала.
///
/// ЧЕГО СРЕДИ НИХ НЕТ И БОЛЬШЕ НЕ БУДЕТ: закрытия ПО СОВПАДЕНИЮ. До этого наряда движок брал
/// снаружи предикат «в склейке узнан ключ» и закрывал попытку, как только тот срабатывал. На
/// телефоне 08.09 это выглядело так: человек говорит длинную реплику, на первом узнанном ключевом
/// слове микрофон закрывается и карточка ставит «верно». Фразу никто не дослушал и не оценил —
/// а тренажёр, который учит говорить фразами, засчитал слово. Зачёт теперь считает
/// [SpeechTurnResult.transcript] ЦЕЛИКОМ, и считает его карточка, после закрытия.
///
/// ЭХО-ЗАМОК (DAY-FIX-3, Ч.1.5): микрофон, открытый рядом с динамиком, слышит реплику собеседника.
/// Склейка, в которой узнана реплика роли (по [echoOf]), — не ответ: она выбрасывается, микрофон
/// переоткрывается, запись продолжается с чистого листа. Это НЕ закрытие по совпадению: эхо не
/// заканчивает ход, а выбрасывает чужой голос из склейки.
///
/// Движок ничего не знает о том, что такое правильный ответ: [echoOf] приходит снаружи, и это то,
/// что держит его вне слоя тренажёров. Таймеры — обычные [Timer], поэтому виджет-тест гоняет его
/// `pump(Duration)`, а юнит-тест — `fakeAsync`.
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
  Timer? _recordingTimer;

  /// Когда микрофон открылся — от него меряется сторож записи и пол [minWaitBeforeSilence].
  DateTime? _openedAt;

  /// Ручки текущего хода — их же дёргает [injectTranscript], чтобы подстановка шла ТОЙ ЖЕ дорогой,
  /// что и живой частичный результат, а не соседней.
  ValueChanged<String>? _onPartial;
  VoidCallback? _onSpeechStarted;
  bool _closing = false;
  bool _manualStop = false;

  bool get isListening => _turn != null && !_turn!.isCompleted;

  /// Всё, что услышано на этот момент — склейка плюс текущий кусок.
  String get transcript => [..._chunks, if (_partial.trim().isNotEmpty) _partial.trim()].join(' ').trim();

  /// Один ход. Возвращается ОДИН раз, когда запись закрыта по правилу — см. класс.
  ///
  /// [expected] и [contextualStrings] едут в плагин подсказкой; [echoOf] — «в склейке узнана
  /// реплика роли» (покрытие ≥ [SpeechTurnConfig.echoCoverage] считает вызывающий, движок только
  /// спрашивает).
  Future<SpeechTurnResult> listen({
    required List<String> expected,
    required String localeId,
    List<String> contextualStrings = const [],
    bool Function(String transcript)? echoOf,
    ValueChanged<String>? onPartial,
    VoidCallback? onSpeechStarted,
    ValueChanged<double>? onLevel,
  }) async {
    if (isListening) return _turn!.future;
    final turn = Completer<SpeechTurnResult>();
    _turn = turn;
    _chunks.clear();
    _partial = '';
    _firstWordAt = null;
    _openedAt = _now();
    _echoes = 0;
    _closing = false;
    _manualStop = false;
    _onPartial = onPartial;
    _onSpeechStarted = onSpeechStarted;
    diagnostics?.turnStarted();

    _armRecordingCap();

    // ЦИКЛ КРУТИТСЯ В СТОРОНЕ ОТ ХОДА, и это значит, что его исключение НЕ доходит до того, кто
    // ждёт исход (наряд DAY-GATE-1, Ч.0.2, находка F4): плагин, бросивший `PlatformException`,
    // оставлял `turn.future` незавершённым навсегда — карточка стояла на «Слушаю…», микрофона за
    // этим не было, и выхода тоже. Ход обязан закрыться, чем бы ни кончился цикл.
    unawaited(
      _loop(
        expected: expected,
        localeId: localeId,
        contextualStrings: contextualStrings,
        echoOf: echoOf,
        onPartial: onPartial,
        onSpeechStarted: onSpeechStarted,
        onLevel: onLevel,
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
    required bool Function(String)? echoOf,
    required ValueChanged<String>? onPartial,
    required VoidCallback? onSpeechStarted,
    ValueChanged<double>? onLevel,
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
        // Окно плагина — не правило, а потолок: запись закрывает движок. Плагину отдаётся столько,
        // сколько запись вообще может длиться, и его же пауза после речи.
        timeout: config.effectiveMaxRecording,
        pauseFor: config.silenceAfterSpeech,
        contextualStrings: contextualStrings,
        onLevel: onLevel,
        onPartial: (text) {
          if (turn.isCompleted || _closing) return;
          final trimmed = text.trim();
          if (trimmed.isEmpty) return;
          final changed = trimmed != _partial;
          _partial = trimmed;
          if (!changed) return;
          if (_firstWordAt == null) {
            _firstWordAt = _now();
            diagnostics?.phaseIs(SpeechPhase.listening);
            onSpeechStarted?.call();
          }
          _armSilence();
          diagnostics?.partial(transcript);
          // ЧАСТИЧНЫЙ РЕЗУЛЬТАТ — ТОЛЬКО НА ЭКРАН И В ЖУРНАЛ (Ч.2.3). На зачёт уходит финальная
          // склейка, и уходит она из [_settle], после закрытия записи. Ничего, что решает исход
          // хода, здесь произойти не может — в этом и был дефект, который наряд чинит.
          onPartial?.call(transcript);
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
          // ЭХО: в склейке узнана реплика собеседника. Склейка вон, микрофон переоткрыт — но
          // ЗАПИСЬ ПРОДОЛЖАЕТСЯ, и её сторож продолжает идти от того же тапа: эхо не добавляет
          // человеку времени и не отнимает его.
          if (echoOf != null && joined.isNotEmpty && echoOf(joined)) {
            _echoes++;
            _chunks.clear();
            _firstWordAt = null;
            _silenceTimer?.cancel();
            _silenceTimer = null;
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
            _settle(SpeechTurnOutcome.heard, phase: SpeechPhase.closedByTap);

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
              phase: SpeechPhase.closedByTap,
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

  /// ТИШИНА ПОСЛЕ РЕЧИ — и не раньше [SpeechTurnConfig.minWaitBeforeSilence] от начала записи
  /// (Ч.2.2). Пол здесь, а не в вызывающем: человек, сказавший первое слово на второй секунде,
  /// имеет право на паузу, и запись, закрытая на четвёртой, отдаёт на зачёт полфразы.
  void _armSilence() {
    _silenceTimer?.cancel();
    final since = _openedAt == null ? Duration.zero : _now().difference(_openedAt!);
    final floor = SpeechTurnConfig.minWaitBeforeSilence - since;
    final wait = config.silenceAfterSpeech > floor ? config.silenceAfterSpeech : floor;
    _silenceTimer = Timer(wait, () {
      if (!isListening) return;
      _settle(
        transcript.isEmpty ? SpeechTurnOutcome.silent : SpeechTurnOutcome.heard,
        phase: SpeechPhase.closedBySilence,
      );
    });
  }

  /// СТОРОЖ ОБЩЕЙ ДЛИНЫ ЗАПИСИ — от тапа, не от первого слова (Ч.2.1). Один на всю запись: эхо
  /// динамика, запинка и переоткрытие плагина его не двигают, иначе «сколько это может длиться»
  /// перестало бы иметь ответ.
  void _armRecordingCap() {
    _recordingTimer?.cancel();
    _recordingTimer = Timer(config.effectiveMaxRecording, () {
      if (!isListening) return;
      _settle(
        transcript.isEmpty ? SpeechTurnOutcome.silent : SpeechTurnOutcome.heard,
        phase: SpeechPhase.closedByTimeout,
      );
    });
  }

  /// ПОДСТАВИТЬ РАСПОЗНАННЫЙ ТЕКСТ В ОТКРЫТЫЙ ХОД — дев-дверь QA (наряд DAY-GATE-1, доработка Ч.3).
  ///
  /// Ведёт себя РОВНО как плагин, отдавший этот текст: обычная дорога частичного результата — первое
  /// слово, таймеры, журнал. Именно поэтому она здесь, а не в карточке: подстановка, сделанная мимо
  /// движка, проверяет карточку и НЕ проверяет ничего из того, что чинилось в Ч.0 — склейку,
  /// сторож, эхо-замок, стадии. На симуляторе микрофона нет, и без этой двери починки остаются
  /// теорией.
  ///
  /// И ТЕПЕРЬ ОНА ХОД НЕ ЗАКРЫВАЕТ (наряд SPEECH-2, Ч.2.1): как и живая речь, подставленный текст
  /// ждёт тишины, тапа или сторожа. Подстановка, закрывавшая ход совпадением, проверяла путь,
  /// которого больше нет.
  ///
  /// Возвращает false, когда хода нет: тогда вызывающий подставляет ответ по-старому, напрямую.
  /// Ничего не ослабляет — ни разрешений, ни окон: это тот же путь, по которому едет живая речь.
  bool injectTranscript(String text) {
    final turn = _turn;
    if (turn == null || turn.isCompleted || _closing) return false;
    final trimmed = text.trim();
    if (trimmed.isEmpty) return false;

    _partial = trimmed;
    if (_firstWordAt == null) {
      _firstWordAt = _now();
      diagnostics?.phaseIs(SpeechPhase.listening);
      _onSpeechStarted?.call();
    }
    _armSilence();
    diagnostics?.partial(transcript);
    _onPartial?.call(transcript);

    return true;
  }

  /// «Готово»: закрыть запись тем, что есть. Плагин отдаст свой последний кусок, и он войдёт в
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
        phase: SpeechPhase.closedByTap,
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
    diagnostics?.turnClosed(
      spokeFor: _firstWordAt == null ? null : _now().difference(_firstWordAt!),
      transcript: text,
    );
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
    _recordingTimer?.cancel();
    _silenceTimer = null;
    _recordingTimer = null;
  }
}
