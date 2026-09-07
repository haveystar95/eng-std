import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

/// ОТВЕТ ОС ПРО РАЗРЕШЕНИЯ, ПОДДЕЛАННЫЙ ДЛЯ ТЕСТА — наряд DAY-GATE-1, Ч.0.1.
///
/// `SpeechDiagnostics` спрашивает нативную дверь `com.denis.engstd/speech_probe`. В виджет-тесте
/// её нет, и вызов не отвечает вовсе — а раз он не отвечает, его таймаут остаётся висеть после
/// сноса дерева и падает на `!timersPending`. Поэтому КАЖДЫЙ тест, в котором на экране есть
/// микрофон, ставит эту заглушку — не ради удобства, а потому что иначе он меряет отсутствие
/// платформы, а не поведение экрана.
///
/// Возвращает изменяемое состояние: тест, которому нужен отказ, пишет в него до `pumpWidget`.
class MockSpeechProbe {
  MockSpeechProbe({this.recognition = 'granted', this.microphone = 'granted'});

  /// Слова платформы дословно: `granted` · `denied` · `restricted` · `not_determined`.
  String recognition;
  String microphone;
  bool recognizerSupported = true;
  bool recognizerAvailable = true;
  bool onDeviceSupported = true;
}

/// Поставить заглушку на всё время теста. Зовётся в `main()` один раз, до `setUp`-ов теста.
MockSpeechProbe mockSpeechProbe() {
  const channel = MethodChannel('com.denis.engstd/speech_probe');
  final probe = MockSpeechProbe();

  setUp(() {
    probe
      ..recognition = 'granted'
      ..microphone = 'granted'
      ..recognizerSupported = true
      ..recognizerAvailable = true
      ..onDeviceSupported = true;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      channel,
      (call) async => call.method != 'probe'
          ? null
          : <Object?, Object?>{
              'recognition': probe.recognition,
              'microphone': probe.microphone,
              'recognizer_supported': probe.recognizerSupported,
              'recognizer_available': probe.recognizerAvailable,
              'on_device_supported': probe.onDeviceSupported,
            },
    );
  });

  tearDown(
    () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null),
  );

  return probe;
}
