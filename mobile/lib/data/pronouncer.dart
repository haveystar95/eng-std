import 'package:flutter/foundation.dart' show defaultTargetPlatform, TargetPlatform;
import 'package:flutter_tts/flutter_tts.dart';

import 'models.dart';
import 'languages.dart';

/// Speaks a term out loud.
///
/// System TTS is the default: it is free, offline (so a session works with no
/// network), instant, and covers 100% of terms — including user words that will
/// never have server audio. `audioUrl` is an optional override, applied
/// point-wise only where it pays off (curated phrases, hard pronunciations);
/// `ttsHint` fixes the most common system-synth misreadings ("ATM" → "A T M")
/// without generating any audio.
class Pronouncer {
  Pronouncer([FlutterTts? tts]) : _tts = tts ?? FlutterTts();

  final FlutterTts _tts;
  bool _audioSessionReady = false;

  /// audio_url != null → play the file; audio_url == null → system TTS.
  Future<void> speak(Word word, {required String targetLang}) async {
    if (word.audioUrl != null) {
      // TODO(audio-override): play the remote file once an audio player package
      // is added. No term carries audio_url yet (server audio is deferred), so
      // this falls through to system TTS and every term stays audible.
    }
    await _configureIosAudioSession();
    await _tts.setLanguage(ttsLocaleFor(targetLang));
    await _tts.setSpeechRate(0.45);
    await _tts.speak(word.ttsHint ?? word.term);
  }

  /// Pronunciation is intentional media, not a notification, so it must play through the iOS
  /// hardware silent switch. The default audio-session category respects the mute switch (silent
  /// → no sound); `.playback` overrides it, the way media/player apps do (device-batch F10). Set
  /// once, iOS-only (`defaultTargetPlatform` avoids importing dart:io so web/preview still builds).
  Future<void> _configureIosAudioSession() async {
    if (_audioSessionReady || defaultTargetPlatform != TargetPlatform.iOS) return;
    _audioSessionReady = true;
    await _tts.setIosAudioCategory(
      IosTextToSpeechAudioCategory.playback,
      [IosTextToSpeechAudioCategoryOptions.duckOthers],
      IosTextToSpeechAudioMode.defaultMode,
    );
  }

  Future<void> stop() => _tts.stop();
}
