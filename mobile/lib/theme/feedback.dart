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
