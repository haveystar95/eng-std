import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/audio_loader.dart';
import '../../../data/line_audio.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/pronouncer.dart';

/// ГОЛОС СЕССИИ ДНЯ (наряд SESSION-1b) — звук карточки файлом сервера по его АДРЕСУ, а без файла —
/// системным голосом по тексту, который карточка показывает.
///
/// Почему адрес, а не текст (как у окна дня): у карточки звук варианта — это не всегда его текст. Вариант
/// `phrase_slot` «sharp» звучит всей фразой своего каркаса («The pain is sharp when he bends.»), и
/// текст этого файла из карточки не выводится. Файлы лежат в том же общем загрузчике звука
/// (`AudioLoader`: диск, шесть докачек, повторы), что и у окна: окно, открытое перед сессией, уже
/// положило их на диск.
///
/// [playing] — что звучит сейчас (ключ, который дал вызывающий): по нему живут волны «прослушать».
class SessionVoice {
  SessionVoice({required LineAudioCache lines, required this.targetLang, Pronouncer? pronouncer})
    : _lines = lines,
      _pronouncer = pronouncer ?? Pronouncer(null, lines);

  final LineAudioCache _lines;
  final Pronouncer _pronouncer;
  final String targetLang;

  /// Сколько ждать файл, который ещё качается, прежде чем читать телефоном.
  static const Duration fileWait = Duration(milliseconds: 1500);

  /// Ключ того, что звучит сейчас; null — тишина.
  final ValueNotifier<Object?> playing = ValueNotifier(null);

  int _serial = 0;
  bool _released = false;

  Future<void> warmUp() => _pronouncer.warmUp(targetLang: targetLang);

  /// Докачать звуки карточек на диск — ничего не ждёт.
  Future<void> prepare(Iterable<CardAudio> audios) async {
    await _lines.load();
    final loader = _lines.loader;
    if (loader == null) return;
    final urls = {
      for (final a in audios)
        if (a.url case final url?) LineAudioCache.normalizeUrl(url),
    };
    unawaited(loader.prefetch(urls).catchError((Object e) => debugPrint('[session-voice] prefetch: $e')));
  }

  /// Сыграть [audio] и дождаться конца. Нет файла — [fallback] системным голосом. [rate] — темп файла
  /// (0.85× у «Повтори вслух»); системный голос читает своим темпом.
  Future<void> play(CardAudio? audio, {required String fallback, double rate = 1.0, Object? key}) async {
    if (_released) return;
    final serial = ++_serial;
    playing.value = key ?? audio?.ref ?? fallback;
    try {
      if (await _playFile(audio, rate)) return;
      if (fallback.trim().isEmpty) return;
      await _pronouncer.speakText(fallback, targetLang: targetLang, awaitDone: true);
    } finally {
      if (serial == _serial && !_released) playing.value = null;
    }
  }

  Future<bool> _playFile(CardAudio? audio, double rate) async {
    final url = audio?.url;
    if (url == null) return false;
    await _lines.load();
    final loader = _lines.loader;
    if (loader == null) return false;
    final normalized = LineAudioCache.normalizeUrl(url);
    var path = loader.cachedPath(normalized);
    if (path == null) {
      final load = await loader
          .load(normalized)
          .timeout(fileWait, onTimeout: () => const AudioLoad.failed(null, 'still loading'))
          .catchError((Object e) => AudioLoad.failed(null, '$e'));
      path = load.path;
    }
    if (path == null) return false;
    return _lines.playFile(path, rate: rate);
  }

  Future<void> stop() async {
    _serial++;
    playing.value = null;
    await _lines.stop();
    await _pronouncer.stop();
  }

  Future<void> release() async {
    _released = true;
    _serial++;
    await _lines.stop();
    await _pronouncer.release();
    playing.dispose();
  }
}
