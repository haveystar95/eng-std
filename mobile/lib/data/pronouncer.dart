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
  // Cache what we've already pushed to the engine so a repeat speak() is ONE platform-channel call
  // (speak) instead of three (setLanguage + setSpeechRate + speak). The redundant round-trips were a
  // per-answer stall that landed on the card-transition animation (F20).
  String? _lastLocale;
  double? _lastRate;

  /// Normal and slowed speech rates. Slow (a touch under normal) is the listening card's
  /// «замедленно» replay — a beat slower so a learner can catch each sound (кадр 12g/12h).
  static const double _rateNormal = 0.45;
  static const double _rateSlow = 0.30;

  /// audio_url != null → play the file; audio_url == null → system TTS. [slow] drops the rate for a
  /// deliberate, easier-to-parse replay (listening exercise).
  Future<void> speak(Word word, {required String targetLang, bool slow = false}) async {
    if (word.audioUrl != null) {
      // TODO(audio-override): play the remote file once an audio player package
      // is added. No term carries audio_url yet (server audio is deferred), so
      // this falls through to system TTS and every term stays audible.
    }
    await _configureIosAudioSession();
    // Interrupt any still-playing utterance instead of queueing behind it — a growing TTS queue was
    // a per-card platform-thread load that got worse through a session (F20).
    await _tts.stop();
    final locale = ttsLocaleFor(targetLang);
    final rate = slow ? _rateSlow : _rateNormal;
    // Only touch the engine when a setting actually changes — otherwise speak() is one channel call,
    // not three, so it stops stalling the card transition (F20).
    if (_lastLocale != locale) {
      _lastLocale = locale;
      await _tts.setLanguage(locale);
    }
    if (_lastRate != rate) {
      _lastRate = rate;
      await _tts.setSpeechRate(rate);
    }
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
