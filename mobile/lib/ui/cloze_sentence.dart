import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// ПРИМЕР С ПРОПУСКОМ (кадры 12i/12j, «слово в пример» дня): предложение курсивной антиквой,
/// пропуск ровно по ширине ответа — подчёркнутый пробел 1.5 px, — в пропуске стоит то, что
/// ответил человек. Один виджет для коллекций и плана.
///
/// [filled] — слово в пропуске: своё, живое во время набора и после вердикта; null — пусто, с
/// курсором. [answered] + [correct] красят подчёркивание вердиктом; [mistakes] — индексы слов
/// в [filled], которые подчёркиваются волной терракотой.
class ClozeSentence extends StatelessWidget {
  const ClozeSentence({
    super.key,
    required this.example,
    required this.answer,
    required this.filled,
    required this.answered,
    required this.correct,
    this.mistakes = const {},
    this.style = AppTextExercise.clozeExample,
    this.gap = '___',
  });

  final String example;
  final String answer;
  final String? filled;
  final bool answered;
  final bool correct;
  final Set<int> mistakes;
  final TextStyle style;

  /// Маркер пропуска в тексте сервера («___»); если его нет, пропуск ищется по ответу.
  final String gap;

  @override
  Widget build(BuildContext context) {
    final String before, after;
    final gapAt = example.indexOf(gap);
    if (gapAt >= 0) {
      before = example.substring(0, gapAt);
      after = example.substring(gapAt + gap.length);
    } else {
      final idx = example.toLowerCase().indexOf(answer.toLowerCase());
      before = idx >= 0 ? example.substring(0, idx) : '$example ';
      after = idx >= 0 ? example.substring(idx + answer.length) : '';
    }

    final InlineSpan blank;
    if (filled == null) {
      // Ширина пропуска — ширина ответа тем же шрифтом (12i: «пропуск ровно по ширине слова»).
      final painter = TextPainter(
        text: TextSpan(text: answer, style: style.copyWith(fontStyle: FontStyle.normal, fontWeight: FontWeight.w500)),
        textDirection: TextDirection.ltr,
      )..layout();
      blank = WidgetSpan(
        alignment: PlaceholderAlignment.baseline,
        baseline: TextBaseline.alphabetic,
        child: Container(
          width: (painter.width + 8).clamp(48.0, 220.0),
          height: (style.fontSize ?? 18) * 1.2,
          alignment: Alignment.centerLeft,
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.playOutline, width: 1.5)),
          ),
          child: SizedBox(width: 1.5, height: (style.fontSize ?? 18) * 1.05, child: const ColoredBox(color: AppColors.ink)),
        ),
      );
    } else {
      final marked = mistakes.isNotEmpty;
      blank = TextSpan(
        text: filled,
        style: style.copyWith(
          fontStyle: FontStyle.normal,
          fontWeight: FontWeight.w500,
          color: marked ? AppColors.destructiveText : AppColors.ink,
          decoration: TextDecoration.underline,
          decorationStyle: marked ? TextDecorationStyle.wavy : TextDecorationStyle.solid,
          decorationColor: answered
              ? (correct ? AppColors.verdictKnown : AppColors.destructiveText)
              : AppColors.tertiary,
          decorationThickness: answered && !marked ? 2 : 1.5,
        ),
      );
    }

    return Text.rich(
      TextSpan(style: style, children: [TextSpan(text: before), blank, TextSpan(text: after)]),
    );
  }
}
