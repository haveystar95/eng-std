import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Главная кнопка — чернильная заливка, бумажный текст, опциональная подстрока
/// (§2). Rule 20: терракотовой заливки у неё нет — это привилегия вердикта
/// «Не знаю». On [enabled]=false она гаснет (исчерпанная квота генерации).
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    this.subtitle,
    this.onPressed,
    this.enabled = true,
    this.trailingIcon,
    this.minHeight = 56,
  });

  final String label;
  final String? subtitle;
  final VoidCallback? onPressed;
  final bool enabled;
  final IconData? trailingIcon;
  final double minHeight;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onPressed != null;
    final bg = on ? AppColors.ink : AppColors.track;
    final fg = on ? AppColors.paper : AppColors.tertiary;

    final title = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Flexible(
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: AppText.primaryButton.copyWith(color: fg),
          ),
        ),
        if (trailingIcon != null) ...[
          const SizedBox(width: AppSpacing.s8),
          Icon(trailingIcon, size: 18, color: fg),
        ],
      ],
    );

    return Semantics(
      button: true,
      enabled: on,
      label: label,
      child: Material(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.button),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: on ? onPressed : null,
          child: Container(
            constraints: BoxConstraints(minHeight: minHeight),
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.s22,
              vertical: AppSpacing.s12,
            ),
            alignment: Alignment.center,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                title,
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    textAlign: TextAlign.center,
                    style: AppText.primaryButtonSub.copyWith(color: on ? null : AppColors.tertiary),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Тихая кнопка — низкий приоритет: лёгкая чернильная подложка, вторичный текст
/// (§2б «Вспомогательные кнопки ответа»). Опциональная иконка Lucide слева.
class QuietButton extends StatelessWidget {
  const QuietButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.minHeight = 44,
    this.foreground = AppColors.secondary,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final double minHeight;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: label,
      child: Material(
        color: AppColors.faintInk,
        borderRadius: BorderRadius.circular(AppRadii.field),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onPressed,
          child: Container(
            constraints: BoxConstraints(minHeight: minHeight),
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.s16,
              vertical: AppSpacing.s8,
            ),
            alignment: Alignment.center,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icon != null) ...[
                  Icon(icon, size: 17, color: foreground),
                  const SizedBox(width: AppSpacing.s8),
                ],
                // Flexible + a second line, as on the verdict buttons (QA-OBS-3): the label used to
                // be an unbounded child of a `min` Row, so a button that shares its row with
                // another one simply overflowed — «Тренировка по теме» beside «Мои слова» on the
                // home screen (QA-OBS-10) and «Подсказка: первая буква» in cloze (QA-OBS-29).
                // The 44pt minimum has room for two lines of this type.
                Flexible(
                  child: Text(
                    label,
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextExercise.answerAuxButton.copyWith(color: foreground),
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
