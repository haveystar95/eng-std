import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Линия прогресса (§4б): подложка track, заливка чернилами. Заливка имеет
/// минимальную ширину [AppProgress.fillMinWidth] (8), чтобы малый прогресс не
/// исчезал.
class ProgressLine extends StatelessWidget {
  const ProgressLine({
    super.key,
    required this.value,
    this.height = AppProgress.heightScreen,
    this.trackColor = AppColors.track,
    this.fillColor = AppColors.ink,
  });

  /// 0..1.
  final double value;
  final double height;
  final Color trackColor;
  final Color fillColor;

  /// Ширина заливки с учётом min-width. 0 при [value]<=0. Вынесено для теста.
  static double fillWidth(double value, double totalWidth) {
    final v = value.clamp(0.0, 1.0);
    if (v <= 0) return 0;
    final raw = v * totalWidth;
    return raw < AppProgress.fillMinWidth
        ? AppProgress.fillMinWidth.clamp(0.0, totalWidth)
        : raw;
  }

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(height / 2);
    return ClipRRect(
      borderRadius: radius,
      child: SizedBox(
        height: height,
        child: LayoutBuilder(
          builder: (context, c) {
            final w = fillWidth(value, c.maxWidth);
            return Stack(
              children: [
                Positioned.fill(child: ColoredBox(color: trackColor)),
                if (w > 0)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: SizedBox(
                      width: w,
                      child: DecoratedBox(
                        decoration: BoxDecoration(color: fillColor, borderRadius: radius),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}
