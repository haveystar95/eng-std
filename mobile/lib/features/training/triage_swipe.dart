import 'dart:math' as math;

import 'package:flutter/widgets.dart';

import 'package:eng_std/theme/theme.dart';

import '../../data/models.dart';

/// Which side of the card the verdict sign sits on — always the side that
/// **stays on screen** as the card slides away (§4д: «свайп вправо — знак
/// слева, и наоборот»).
enum VerdictSignSide { left, right, bottom }

/// Pure geometry of the triage swipe gesture (§4д). Extracted from the widget so
/// the thresholds, the ±6° tilt cap and the commit rule are unit-testable
/// without pumping a gesture. Displacement is measured in logical pixels; the
/// commit [threshold] is 32 % of the card width, computed by the caller.
abstract final class TriageSwipe {
  /// Below this the drag has no direction yet (avoids a jittery sign at rest).
  static const deadzone = 12.0;

  /// The active verdict for a drag [d] (dominant axis past the deadzone), or
  /// null. Up = «Не уверен», right = «Знаю», left = «Не знаю» — the same order
  /// as the buttons (rule 21).
  static TriageVerdict? direction(Offset d) {
    if (d.dy.abs() > d.dx.abs()) {
      return d.dy < -deadzone ? TriageVerdict.unsure : null; // up only
    }
    if (d.dx > deadzone) return TriageVerdict.known; // right
    if (d.dx < -deadzone) return TriageVerdict.unknown; // left
    return null;
  }

  /// 0..1 progress toward the active [direction] given the commit [threshold].
  /// Drives the sign's opacity and the background tint (both ∝ displacement).
  static double progress(Offset d, double threshold) {
    final dir = direction(d);
    if (dir == null || threshold <= 0) return 0;
    final mag = dir == TriageVerdict.unsure ? d.dy.abs() : d.dx.abs();
    return (mag / threshold).clamp(0.0, 1.0);
  }

  /// Card tilt in **radians**, ∝ horizontal drag, capped at ±[AppMotion.swipeTilt]°.
  static double tiltRadians(Offset d, double threshold) {
    if (threshold <= 0) return 0;
    final frac = (d.dx / threshold).clamp(-1.0, 1.0);
    return frac * AppMotion.swipeTilt * math.pi / 180.0;
  }

  /// The side that stays in the screen — where the sign and the tint live.
  static VerdictSignSide signSide(TriageVerdict v) => switch (v) {
        TriageVerdict.known => VerdictSignSide.left, // card slides right
        TriageVerdict.unknown => VerdictSignSide.right, // card slides left
        TriageVerdict.unsure => VerdictSignSide.bottom, // card slides up
      };

  /// Commit when the drag passes the distance [threshold] OR is a fling faster
  /// than [AppMotion.swipeFlingVelocity] (600 px/s) in an established direction.
  static bool shouldCommit({
    required Offset drag,
    required double threshold,
    required Velocity velocity,
  }) {
    if (direction(drag) == null) return false;
    final past = progress(drag, threshold) >= 1.0;
    final fling = velocity.pixelsPerSecond.distance >= AppMotion.swipeFlingVelocity;
    return past || fling;
  }
}

/// Session-progress segments in the triage header (§2б «Сегменты сессии»): one
/// equal-width bar per card, height 3, gap 4 — the first [done] filled with ink,
/// the rest on track. Distinct from `InkSegments` (mastery, 3 densities): here
/// every segment is the same weight.
class SessionSegments extends StatelessWidget {
  const SessionSegments({
    super.key,
    required this.done,
    required this.total,
    this.height = 3,
    this.gap = 4,
  });

  final int done;
  final int total;
  final double height;
  final double gap;

  @override
  Widget build(BuildContext context) {
    if (total <= 0) return SizedBox(height: height);
    return SizedBox(
      height: height,
      child: Row(
        // Stretch so each zero-child ColoredBox fills the bar's height — without
        // this the default (center) collapses the segments to height 0.
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < total; i++) ...[
            if (i > 0) SizedBox(width: gap),
            Expanded(
              child: ColoredBox(
                color: i < done ? AppColors.ink : AppColors.track,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
