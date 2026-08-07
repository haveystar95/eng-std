import 'package:flutter/services.dart';

/// Haptic helper (`tokens.html` §4е). Three intents, called by the motion specs:
///
/// * [light]   — чип встаёт в строку сборки (§4е «Чип в строку сборки»).
/// * [success] — верный вариант / принят ответ с опечаткой.
/// * [warning] — неверный вариант.
///
/// Flutter core exposes impact/selection feedback but not iOS notification
/// haptics (success/warning/error). The mapping below gives three distinct
/// feels; swapping to real `UINotificationFeedbackGenerator` via a platform
/// channel is a possible upgrade (noted in ROADMAP), not a blocker.
abstract final class AppHaptics {
  static void light() => HapticFeedback.selectionClick();

  static void success() => HapticFeedback.lightImpact();

  static void warning() => HapticFeedback.mediumImpact();
}
