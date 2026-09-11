import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// БЛОК ЗАДАНИЯ (токен-лист 4м) — место, на которое смотришь сразу и понимаешь, что от тебя хотят.
/// На каждой карточке ровно один; стоит непосредственно над зоной ответа с gap 16.
///
/// Подложка `rgba(46,38,32,.04)`, radius 18, padding 16 18 (слева 24 — за полосой), без тени;
/// слева вертикальная полоса 3 px ink на всю высоту, radius 2. Латуни в блоке нет — чернила это
/// задание, латунь — собеседник.
///
/// Внутри: лейбл caps 11/700 tertiary и либо текст задания Inter 22/800 ([text]), либо
/// произвольное содержимое ([child] — слово Literata 46 на «произнеси», фраза 26 на «повтори»,
/// пример с пропуском на 12i). Блок из одного лейбла — высота 44.
class TaskBlock extends StatelessWidget {
  const TaskBlock({super.key, required this.label, this.text, this.child})
    : assert(text == null || child == null, 'either text or child');

  final String label;
  final String? text;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final labelOnly = text == null && child == null;
    final content = labelOnly
        ? Text(label.toUpperCase(), style: AppTextDay.taskLabel)
        : Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(label.toUpperCase(), style: AppTextDay.taskLabel),
              const SizedBox(height: AppSpacing.s8),
              child ?? Text(text!, style: AppTextDay.taskText),
            ],
          );

    return Container(
      constraints: BoxConstraints(minHeight: labelOnly ? 44 : 0),
      padding: labelOnly
          ? const EdgeInsets.fromLTRB(24, 0, 18, 0)
          : const EdgeInsets.fromLTRB(24, 16, 18, 16),
      alignment: labelOnly ? Alignment.centerLeft : null,
      decoration: BoxDecoration(
        color: AppColors.taskBlock,
        borderRadius: BorderRadius.circular(AppRadii.field),
      ),
      child: Stack(
        children: [
          Positioned(
            left: -24,
            top: labelOnly ? -0 : -16,
            bottom: labelOnly ? -0 : -16,
            child: Container(
              width: 3,
              decoration: BoxDecoration(
                color: AppColors.ink,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          content,
        ],
      ),
    );
  }
}
