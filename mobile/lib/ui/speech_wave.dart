import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// АМПЛИТУДА ЗАПИСИ (токен-лист 2б «Микрофон»): пять столбиков по 3 px под кнопкой, высота по
/// уровню звука, ink; пока тихо — 4 px контурного цвета. Под «уменьшением движения» столбики
/// статичны: полная высота при записи, тихие без неё.
class AmplitudeBars extends StatelessWidget {
  const AmplitudeBars({super.key, required this.level, required this.active});

  /// Уровень звука 0…1.
  final double level;
  final bool active;

  static const _profile = [0.35, 0.7, 1.0, 0.6, 0.45];

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final l = active ? (reduce ? 1.0 : level.clamp(0.0, 1.0)) : 0.0;

    return SizedBox(
      height: 22,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (var i = 0; i < _profile.length; i++) ...[
            if (i > 0) const SizedBox(width: 4),
            AnimatedContainer(
              duration: reduce ? Duration.zero : const Duration(milliseconds: 90),
              width: 3,
              height: 4 + 18 * _profile[i] * l,
              decoration: BoxDecoration(
                color: active ? AppColors.ink : AppColors.markerOutline,
                borderRadius: BorderRadius.circular(1.5),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// ВОЛНА РЕПЛИКИ СОБЕСЕДНИКА: 24 столбика по 2 px, gap 3, высота 22; идёт по длительности аудио —
/// пройденная часть ink, остальное `.28`. Рисунок столбиков фиксирован (это волна, а не спектр),
/// чтобы одна и та же реплика выглядела одинаково при каждом воспроизведении.
class LineWave extends StatelessWidget {
  const LineWave({super.key, required this.progress});

  /// 0…1 — доля реплики, которая уже прозвучала.
  final double progress;

  static const _heights = [6, 10, 14, 18, 12, 8, 16, 20, 14, 9, 12, 18, 15, 10, 7, 13, 17, 11, 8, 14, 19, 12, 9, 6];

  @override
  Widget build(BuildContext context) {
    final played = (progress.clamp(0.0, 1.0) * _heights.length).round();

    return SizedBox(
      height: 22,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          for (var i = 0; i < _heights.length; i++) ...[
            if (i > 0) const SizedBox(width: 3),
            Container(
              width: 2,
              height: _heights[i].toDouble(),
              decoration: BoxDecoration(
                color: i < played ? AppColors.ink : AppColors.playOutline,
                borderRadius: BorderRadius.circular(1),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
