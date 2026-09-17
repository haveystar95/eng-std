/// THE APP'S ONE AUDIO ENGINE (work order SESSION-2a §1) — the Dart side of `AudioMixer` in
/// `ios/Runner/AppDelegate.swift`.
///
/// Every sound the app plays from a file goes through here: a line, a phrase or a word from its server file
/// ([speak]) and the short sounds — verdict, microphone, stage, day ([playEffect]). They meet in one mixer at fixed
/// levels ([AudioLevels]); a short sound is mixed over the speech and never stops it. The system synthesiser (a line
/// with no file yet) is the one voice outside the engine: `AVSpeechSynthesizer` owns its own output.
library;

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// THE LEVELS — the only place they are written. Applied by the engine on every call, so a sound is as loud the
/// tenth time as the first.
abstract final class AudioLevels {
  /// Speech: lines, phrases, words.
  static const double speech = 1.0;

  /// A short sound of a stage (verdict, microphone, stage and day done, day ready) — clearly under the speech it
  /// may overlap.
  static const double effect = 0.38;
}

abstract final class AudioMixer {
  @visibleForTesting
  static const MethodChannel channel = MethodChannel('com.denis.engstd/audio_mixer');

  /// Play the file at [path] as speech and return when it has ACTUALLY ended: true — played to the end, false — cut
  /// (by [stopSpeech], by the next line, by an interruption of the audio session). [rate] — 0.85× for «Say it
  /// aloud», 0.75× for the slow tempo.
  ///
  /// Throws [PlatformException] when the file is gone or does not decode, [MissingPluginException] off iOS — the
  /// caller then reads the text with the system voice.
  static Future<bool> speak(String path, {double rate = 1.0}) async =>
      await channel.invokeMethod<bool>('playSpeech', {
        'path': path,
        'level': AudioLevels.speech,
        if (rate != 1.0) 'rate': rate,
      }) ??
      false;

  /// Cut the line that is sounding; whoever waits on [speak] gets false.
  static Future<void> stopSpeech() => _quietly('stopSpeech');

  /// Decode short sounds ahead of their first play: name → bundled asset. Answers how many decoded.
  static Future<int> loadEffects(Map<String, String> assets) async =>
      (await _quietly<int>('loadEffects', {'effects': assets})) ?? 0;

  /// Play a short sound over whatever sounds. Not loaded yet — decoded from [asset] on the spot. Fire and forget: a
  /// sound that fails is not something the learner can act on.
  static Future<void> playEffect(String name, String asset) =>
      _quietly('playEffect', {'name': name, 'asset': asset, 'level': AudioLevels.effect});

  /// Free decoded short sounds.
  static Future<void> releaseEffects(Iterable<String> names) => _quietly('releaseEffects', {'names': names.toList()});

  /// The screen that played is gone: cut the speech and stop the engine, so the audio session can be let go.
  static Future<void> pause() => _quietly('pause');

  static Future<T?> _quietly<T>(String method, [Map<String, Object?>? arguments]) async {
    try {
      return await channel.invokeMethod<T>(method, arguments);
    } on MissingPluginException {
      return null;
    } catch (e) {
      debugPrint('[audio-mixer] $method: $e');
      return null;
    }
  }
}
