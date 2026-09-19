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

  /// «ВЕРНО» ИДЁТ ТИШЕ ОСТАЛЬНЫХ КОРОТКИХ (наряд FIX-1, доработка 19.09).
  ///
  /// Не вкусовая правка, а выравнивание по замеру шести файлов: у `correct.mp3` пик стоит в потолок (0 dBFS) и
  /// RMS −14,8 dB против −21,8 у `miss` и −25 у остальных четырёх. На общем уровне 0,38 он и звучал «очень
  /// громко» — громче не потому, что так решили, а потому, что файл сведён горячее. −7 dB возвращают его в семью;
  /// сам файл при этом не тронут (правило «шесть mp3 владельца не менять и не нормализовать»).
  static const double correct = 0.17;
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

  /// START THE ENGINE BEFORE ANYTHING HAS TO SOUND (FIX-1 §2). Starting it costs hundreds of milliseconds after a
  /// route change, and the screen that pays for it is the one that asked for the sound. Answers whether it runs.
  static Future<bool> warmUp() async => await _quietly<bool>('warmUp') ?? false;

  /// Decode short sounds ahead of their first play: name → bundled asset. Answers how many decoded.
  static Future<int> loadEffects(Map<String, String> assets) async =>
      (await _quietly<int>('loadEffects', {'effects': assets})) ?? 0;

  /// Play a short sound over whatever sounds. Not loaded yet — decoded from [asset] on the spot. Fire and forget: a
  /// sound that fails is not something the learner can act on.
  static Future<void> playEffect(String name, String asset, {double level = AudioLevels.effect}) =>
      _quietly('playEffect', {'name': name, 'asset': asset, 'level': level});

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
