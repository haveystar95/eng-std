import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import 'haptics.dart';

/// ЗВУК И ХАПТИКА — ОДИН СЕРВИС на все экраны (токен-лист 4к-3, 4е; наряд DAY-UI).
///
/// Четыре события и не больше: верно · неверно · этап закрыт · день закрыт. Каждое — сначала
/// хаптика, потом звук, из одного места, потому что они всегда идут вместе и разнести их по
/// экранам — значит однажды получить вибрацию без звука или наоборот.
///
/// Звуки НАШИ, сгенерированы `tool/gen_feedback_sounds.dart` в `assets/sounds/` и играются
/// нативно через `AudioServicesPlaySystemSound` (`ios/Runner/AppDelegate.swift`): этот путь сам
/// уважает беззвучный переключатель телефона — в тишине звука нет, хаптика остаётся (4к-3), — не
/// трогает аудиосессию синтезатора и не стоит pub-зависимости.
///
/// Выключатель «Звуки» в профиле — [soundsEnabled]; его выставляет провайдер настроек. Правило
/// «звук никогда поверх озвучки реплики» держит ВЫЗЫВАЮЩИЙ: карточка ждёт конца реплики
/// (`Pronouncer.speakText(awaitDone: true)`) и только потом зовёт [correct].
abstract final class AppFeedback {
  /// «Звуки» в профиле (4к-3). Flipped by the settings controller; the haptic never depends on it.
  static bool soundsEnabled = true;

  /// Handled in `ios/Runner/AppDelegate.swift`. Android has no handler and would throw
  /// [MissingPluginException]; that is caught below rather than guarded by a platform check,
  /// because the app is iOS-only and the failure mode that matters is «a sound must never take an
  /// answer down with it», not «which OS is this».
  @visibleForTesting
  static const MethodChannel channel = MethodChannel('com.denis.engstd/feedback_sound');

  /// The asset base names, which are also the identifiers the native side accepts.
  @visibleForTesting
  static const String correctSound = 'verdict_correct';
  @visibleForTesting
  static const String wrongSound = 'verdict_wrong';
  @visibleForTesting
  static const String stageClosedSound = 'stage_closed';
  @visibleForTesting
  static const String dayClosedSound = 'day_closed';


  /// «Верно» — короткий мягкий тон вверх, 120 мс. Haptic success.
  static void correct() {
    AppHaptics.success();
    _play(correctSound);
  }

  /// «Неверно» — низкий глухой, 160 мс: мягкое «нет», не сигнал ошибки. Haptic warning.
  static void wrong() {
    AppHaptics.warning();
    _play(wrongSound);
  }

  /// «Этап закрыт» — две ноты вверх, 240 мс. Haptic success.
  static void stageClosed() {
    AppHaptics.success();
    _play(stageClosedSound);
  }

  /// «День закрыт» — три ноты вверх, 300 мс. Haptic success один раз; конфетти нет.
  static void dayClosed() {
    AppHaptics.success();
    _play(dayClosedSound);
  }

  /// Fire and forget. A verdict is never held up by its own sound effect, and a sound that fails
  /// to play is not something the learner can act on — so the failure is swallowed here rather
  /// than surfaced anywhere.
  static void _play(String sound) {
    if (!soundsEnabled) return;
    channel.invokeMethod<void>('play', {'sound': sound}).catchError((Object e) {
      debugPrint('[feedback] $sound did not play: $e');
    });
  }
}

/// THE DAY SESSION'S SOUNDS (SESSION-1b′, item 5; the owner's files and decisions of 16.09) — the owner's six mp3 in
/// `assets/sounds/`, wired by the owner's map:
///
/// * [correct] / [miss] — the 30-4 reactions (choice, tiles) and the verdict of the voice and of the slot judge;
/// * [micOn] — a recording starts;
/// * [stageDone] — the stage summary (30-6) opens;
/// * [dayDone] — «Day done» ([dayCompleted] is reserved: the day summary screen comes in 1c);
/// * [ready] — the day is ready after waiting for its lesson to be built.
///
/// Played as iOS system sounds (`ios/Runner/AppDelegate.swift`, channel `com.denis.engstd/session_sounds`): [load]
/// decodes the six into memory when the session opens (leading silence cut, the asset files untouched) and
/// registers them, [release] frees them when it closes. A system sound follows the silent switch by itself, mixes
/// with the partner's line instead of cutting it and starts without the mp3 decoder's delay. «Sounds in the
/// session» off — nothing is registered at all.
abstract final class SessionSounds {
  @visibleForTesting
  static const MethodChannel channel = MethodChannel('com.denis.engstd/session_sounds');

  static const String correct = 'correct';
  static const String miss = 'miss';
  static const String micOn = 'mic_on';
  static const String stageDone = 'stage_done';
  static const String dayDone = 'day_done';
  static const String ready = 'ready';

  /// «Sounds in the session» — set by the settings controller.
  static bool get enabled => _enabled;
  static bool _enabled = true;
  static bool _loaded = false;

  static set enabled(bool on) {
    _enabled = on;
    if (!on && _loaded) unawaited(release());
  }

  /// The session opened: decode and register the six sounds — unless «Sounds in the session» is off.
  static Future<void> load() async {
    if (!_enabled || _loaded) return;
    _loaded = true;
    final count = await _invoke('load');
    debugPrint('[session-sounds] registered $count of 6');
  }

  /// The session closed: free the registered sounds.
  static Future<void> release() async {
    if (!_loaded) return;
    _loaded = false;
    await _invoke('release');
  }

  /// Play one of the six. Not registered (switch off, no session) — silence.
  static void play(String sound) {
    if (!_enabled || !_loaded) return;
    unawaited(_invoke('play', {'sound': sound}));
  }

  /// The reaction to an answer: «correct» or «miss».
  static void verdict({required bool correct}) => play(correct ? SessionSounds.correct : miss);

  /// Reserved for «Day done» — the day summary screen of 1c calls it.
  static void dayCompleted() => play(dayDone);

  /// A sound that fails is not something the learner can act on — swallowed, as with [AppFeedback].
  static Future<Object?> _invoke(String method, [Map<String, Object?>? arguments]) async {
    try {
      return await channel.invokeMethod<Object?>(method, arguments);
    } catch (e) {
      debugPrint('[session-sounds] $method: $e');
      return null;
    }
  }

  @visibleForTesting
  static void resetForTest() {
    _enabled = true;
    _loaded = false;
  }
}
