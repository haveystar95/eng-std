import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

/// Три вердикта разбора. Rule 07: различаются тремя признаками одновременно —
/// позиция (её задаёт раскладка вызывающего), приглушённый цвет и знак
/// (крест / тире / галка). Rule 20: заливка — привилегия только «Не знаю»;
/// «Не уверен» и «Знаю» — контур на бумаге со знаком и текстом в цвете вердикта.
enum VerdictKind { unknown, unsure, known }

class VerdictButton extends StatelessWidget {
  const VerdictButton({
    super.key,
    required this.kind,
    required this.label,
    this.onPressed,
    this.minHeight = 52,
  });

  final VerdictKind kind;
  final String label;
  final VoidCallback? onPressed;
  final double minHeight;

  /// Заливка (только у «Не знаю»), иначе null.
  Color? get _fill => switch (kind) {
    VerdictKind.unknown => AppColors.verdictUnknown,
    VerdictKind.unsure => null,
    VerdictKind.known => null,
  };

  Color get _verdictColor => switch (kind) {
    VerdictKind.unknown => AppColors.verdictUnknown,
    VerdictKind.unsure => AppColors.verdictUnsure,
    VerdictKind.known => AppColors.verdictKnown,
  };

  Color get _foreground => switch (kind) {
    VerdictKind.unknown => AppColors.onVerdictUnknown, // белый на заливке
    VerdictKind.unsure => AppColors.verdictUnsure,
    VerdictKind.known => AppColors.verdictKnown,
  };

  IconData get _sign => switch (kind) {
    VerdictKind.unknown => LucideIcons.x,
    VerdictKind.unsure => LucideIcons.minus,
    VerdictKind.known => LucideIcons.check,
  };

  @override
  Widget build(BuildContext context) {
    final filled = _fill != null;
    final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadii.field));

    return Semantics(
      button: true,
      label: label,
      child: Material(
        color: _fill ?? Colors.transparent,
        shape: filled ? shape : shape.copyWith(side: BorderSide(color: AppColors.hairline)),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onPressed,
          child: Container(
            constraints: BoxConstraints(minHeight: minHeight),
            // Tight horizontally, because three of these share one screen row and the longest
            // label in either locale is English, not Russian: «Don't know» came out as «Don't k…»
            // while «Не знаю» fitted with room to spare (QA-OBS-3).
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s8, vertical: AppSpacing.s8),
            alignment: Alignment.center,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(_sign, size: 18, color: filled ? _foreground : _verdictColor),
                const SizedBox(width: AppSpacing.s8),
                Flexible(
                  child: Text(
                    label,
                    // A second line rather than a smaller type or an ellipsis: the three buttons
                    // must read as one row of equals, and shrinking one of them (or cutting its
                    // word) breaks that. The 56pt minimum already has room for two lines.
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    overflow: TextOverflow.ellipsis,
                    style: AppText.verdictLabel.copyWith(color: _foreground),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
