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

  /// audio_url != null → play the file; audio_url == null → system TTS.
  Future<void> speak(Word word, {required String targetLang}) async {
    if (word.audioUrl != null) {
      // TODO(audio-override): play the remote file once an audio player package
      // is added. No term carries audio_url yet (server audio is deferred), so
      // this falls through to system TTS and every term stays audible.
    }
    await _tts.setLanguage(ttsLocaleFor(targetLang));
    await _tts.setSpeechRate(0.45);
    await _tts.speak(word.ttsHint ?? word.term);
  }

  Future<void> stop() => _tts.stop();
}
