import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/line_audio.dart';
import '../../../data/pronouncer.dart';

/// ГОЛОС ОКНА ДНЯ — всё, что звучит в окне, — файлом сервера (DAY-UI-3: реплики обоих говорящих, фразы и
/// слова; окно докачивает их все при открытии), а системный синтез — замена, пока файла нет. Ключ файла —
/// текст строки (`LineAudioCache`), поэтому слово окна звучит тем же файлом, что и шит слова. Один экземпляр
/// держит аудиосессию, пока окно открыто.
///
/// Голос СЕССИИ дня — свой (`features/plan/session/session_voice.dart`, наряд SESSION-1b): у карточек звук
/// приходит адресом, а текст звука из карточки не всегда выводится.
class DayVoice {
  DayVoice({required LineAudioCache lines, required this.targetLang, Pronouncer? pronouncer})
    : _lines = lines,
      _pronouncer = pronouncer ?? Pronouncer(null, lines);

  final LineAudioCache _lines;
  final Pronouncer _pronouncer;
  final String targetLang;

  Future<void> warmUp() => _pronouncer.warmUp(targetLang: targetLang);

  /// Строки окна дня с адресами серверного голоса — слова, реплики «в разговоре», фразы и обе реплики
  /// каждого обмена (DAY-UI-3): запомнить и докачать. «Прослушать» без файла читает телефон.
  Future<void> preload(Iterable<LineAudioRef> lines) async {
    _lines.note(lines.map((l) => l.text));
    await _lines.preload(lines).catchError((Object e) => debugPrint('[day-voice] preload: $e'));
  }

  /// Слово, фраза или строка окна — файлом, если он на диске, иначе системным голосом; не ждём.
  Future<void> speak(String text) => _pronouncer.speakText(text, targetLang: targetLang);

  Future<void> stop() => _pronouncer.stop();

  Future<void> release() => _pronouncer.release();
}
