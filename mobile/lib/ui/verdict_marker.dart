import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

/// СОСТОЯНИЕ МАРКЕРА 22 (токен-лист 4л, 4к-2) — единственный способ показать состояние карточки
/// в списках плана: шалфей / охра / терракота, не начат — пусто. Никогда словом.
enum MarkerState {
  /// Контур hairline `.22`, внутри пусто.
  empty,

  /// Сдал — заливка #4E6B52 с галкой paper.
  passed,

  /// Сдал с подсказкой — охра #A6761F с галкой.
  hinted,

  /// Не сдал, вернётся — терракота #B5533C с крестом (заливка только у значка, 4к-2).
  failed,
}

/// Круг 22 (по умолчанию) с галкой 12 stroke 2.4 или крестом. На тёмной плите — [onPlate]:
/// контур `rgba(246,243,236,.35)`, галка paper 10 (маркеры «научишься», 4н).
///
/// Заливка анимируется 220 мс ease-out (4е «Верный вариант»); под «уменьшением движения» — сразу.
class VerdictMarker extends StatelessWidget {
  const VerdictMarker({super.key, required this.state, this.size = 22, this.onPlate = false});

  final MarkerState state;
  final double size;
  final bool onPlate;

  Color? get _fill => switch (state) {
    MarkerState.empty => null,
    MarkerState.passed => AppColors.verdictKnown,
    MarkerState.hinted => AppColors.verdictUnsure,
    MarkerState.failed => AppColors.verdictUnknown,
  };

  @override
  Widget build(BuildContext context) {
    final fill = _fill;
    final reduce = MediaQuery.of(context).disableAnimations;
    final icon = state == MarkerState.failed ? LucideIcons.x : LucideIcons.check;
    final iconSize = size * (onPlate ? 10 / 18 : 12 / 22);

    return AnimatedContainer(
      duration: reduce ? Duration.zero : AppMotion.answerCorrect,
      curve: AppMotion.easeOut,
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: fill ?? Colors.transparent,
        border: fill == null
            ? Border.all(
                color: onPlate ? AppColors.paperMarkerOutline : AppColors.markerOutline,
                width: onPlate ? 1.5 : 1,
              )
            : null,
      ),
      child: fill == null
          ? null
          : Center(
              child: _Stroke(
                icon: icon,
                size: iconSize,
                color: state == MarkerState.hinted ? AppColors.onVerdictUnsure : AppColors.paper,
              ),
            ),
    );
  }
}

/// Lucide-значок толщиной 2.4 — галка и крест маркера.
class _Stroke extends StatelessWidget {
  const _Stroke({required this.icon, required this.size, required this.color});

  final IconData icon;
  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => Icon(icon, size: size, color: color, weight: 700);
}
