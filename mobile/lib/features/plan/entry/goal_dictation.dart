import 'dart:async';

import 'package:flutter/widgets.dart';

import '../../../data/speech/speech_recognizer.dart';
import '../../../data/speech/speech_turn.dart';
import 'entry_state.dart';

/// ПЕЧАТЬ ГОЛОСОМ В ПОЛЕ ЦЕЛИ (кадры 22-1, 22-1c; наряд PLAN-UI-3 §2).
///
/// Голос — это печать, а не «запись → распознавание»: частичные результаты [SpeechTurn] ложатся в
/// поле по мере речи, последнее слово ещё уточняется (экран красит его серым), волна идёт от
/// настоящей громкости. Состояния «распознаю…» нет: движок отдаёт текст, пока человек говорит, и
/// закрывает запись сам — тишина 2 с после речи ([SpeechTurnConfig.silenceAfterSpeech]) или
/// повторный тап ([toggle]).
///
/// Уже написанное руками не стирается: сказанное дописывается после него через пробел.
class GoalDictation extends ChangeNotifier {
  GoalDictation({required SpeechRecognizer recognizer, required this.field, SpeechTurnConfig? config})
    : _turn = SpeechTurn(recognizer, config: config ?? defaultConfig);

  /// Цель — не реплика: человек рассказывает ситуацию, и сторож в 15 с резал бы рассказ. Минута
  /// — потолок записи; закрывает её по-прежнему тишина.
  static const defaultConfig = SpeechTurnConfig(maxRecording: Duration(seconds: 60));

  final TextEditingController field;
  final SpeechTurn _turn;

  EntryMicState _state = EntryMicState.idle;
  EntryMicState get state => _state;

  /// Громкость 0…1 — из `soundLevel` плагина (дБ −2…10 на iOS).
  double _level = 0;
  double get level => _level;

  /// Секунды записи — «0:07 · говори, я слушаю».
  int _seconds = 0;
  int get seconds => _seconds;

  String _base = '';
  Timer? _clock;
  bool _disposed = false;

  bool get listening => _state == EntryMicState.listening;

  /// Сказанное за эту запись — то, что экран печатает, с серым последним словом.
  String _heard = '';
  String get heard => _heard;

  /// Текст до записи, к которому дописывается голос.
  String get base => _base;

  /// Тап по микрофону: открыть запись или закрыть её тем, что услышано.
  Future<void> toggle({required String localeId}) async {
    if (listening) {
      await _turn.stop();

      return;
    }
    _base = field.text.trimRight();
    _heard = '';
    _level = 0;
    _seconds = 0;
    _set(EntryMicState.listening);
    _clock = Timer.periodic(const Duration(seconds: 1), (_) {
      _seconds++;
      _notify();
    });

    final result = await _turn.listen(
      expected: const [],
      localeId: localeId,
      onPartial: (text) {
        _heard = text.trim();
        _write();
        _notify();
      },
      onLevel: (db) {
        _level = ((db + 2) / 12).clamp(0.0, 1.0);
        _notify();
      },
    );
    _clock?.cancel();
    if (_disposed) return;
    _heard = result.transcript.trim();
    _level = 0;
    _write();
    _set(field.text.trim().isEmpty ? EntryMicState.idle : EntryMicState.done);
  }

  /// Тап по истории или правка руками — голос больше ни при чём.
  void reset() {
    if (listening) unawaited(_turn.cancel());
    _clock?.cancel();
    _heard = '';
    _set(EntryMicState.idle);
  }

  void _write() {
    final text = _heard.isEmpty ? _base : (_base.isEmpty ? _heard : '$_base $_heard');
    field.value = TextEditingValue(text: text, selection: TextSelection.collapsed(offset: text.length));
  }

  void _set(EntryMicState next) {
    _state = next;
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _clock?.cancel();
    unawaited(_turn.cancel());
    super.dispose();
  }
}
