import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/audio_loader.dart';
import '../../../data/audio_mixer.dart';
import '../../../data/line_audio.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/pronouncer.dart';

/// DAY SESSION VOICE (work order SESSION-1b) — a card's sound as the server file by its URL, and without a file —
/// the system voice reading the text the card shows.
///
/// Why the URL and not the text (as in the day window): on a card, an option's sound is not always its text. The
/// `phrase_slot` option «sharp» sounds as the whole phrase of its frame («The pain is sharp when he bends.»), and
/// the text of that file cannot be derived from the card. The files live in the same shared audio loader
/// (`AudioLoader`: disk, six downloads, retries) as the window's: a window opened before the session has already
/// put them on disk.
///
/// [playing] — what is sounding now (the key the caller gave): the «Listen» waves live off it. It is cleared when the
/// sound has actually ended or been cut — the engine answers [play] at that moment (SESSION-2a §1), never a timer.
class SessionVoice {
  SessionVoice({required LineAudioCache lines, required this.targetLang, Pronouncer? pronouncer})
    : _lines = lines,
      _pronouncer = pronouncer ?? Pronouncer(null, lines);

  final LineAudioCache _lines;
  final Pronouncer _pronouncer;
  final String targetLang;

  /// How long to wait for a file that is still downloading before the phone reads the text itself.
  static const Duration fileWait = Duration(milliseconds: 1500);

  /// Key of what is sounding now; null — silence.
  final ValueNotifier<Object?> playing = ValueNotifier(null);

  int _serial = 0;
  bool _released = false;

  /// The session records as well as speaks: the audio session is raised for both at once (SESSION-2a §1).
  Future<void> warmUp() => _pronouncer.warmUp(targetLang: targetLang, recording: true);

  /// Download the cards' sounds to disk — waits for nothing.
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

  /// Play [audio] and wait for it to end. No file — [fallback] in the system voice. [rate] — the file's tempo
  /// (0.85× for «Repeat aloud»); the system voice reads at its own tempo, unless [slowFallback] asks for its slow
  /// repeat (the partner's slow repeat of 33-6 and 34-6, SESSION-1c).
  Future<void> play(CardAudio? audio, {required String fallback, double rate = 1.0, Object? key, bool slowFallback = false}) async {
    if (_released) return;
    final serial = ++_serial;
    playing.value = key ?? audio?.ref ?? fallback;
    try {
      if (await _playFile(audio, rate)) return;
      if (fallback.trim().isEmpty) return;
      await _pronouncer.speakText(fallback, targetLang: targetLang, slow: slowFallback, awaitDone: true);
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
    // The engine stops before the session is let go: a session with running output refuses to deactivate.
    await AudioMixer.pause();
    await _pronouncer.release();
    playing.dispose();
  }
}
