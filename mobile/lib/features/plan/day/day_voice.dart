import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/line_audio.dart';
import '../../../data/pronouncer.dart';

/// THE DAY WINDOW'S VOICE — everything the window says is a server file (DAY-UI-3: both speakers' lines,
/// phrases and words; the window fetches them all on open), and system synthesis stands in while a file is
/// missing. A file is keyed by the line's text (`LineAudioCache`), so a word in the window plays the same file
/// as the word sheet. One instance holds the audio session while the window is open.
///
/// The day SESSION has its own voice (`features/plan/session/session_voice.dart`, work order SESSION-1b):
/// a card's sound arrives as a URL, and the sound's text cannot always be derived from the card.
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
