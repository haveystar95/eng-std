import 'package:flutter/painting.dart';

import 'colors.dart';

/// The ink-density pattern (`tokens.html` §4) — the only way to show "degree"
/// in a colourless system (rule 03). Verdict colours never participate here.
///
/// * [filled]   — подтверждено упражнениями · выполненный день · активный столбик.
/// * [halftone] — отмечено знакомым · слабый день. Не для дней календаря.
/// * [outline]  — в работе / не разобрано · пустой день (волосяная линия).
enum InkDensity { filled, halftone, outline }

/// Concrete values for the three grades. Rendering (a filled bar vs a 1px
/// outline) is up to the widget — see `InkSegments` in `lib/ui/`.
abstract final class AppInkDensity {
  static const int _inkR = 46, _inkG = 38, _inkB = 32; // #2E2620

  /// Залито.
  static const filled = AppColors.ink; // #2E2620

  /// Полутон.
  static const halftone = Color.fromARGB(107, _inkR, _inkG, _inkB); // .42

  /// Контур — цвет линии и её толщина.
  static const outlineColor = Color.fromARGB(77, _inkR, _inkG, _inkB); // .30
  static const outlineWidth = 1.0;

  /// The solid colour a filled/halftone grade paints with. [InkDensity.outline]
  /// is a border, not a fill — it returns transparent here; draw it with
  /// [outlineColor] / [outlineWidth].
  static Color solid(InkDensity d) => switch (d) {
    InkDensity.filled => filled,
    InkDensity.halftone => halftone,
    InkDensity.outline => const Color(0x00000000),
  };
}
