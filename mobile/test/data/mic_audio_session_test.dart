import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_tts/flutter_tts.dart';

import 'package:eng_std/data/pronouncer.dart';

/// ОДИН МИКРОФОН, ОДНА АУДИОСЕССИЯ (наряд FIX-1, п. 3).
///
/// Форк `speech_to_text` НЕ трогает сессию, которая уже поднята в `playAndRecord` (`sessionOwnedByApp`), и трогает
/// её во всех остальных случаях: перекладывает категорию на каждое открытие микрофона и делает
/// `setActive(false, .notifyOthersOnDeactivation)` на каждом закрытии. Один ход голосом переоткрывает плагин
/// несколько раз — и на экране, поднявшем сессию как `playback`, эти перекладывания режут речь человека на
/// полуслове: «термин из двух слов пишется до первого».
///
/// ЛОВИТ: экран с микрофоном, поднимающий сессию без записи.
class _RecordingTts extends FlutterTts {
  IosTextToSpeechAudioCategory? category;
  List<IosTextToSpeechAudioCategoryOptions> options = const [];

  @override
  Future<dynamic> setIosAudioCategory(
    IosTextToSpeechAudioCategory category,
    List<IosTextToSpeechAudioCategoryOptions> options, [
    IosTextToSpeechAudioMode mode = IosTextToSpeechAudioMode.defaultMode,
  ]) async {
    this.category = category;
    this.options = options;
  }

  @override
  Future<dynamic> autoStopSharedSession(bool autoStop) async {}

  @override
  Future<dynamic> setSharedInstance(bool sharedInstance) async {}

  @override
  Future<dynamic> setLanguage(String language) async => 1;

  @override
  Future<dynamic> setSpeechRate(double rate) async => 1;

  @override
  Future<dynamic> setVolume(double volume) async => 1;

  @override
  Future<dynamic> speak(String text, {bool focus = false}) async => 1;

  @override
  Future<dynamic> stop() async => 1;

  @override
  void setCompletionHandler(void Function() callback) {}

  @override
  void setCancelHandler(void Function() callback) {}

  @override
  void setErrorHandler(Function(dynamic) handler) {}
}

void main() {
  // `FlutterTts`'s constructor hangs handlers on its channel — the binding has to exist first.
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => debugDefaultTargetPlatformOverride = TargetPlatform.iOS);
  tearDown(() => debugDefaultTargetPlatformOverride = null);

  test('a screen that records raises the session in playAndRecord with the recognizer\'s own options', () async {
    final tts = _RecordingTts();
    await Pronouncer(tts).warmUp(targetLang: 'en', recording: true);
    expect(tts.category, IosTextToSpeechAudioCategory.playAndRecord);
    expect(tts.options, containsAll(<IosTextToSpeechAudioCategoryOptions>[
      IosTextToSpeechAudioCategoryOptions.mixWithOthers,
      IosTextToSpeechAudioCategoryOptions.defaultToSpeaker,
      IosTextToSpeechAudioCategoryOptions.allowBluetooth,
      IosTextToSpeechAudioCategoryOptions.allowBluetoothA2DP,
    ]), reason: 'the same set `SpeechToTextPlugin.listenForSpeech` would set itself');
  });

  test('a screen that only speaks stays in playback — nothing records there', () async {
    final tts = _RecordingTts();
    await Pronouncer(tts).warmUp(targetLang: 'en');
    expect(tts.category, IosTextToSpeechAudioCategory.playback);
  });
}
