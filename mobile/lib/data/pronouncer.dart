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

  /// Synthesizer cold-start priming: done once, and never left half-way (see [_endPriming]).
  bool _primed = false;
  bool _priming = false;

  /// Normal and slowed speech rates. Slow (a touch under normal) is the listening card's
  /// «замедленно» replay — a beat slower so a learner can catch each sound (кадр 12g/12h).
  static const double _rateNormal = 0.45;
  static const double _rateSlow = 0.30;

  /// Open the iOS audio session ONCE for the whole training session and push the engine's language
  /// and rate before any card needs them.
  ///
  /// The important part is [FlutterTts.autoStopSharedSession]`(false)`. flutter_tts defaults it to
  /// true, and its iOS side then runs this after EVERY utterance:
  ///
  ///     if shouldDeactivateAndNotifyOthers(session) && autoStopSharedSession {
  ///       try session.setActive(false, options: .notifyOthersOnDeactivation)
  ///     }
  ///
  /// — and `shouldDeactivateAndNotifyOthers` is true precisely because we ask for `duckOthers`.
  /// `setActive(false, .notifyOthersOnDeactivation)` is a blocking system teardown, and it was the
  /// ~600 ms freeze that hit the trainer once per spoken word: measured on the device, the stalls
  /// landed on exactly the listening cards and vanished on the rest when auto-pronounce was turned
  /// off. It is also why the first word came out clipped — the next utterance started while the
  /// session was still coming back up.
  ///
  /// So: raise the session once here, keep it up for the session, and drop it once in [release].
  /// Ducking now lasts for the whole training session instead of flickering per word, which is
  /// also the better behaviour for someone training with music on.
  Future<void> warmUp({required String targetLang}) async {
    await _configureIosAudioSession();
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      await _tts.autoStopSharedSession(false);
      await _tts.setSharedInstance(true);
    }
    final locale = ttsLocaleFor(targetLang);
    if (_lastLocale != locale) {
      _lastLocale = locale;
      await _tts.setLanguage(locale);
    }
    if (_lastRate != _rateNormal) {
      _lastRate = _rateNormal;
      await _tts.setSpeechRate(_rateNormal);
    }
    await _prime();
  }

  /// Speak one silent utterance so the synthesizer's own cold start — loading the voice and opening
  /// the audio route — is paid here rather than eating the beginning of the first real word. Keeping
  /// the audio session up (see [warmUp]) fixed the per-word freeze but not this: the very first
  /// utterance after launch still came out with only its tail audible.
  Future<void> _prime() async {
    if (_primed) return;
    _primed = true;
    _priming = true;
    await _tts.setVolume(0);
    await _tts.speak('a');
    await Future<void>.delayed(const Duration(milliseconds: 400));
    await _endPriming();
  }

  /// Restore the volume. Called either when priming finishes or the moment a REAL utterance is
  /// requested, so a card can never be pronounced silently if it beats the 400 ms.
  Future<void> _endPriming() async {
    if (!_priming) return;
    _priming = false;
    await _tts.setVolume(1);
  }

  /// audio_url != null → play the file; audio_url == null → system TTS. [slow] drops the rate for a
  /// deliberate, easier-to-parse replay (listening exercise).
  Future<void> speak(Word word, {required String targetLang, bool slow = false}) async {
    if (word.audioUrl != null) {
      // TODO(audio-override): play the remote file once an audio player package
      // is added. No term carries audio_url yet (server audio is deferred), so
      // this falls through to system TTS and every term stays audible.
    }
    await _configureIosAudioSession();
    await _endPriming(); // a real word must never land while the priming volume is still 0
    // Interrupt any still-playing utterance instead of queueing behind it — a growing TTS queue was
    // a per-card platform-thread load that got worse through a session (F20). This is
    // `stopSpeaking(.immediate)`, which raises `didCancel`, NOT `didFinish` — so it never trips the
    // session teardown that [warmUp] disables.
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

  /// Give the audio session back when the training session ends. This is the one place the
  /// expensive `setActive(false)` runs — on a screen the user is already leaving, where a stall
  /// costs nothing — instead of after every spoken word.
  Future<void> release() async {
    await _tts.stop();
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      _audioSessionReady = false;
      // Hand the plugin back to its DEFAULT self-deactivating behaviour BEFORE dropping the
      // session. `autoStopSharedSession` lives on the plugin's native singleton, so leaving it
      // false would apply to every other speaker in the app — the collection screen owns a
      // Pronouncer with no lifecycle of its own, and its word button would then leave the session
      // active, ducking other apps' audio indefinitely. Holding the session is a TRAINING-SCREEN
      // behaviour and must not outlive the training screen. It also means the realtime dialog,
      // which raises its own `playAndRecord` session, never meets a session we are still holding.
      await _tts.autoStopSharedSession(true);
      await _tts.setSharedInstance(false);
    }
  }
}
