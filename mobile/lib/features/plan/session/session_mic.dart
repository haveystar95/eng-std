import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/speech/speech_diagnostics.dart';
import '../../../data/speech/speech_recognizer.dart';
import '../../../data/speech/speech_turn.dart';

/// Состояние микрофона карточки (кадр 30-3).
enum MicState {
  /// Покой — «тап — говорить».
  idle,

  /// Запись идёт: пусто (курсор) или текст идёт (живая строка).
  listening,

  /// Услышал — зачёт: кнопка шалфеем с галкой.
  heard,

  /// Не расслышал (или сказано не то) — «ещё раз».
  missed,

  /// Микрофона нет: нет разрешения или распознаватель не поднялся — экран «Нужен микрофон».
  unavailable,
}

/// Чем кончилась одна запись.
typedef MicTurn = ({String transcript, SpeechTurnOutcome outcome});

/// МИКРОФОН КАРТОЧКИ СЕССИИ (наряд SESSION-1b, кадр 30-3) — поверх существующего движка записи
/// ([SpeechTurn]: запись только по нажатию, тишина 2 с после речи закрывает запись, сторож длины, склейка
/// кусков распознавателя). Здесь только состояние кнопки и живая строка; зачёт считает карточка по
/// [onTurn] и отвечает [settle].
///
/// Отладочная дверь [submitDebug] — поле «что услышал» debug-сборки на симуляторе, где микрофона нет:
/// текст идёт той же дорогой, что финальный транскрипт живой записи.
class SessionMic extends ChangeNotifier {
  SessionMic({
    required this._recognizer,
    this._diagnostics,
    required this.localeId,
    required this.expected,
    this.contextualStrings = const [],
  });

  final SpeechRecognizer _recognizer;
  final SpeechDiagnostics? _diagnostics;

  /// Локаль распознавания — `en_US`.
  final String localeId;

  /// Что должно прозвучать — подсказка движку и эталон живой строки.
  final String expected;

  /// Слова карточки — `SFSpeechRecognitionRequest.contextualStrings`.
  final List<String> contextualStrings;

  /// Запись закрыта — карточка судит и зовёт [settle].
  void Function(MicTurn turn)? onTurn;

  MicState _state = MicState.idle;
  String _partial = '';
  double _level = 0;
  bool _blockedInSettings = false;
  bool _closed = false;
  SpeechTurn? _turn;
  bool _disposed = false;
  Timer? _debugClose;

  MicState get state => _state;

  /// Запись закрыта и ждёт вердикта (судья окна отвечает по сети): строка замерла, кнопка не пульсирует.
  bool get closed => _closed;

  /// Что услышано на этот момент (живая строка) или итог записи.
  String get partial => _partial;

  /// Громкость 0…1 — только пока идёт запись.
  double get level => _level;

  /// Разрешение отказано насовсем: помогут только «Настройки».
  bool get blockedInSettings => _blockedInSettings;

  bool get isListening => _state == MicState.listening;

  /// Тап по кнопке: покой / «ещё раз» — начать запись; запись — закрыть её тем, что услышано.
  Future<void> tap() async {
    switch (_state) {
      case MicState.listening:
        if (_closed) return;
        await _turn?.stop();
      case MicState.idle || MicState.missed:
        await _listen();
      case MicState.heard || MicState.unavailable:
        break;
    }
  }

  Future<void> _listen() async {
    _partial = '';
    _level = 0;
    _closed = false;
    _set(MicState.listening);
    final turn = SpeechTurn(_recognizer, diagnostics: _diagnostics);
    _turn = turn;
    SpeechTurnResult result;
    try {
      result = await turn.listen(
        expected: [if (expected.trim().isNotEmpty) expected],
        localeId: localeId,
        contextualStrings: contextualStrings,
        onPartial: (text) {
          if (_turn != turn) return;
          _partial = text;
          _notify();
        },
        // iOS отдаёт децибелы примерно от −2 до 10.
        onLevel: (db) {
          if (_turn != turn) return;
          _level = ((db + 2) / 12).clamp(0.0, 1.0);
          _notify();
        },
      );
    } catch (e) {
      result = const SpeechTurnResult(SpeechTurnOutcome.unavailable);
    }
    if (_disposed || _turn != turn) return;
    _turn = null;
    _level = 0;
    if (result.outcome == SpeechTurnOutcome.unavailable) {
      await _refreshPermission();
      if (_disposed) return;
      _set(MicState.unavailable);
      return;
    }
    _partial = result.transcript;
    _closed = true;
    _notify();
    onTurn?.call((transcript: result.transcript, outcome: result.outcome));
  }

  /// Вердикт карточки по закрытой записи.
  void settle({required bool accepted}) {
    _closed = false;
    if (accepted) {
      _set(MicState.heard);
    } else {
      _set(MicState.missed);
    }
  }

  /// Вернуть кнопку в покой (новая попытка после отказа судьи и т. п.).
  void reset() {
    _partial = '';
    _closed = false;
    _set(MicState.idle);
  }

  /// «Разрешить» на экране «Нужен микрофон»: спросить систему ещё раз.
  Future<bool> askAgain() async {
    final ok = await _recognizer.prepare();
    if (_disposed) return ok;
    if (ok) {
      _blockedInSettings = false;
      _set(MicState.idle);
    } else {
      await _refreshPermission();
    }
    return ok;
  }

  Future<void> _refreshPermission() async {
    final probe = await _diagnostics?.refresh(localeId);
    _blockedInSettings = probe?.blockedInSettings ?? false;
  }

  /// DEBUG-ПОЛЕ «ЧТО УСЛЫШАЛ» (только debug-сборка): текст — как финальный транскрипт записи.
  void submitDebug(String text) {
    if (!kDebugMode) return;
    final heard = text.trim();
    if (heard.isEmpty || _state == MicState.heard) return;
    final turn = _turn;
    _turn = null;
    if (turn != null) unawaited(turn.cancel());
    _debugClose?.cancel();
    _partial = heard;
    _level = 0;
    _closed = false;
    _set(MicState.listening);
    // Строка успевает показаться живой, потом «замирает» и уходит на зачёт — как после тишины.
    _debugClose = Timer(const Duration(milliseconds: 400), () {
      if (_disposed) return;
      _closed = true;
      _notify();
      onTurn?.call((transcript: heard, outcome: SpeechTurnOutcome.heard));
    });
  }

  void _set(MicState s) {
    if (_disposed) return;
    _state = s;
    notifyListeners();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _debugClose?.cancel();
    final turn = _turn;
    _turn = null;
    if (turn != null) unawaited(turn.cancel());
    super.dispose();
  }
}
