import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

/// РАСКЛАДКА КАРТОЧКИ ДНЯ (токен-лист 2б «Раскладка»): карточка задания — верхние ⅔ экрана и
/// уравновешена внутри; зона ответа прибита к низу; нижняя треть никогда не пустая. Группа
/// «блок задания + ответ» — одна группа, вопрос не отрывается от вариантов (gap 16 ставит
/// вызывающий внутри [bottom]).
///
/// [top] — то, что стоит в верхних двух третях (карточка собеседника, слово, фраза);
/// [bottom] — группа ответа, прибитая к низу (блок задания + варианты / плитки / микрофон);
/// [dock] — «Дальше» на доке (или ничего).
class DayCardFrame extends StatelessWidget {
  const DayCardFrame({super.key, this.top, required this.bottom, this.dock, this.topCentered = true});

  final Widget? top;
  final Widget bottom;
  final Widget? dock;

  /// На карточках речи группа стоит по центру своей зоны, не прижата к шапке (2б).
  final bool topCentered;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Expanded(
        child: top == null
            ? const SizedBox.shrink()
            : SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, 16),
                child: topCentered
                    ? ConstrainedBox(
                        constraints: const BoxConstraints(minHeight: 160),
                        child: Center(child: top),
                      )
                    : top,
              ),
      ),
      Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 0, AppSpacing.screenH, 12),
        child: bottom,
      ),
      dock ?? SizedBox(height: MediaQuery.paddingOf(context).bottom + 12),
    ],
  );
}

/// «ДАЛЬШЕ» НА ДОКЕ — с необязательной тихой строкой над кнопкой («Вернётся в конце этапа»).
class DayDock extends StatelessWidget {
  const DayDock({super.key, required this.label, required this.onTap, this.note, this.noteStyle});

  final String label;
  final VoidCallback? onTap;
  final String? note;
  final TextStyle? noteStyle;

  @override
  Widget build(BuildContext context) => Container(
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [AppColors.paperClear, AppColors.paper],
        stops: [0, 0.3],
      ),
    ),
    padding: EdgeInsets.fromLTRB(
      AppSpacing.screenH,
      12,
      AppSpacing.screenH,
      12 + MediaQuery.paddingOf(context).bottom,
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (note case final n?) ...[
          Text(n, textAlign: TextAlign.center, style: noteStyle ?? AppTextDay.quiet),
          const SizedBox(height: 10),
        ],
        PrimaryButton(label: label, minHeight: 54, onPressed: onTap),
      ],
    ),
  );
}

/// Пустой док — только отступ под безопасную зону, чтобы карточки без кнопки стояли одинаково.
class DayDockSpace extends StatelessWidget {
  const DayDockSpace({super.key, this.height = 0});
  final double height;

  @override
  Widget build(BuildContext context) => SizedBox(height: height + MediaQuery.paddingOf(context).bottom + 12);
}

/// ЛАТУННАЯ ПИЛЮЛЯ — «ИЗ ДНЯ 1», «ВЕРНУЛОСЬ ИЗ ДНЯ 1»: заливка #8C6A3A, текст paper 11/700/.1em,
/// высота 22, radius 11.
class BrassPill extends StatelessWidget {
  const BrassPill(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Container(
    height: 22,
    padding: const EdgeInsets.symmetric(horizontal: 9),
    alignment: Alignment.center,
    decoration: BoxDecoration(color: AppColors.brassInk, borderRadius: BorderRadius.circular(11)),
    child: Text(text.toUpperCase(), style: AppTextDay.brassPill),
  );
}

/// КОНТУРНЫЙ БЕЙДЖ — «новое слово», «новая фраза», «повторение»: контур `.22`, 10.5/700 caps
/// secondary, высота 22 (16a).
class OutlineBadge extends StatelessWidget {
  const OutlineBadge(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Container(
    height: 22,
    padding: const EdgeInsets.symmetric(horizontal: 9),
    alignment: Alignment.center,
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(11),
      border: Border.all(color: AppColors.markerOutline),
    ),
    child: Text(text.toUpperCase(), style: AppText.badge.copyWith(fontSize: 10.5, letterSpacing: 1.05)),
  );
}

/// Секционный лейбл на бумаге — «СЛОВА · 8» с необязательной латунной строкой справа.
class DaySectionLabel extends StatelessWidget {
  const DaySectionLabel(this.text, {super.key, this.trailing});
  final String text;
  final String? trailing;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(0, 16, 0, 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.baseline,
      textBaseline: TextBaseline.alphabetic,
      children: [
        Expanded(child: Text(text.toUpperCase(), style: AppTextDay.sectionLabel)),
        if (trailing case final t?) Text(t, style: AppTextDay.brassNote),
      ],
    ),
  );
}

/// Кружок «назад» / «крестик» в шапке сессии — 20, stroke 2, secondary.
class DayCloseButton extends StatelessWidget {
  const DayCloseButton({super.key, required this.onTap, required this.label, this.icon = LucideIcons.x});
  final VoidCallback onTap;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: InkResponse(
      onTap: onTap,
      radius: 22,
      child: SizedBox(
        width: AppSpacing.minTap,
        height: AppSpacing.minTap,
        child: Icon(icon, size: 20, color: AppColors.secondary),
      ),
    ),
  );
}
