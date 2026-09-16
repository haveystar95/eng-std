import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_settings.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/theme/feedback.dart';

/// «Sounds in the session» (SESSION-1b′, item 5; the owner's decisions of 16.09) — on by default, stored on the
/// device, independent of «Sounds»; off — the session registers none of the six sounds at all.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late AppDatabase db;
  final calls = <String>[];

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    calls.clear();
    SessionSounds.resetForTest();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(SessionSounds.channel, (call) async {
      calls.add(call.method == 'play' ? 'play ${(call.arguments as Map)['sound']}' : call.method);
      return null;
    });
  });
  tearDown(() async {
    SessionSounds.resetForTest();
    AppFeedback.soundsEnabled = true;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(SessionSounds.channel, null);
    await db.close();
  });

  Future<AppSettings> load() async {
    final container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
    addTearDown(container.dispose);
    return container.read(appSettingsProvider.future);
  }

  test('nothing stored — on', () async {
    SessionSounds.enabled = false;
    expect((await load()).sessionSoundsEnabled, isTrue);
    expect(SessionSounds.enabled, isTrue, reason: 'loading the settings sets the flag');
  });

  // CATCHES: a switch that forgets itself on the next launch, and one that drags «Sounds» along with it.
  test('switched off — stays off after a relaunch, «Sounds» untouched', () async {
    final container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
    await container.read(appSettingsProvider.future);
    await container.read(appSettingsProvider.notifier).setSessionSoundsEnabled(false);
    expect(SessionSounds.enabled, isFalse);
    expect(container.read(appSettingsProvider).value?.sessionSoundsEnabled, isFalse);
    container.dispose();

    SessionSounds.resetForTest();
    final relaunched = await load();
    expect(relaunched.sessionSoundsEnabled, isFalse);
    expect(relaunched.soundsEnabled, isTrue);
    expect(SessionSounds.enabled, isFalse);
    expect(AppFeedback.soundsEnabled, isTrue);
  });

  // CATCHES: sounds registered although the owner switched them off, a play before the session loaded them, a
  // switch-off in the middle of a session that leaves them registered.
  group('SessionSounds', () {
    test('a session registers the six sounds, plays by name and releases them', () async {
      SessionSounds.play(SessionSounds.correct);
      expect(calls, isEmpty, reason: 'nothing is registered before the session opens');
      await SessionSounds.load();
      SessionSounds.verdict(correct: false);
      SessionSounds.play(SessionSounds.stageDone);
      await pumpEventQueue();
      await SessionSounds.release();
      SessionSounds.play(SessionSounds.ready);
      await pumpEventQueue();
      expect(calls, ['load', 'play miss', 'play stage_done', 'release']);
    });

    test('switched off — nothing is registered and nothing sounds', () async {
      SessionSounds.enabled = false;
      await SessionSounds.load();
      SessionSounds.verdict(correct: true);
      await pumpEventQueue();
      expect(calls, isEmpty);
    });

    test('switched off during a session — the sounds are released', () async {
      await SessionSounds.load();
      SessionSounds.enabled = false;
      await pumpEventQueue();
      SessionSounds.play(SessionSounds.micOn);
      await pumpEventQueue();
      expect(calls, ['load', 'release']);
    });

    // CATCHES: a name the native side does not load, a file missing from the bundle — a silent session.
    test('the six names are the owner\'s mp3 in the bundle and the native side loads each', () {
      const names = [
        SessionSounds.correct,
        SessionSounds.miss,
        SessionSounds.micOn,
        SessionSounds.stageDone,
        SessionSounds.dayDone,
        SessionSounds.ready,
      ];
      expect(names, ['correct', 'miss', 'mic_on', 'stage_done', 'day_done', 'ready']);
      final native = File('ios/Runner/AppDelegate.swift').readAsStringSync();
      expect(File('pubspec.yaml').readAsStringSync(), contains('- assets/sounds/'));
      for (final name in names) {
        expect(File('assets/sounds/$name.mp3').existsSync(), isTrue, reason: name);
        expect(native, contains('"$name"'), reason: '$name is in the native list');
      }
    });
  });
}
