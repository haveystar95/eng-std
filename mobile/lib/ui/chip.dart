import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Chip — ширина строго по содержимому (rule 16): текст никогда не обрезается
/// краем и не сокращается многоточием. Перенос/скролл — забота контейнера
/// ([ChipWrap] или [ChipScrollRow]).
///
/// Состояния: обычный (контур hairline), [selected] (чернильная заливка —
/// системный идиом выбора), [used] (§2б — фон faintInk, текст tertiary).
class AppChip extends StatelessWidget {
  const AppChip({
    super.key,
    required this.label,
    this.selected = false,
    this.used = false,
    this.onTap,
    this.leading,
  });

  final String label;
  final bool selected;
  final bool used;
  final VoidCallback? onTap;

  /// Ведущий виджет (напр. [MiniFlag] в языковом чипе).
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final Color bg;
    final Color fg;
    final BoxBorder? border;
    if (used) {
      bg = AppColors.faintInk;
      fg = AppColors.tertiary;
      border = null;
    } else if (selected) {
      bg = AppColors.ink;
      fg = AppColors.paper;
      border = null;
    } else {
      bg = Colors.transparent;
      fg = AppColors.ink;
      border = Border.all(color: AppColors.hairline, width: 1);
    }

    final content = Padding(
      // padding 8 / 13 (§3 «Чип в ряду»); текст в одну строку, без обрезки.
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (leading != null) ...[leading!, const SizedBox(width: AppSpacing.s8)],
          Text(
            label,
            maxLines: 1,
            softWrap: false,
            overflow: TextOverflow.visible, // rule 16 — не многоточие
            style: AppTextExercise.answerAuxButton.copyWith(
              color: fg,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );

    final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadii.chip));
    return Semantics(
      button: onTap != null,
      selected: selected,
      label: label,
      child: Material(
        color: bg,
        shape: border == null ? shape : shape.copyWith(side: BorderSide(color: AppColors.hairline)),
        clipBehavior: Clip.antiAlias,
        child: InkWell(onTap: onTap, child: content),
      ),
    );
  }
}

/// Ряд чипов с переносом на следующую строку (rule 16, первый вариант).
class ChipWrap extends StatelessWidget {
  const ChipWrap({super.key, required this.children, this.spacing = AppSpacing.s8, this.runSpacing = AppSpacing.s8});

  final List<Widget> children;
  final double spacing;
  final double runSpacing;

  @override
  Widget build(BuildContext context) =>
      Wrap(spacing: spacing, runSpacing: runSpacing, children: children);
}

/// Ряд чипов с горизонтальным скроллом (rule 16, второй вариант) — для
/// неразрывно длинных чипов. Внутренний отступ экрана сохраняется через
/// [padding], чип не режется краем.
class ChipScrollRow extends StatelessWidget {
  const ChipScrollRow({
    super.key,
    required this.children,
    this.spacing = AppSpacing.s8,
    this.padding = const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
  });

  final List<Widget> children;
  final double spacing;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: padding,
      child: Row(
        children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0) SizedBox(width: spacing),
            children[i],
          ],
        ],
      ),
    );
  }
}
