import 'dart:async';
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;

import 'package:eng_std/data/audio_loader.dart';
import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/features/plan/session/session_voice.dart';
import 'package:eng_std/theme/feedback.dart';

/// ONE MIXER (work order SESSION-2a §1), as the Dart side can see it: the line waits for the engine's own answer,
/// a short sound is a separate call that never stops the speech, and «playing» ends with the sound.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const url = 'https://x/api/v1/plans/audio/A1';
  late Directory dir;
  late List<MethodCall> calls;
  late Completer<bool> speech;

  setUp(() {
    dir = Directory.systemTemp.createTempSync('audio_mixer_test');
    File(p.join(dir.path, AudioLoader.fileNameOf(url))).writeAsBytesSync([1, 2, 3]);
    calls = [];
    speech = Completer<bool>();
    SessionSounds.resetForTest();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(AudioMixer.channel, (call) async {
      calls.add(call);
      return switch (call.method) {
        'playSpeech' => speech.future,
        'loadEffects' => 6,
        _ => null,
      };
    });
  });

  tearDown(() {
    SessionSounds.resetForTest();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(AudioMixer.channel, null);
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  SessionVoice voice() => SessionVoice(lines: LineAudioCache(directory: dir), targetLang: 'en');

  const audio = CardAudio(ref: 'x3', url: url, voice: 'partner');

  // CATCHES: the verdict sound after a voice answer cutting the partner's reply (the phone, 17.09).
  test('a short sound does not stop speech: it is mixed over the line, and the line keeps waiting for its end', () async {
    final v = voice();
    final playing = v.play(audio, fallback: 'Here is the kitchen.', key: 'reply');
    await pumpEventQueue();
    expect(calls.map((c) => c.method), ['playSpeech']);
    expect((calls.single.arguments as Map)['level'], AudioLevels.speech);

    await SessionSounds.load();
    SessionSounds.verdict(correct: true);
    await pumpEventQueue();
    // `warmUp` — the engine is started before anything has to sound, off the platform thread (наряд FIX-1, п. 2).
    expect(calls.map((c) => c.method), ['playSpeech', 'warmUp', 'loadEffects', 'playEffect']);
    expect(calls.map((c) => c.method), isNot(contains('stopSpeech')), reason: 'a short sound never cuts the speech');
    expect((calls.last.arguments as Map)['level'], AudioLevels.effect);
    expect(v.playing.value, 'reply', reason: 'the line is still sounding');

    speech.complete(true);
    await playing;
    expect(v.playing.value, isNull);
  });

  // CATCHES: a bubble left «playing» after its line was cut, and «playing» ended by an estimate instead of the sound.
  test('«playing» ends when the engine says the line ended or was cut — not by a timer', () async {
    final v = voice();
    final playing = v.play(audio, fallback: 'Here is the kitchen.', key: 'reply');
    await Future<void>.delayed(const Duration(milliseconds: 200));
    expect(v.playing.value, 'reply', reason: 'no answer from the engine — still playing, however long it takes');

    speech.complete(false);
    await playing;
    expect(v.playing.value, isNull, reason: 'cut (an interruption, the next line) — the bubble stops playing at once');
  });
}
