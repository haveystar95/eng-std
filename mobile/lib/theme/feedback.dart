import 'dart:async';

import 'package:flutter/foundation.dart';

import '../data/audio_mixer.dart';
import 'haptics.dart';

/// THE APP'S SIX SOUNDS — the owner's files in `assets/sounds/`, and there are no others (work order FIX-1 §4).
///
/// Every screen that makes a sound makes one of these, through the one audio engine ([AudioMixer]) at the one level
/// ([AudioLevels.effect]), mixed over speech and never stopping it. The four generated tones the collections
/// trainer used to play (`verdict_correct.wav` and its three siblings) are gone: they were louder than any line of
/// the lesson and ended on a click, because a sound made by a generator ends where its last sample does.
///
/// * [correct] / [miss] — the verdict of an answer, wherever it is given;
/// * [micOn] — a recording starts;
/// * [stageDone] — a stage is closed;
/// * [dayDone] — a day is closed;
/// * [ready] — the day is ready after waiting for its lesson.
abstract final class AppSounds {
  static const String correct = 'correct';
  static const String miss = 'miss';
  static const String micOn = 'mic_on';
  static const String stageDone = 'stage_done';
  static const String dayDone = 'day_done';
  static const String ready = 'ready';

  static const List<String> all = [correct, miss, micOn, stageDone, dayDone, ready];

  /// The bundled file of a sound — the engine keeps it decoded under the same name.
  static String asset(String sound) => 'assets/sounds/$sound.mp3';

  /// The level a sound plays at: the family's ([AudioLevels.effect]), and «correct» quieter — its file is mixed
  /// hotter than the other five ([AudioLevels.correct]).
  static double levelOf(String sound) => sound == correct ? AudioLevels.correct : AudioLevels.effect;
}

/// SOUND AND HAPTIC OF THE COLLECTIONS TRAINER (token list 4к-3, 4е; work order DAY-UI).
///
/// Four events and no more: correct · wrong · stage closed · day closed. Each is the haptic first, then the sound,
/// from one place, because they always go together.
///
/// Since FIX-1 §4 the sounds are the app's own six ([AppSounds]) on the app's own engine — the same file at the same
/// level as in the day session. [load] decodes them when a trainer opens and [release] frees them when it closes;
/// a sound played without that is decoded on its first play, off the platform thread.
///
/// The «Sounds» switch in the profile is [soundsEnabled]; the settings provider sets it. The haptic never depends on
/// it.
abstract final class AppFeedback {
  /// «Звуки» in the profile (4к-3). Flipped by the settings controller; the haptic never depends on it.
  static bool soundsEnabled = true;

  /// A trainer opened: decode the six sounds ahead of their first play.
  static Future<void> load() async {
    if (!soundsEnabled) return;
    await AudioMixer.warmUp();
    await AudioMixer.loadEffects({for (final s in AppSounds.all) s: AppSounds.asset(s)});
  }

  /// A trainer closed: free the decoded sounds — unless the day session is holding them.
  static Future<void> release() => AudioMixer.releaseEffects(AppSounds.all);

  /// «Верно». Haptic success.
  static void correct() {
    AppHaptics.success();
    _play(AppSounds.correct);
  }

  /// «Неверно» — a soft «no», not an error signal. Haptic warning.
  static void wrong() {
    AppHaptics.warning();
    _play(AppSounds.miss);
  }

  /// «Этап закрыт». Haptic success.
  static void stageClosed() {
    AppHaptics.success();
    _play(AppSounds.stageDone);
  }

  /// «День закрыт». Haptic success once; no confetti.
  static void dayClosed() {
    AppHaptics.success();
    _play(AppSounds.dayDone);
  }

  /// Fire and forget: a verdict is never held up by its own sound effect.
  static void _play(String sound) {
    if (!soundsEnabled) return;
    unawaited(AudioMixer.playEffect(sound, AppSounds.asset(sound), level: AppSounds.levelOf(sound)));
  }
}

/// THE DAY SESSION'S SOUNDS (SESSION-1b′, item 5; the owner's files and decisions of 16.09) — [AppSounds], wired by
/// the owner's map:
///
/// * [correct] / [miss] — the 30-4 reactions (choice, tiles) and the verdict of the voice and of the slot judge;
/// * [micOn] — a recording starts;
/// * [stageDone] — a stage summary opens;
/// * [dayDone] — the day summary (30-7) opens;
/// * [ready] — the day is ready after waiting for its lesson to be built.
///
/// Short sounds of the app's one audio engine ([AudioMixer], SESSION-2a §1): [load] decodes the six when the session
/// opens (leading silence cut, a fade at the end, the asset files untouched), [release] frees them when it closes;
/// each plays at [AudioLevels.effect] over the speech and never stops it. «Sounds in the session» off — nothing is
/// loaded or played.
abstract final class SessionSounds {
  static const String correct = AppSounds.correct;
  static const String miss = AppSounds.miss;
  static const String micOn = AppSounds.micOn;
  static const String stageDone = AppSounds.stageDone;
  static const String dayDone = AppSounds.dayDone;
  static const String ready = AppSounds.ready;

  static const List<String> all = AppSounds.all;

  /// «Sounds in the session» — set by the settings controller.
  static bool get enabled => _enabled;
  static bool _enabled = true;
  static bool _loaded = false;

  static set enabled(bool on) {
    _enabled = on;
    if (!on && _loaded) unawaited(release());
  }

  /// The session opened: decode the six sounds — unless «Sounds in the session» is off.
  static Future<void> load() async {
    if (!_enabled || _loaded) return;
    _loaded = true;
    await AudioMixer.warmUp();
    final count = await AudioMixer.loadEffects({for (final s in all) s: AppSounds.asset(s)});
    debugPrint('[session-sounds] decoded $count of ${all.length}');
  }

  /// The session closed: free the decoded sounds.
  static Future<void> release() async {
    if (!_loaded) return;
    _loaded = false;
    await AudioMixer.releaseEffects(all);
  }

  /// Play one of the six. Not loaded (switch off, no session) — silence.
  static void play(String sound) {
    if (!_enabled || !_loaded) return;
    unawaited(AudioMixer.playEffect(sound, AppSounds.asset(sound), level: AppSounds.levelOf(sound)));
  }

  /// The reaction to an answer: «correct» or «miss».
  static void verdict({required bool correct}) => play(correct ? SessionSounds.correct : miss);

  /// «Day done» — the day summary opens.
  static void dayCompleted() => play(dayDone);

  @visibleForTesting
  static void resetForTest() {
    _enabled = true;
    _loaded = false;
  }
}
