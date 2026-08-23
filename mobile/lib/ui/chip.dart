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
      child: MinTapHeight(
        onTap: onTap,
        child: Material(
          color: bg,
          shape: border == null ? shape : shape.copyWith(side: BorderSide(color: AppColors.hairline)),
          clipBehavior: Clip.antiAlias,
          child: InkWell(onTap: onTap, child: content),
        ),
      ),
    );
  }
}

/// Прозрачный хит-бокс: ребёнок рисуется как рисовался, а тап ловится в
/// [minHeight] по вертикали (QA-OBS-15 — у чипа 28 pt высоты при минимуме 44).
///
/// Растёт только вверх-вниз: горизонтальные промежутки между чипами оставлены
/// как есть, иначе ряд разъезжается. Свой [onTap] нужен потому, что поля
/// хит-бокса лежат СНАРУЖИ ребёнка — его собственный `InkWell` их не видит; на
/// самом чипе арена жестов отдаёт тап внутреннему обработчику, так что действие
/// срабатывает ровно один раз, где бы ни попали.
class MinTapHeight extends StatelessWidget {
  const MinTapHeight({
    super.key,
    required this.child,
    this.onTap,
    this.minHeight = AppSpacing.minTap,
  });

  final Widget child;
  final VoidCallback? onTap;
  final double minHeight;

  @override
  Widget build(BuildContext context) => GestureDetector(
        behavior: HitTestBehavior.opaque,
        // Ребёнок уже несёт свою Semantics-кнопку — второй узел не нужен.
        excludeFromSemantics: true,
        onTap: onTap,
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: minHeight),
          child: Align(widthFactor: 1, heightFactor: 1, child: child),
        ),
      );
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
