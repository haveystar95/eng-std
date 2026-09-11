import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'verdict_marker.dart';

/// ЧТО СЛУЧИЛОСЬ С ВАРИАНТОМ после ответа (токен-лист 4л).
enum AnswerOptionVerdict {
  /// До ответа.
  none,

  /// Нажат и верен: маркер шалфеем с галкой, тонировка `rgba(78,107,82,.08)`, контура нет.
  correct,

  /// Нажат и неверен: маркер терракотой с крестом, тонировка `rgba(154,68,48,.06)`, shake ±3 px.
  wrong,

  /// Верный после ошибки — маркер шалфеем, тонировки нет: «вот правильный» тише, чем «ты ответил
  /// верно».
  correctQuiet,

  /// Остальные после ответа — opacity .5, маркер контурный.
  dimmed,
}

/// ВАРИАНТ ОТВЕТА (4л) — один контейнер для всех выборов из вариантов: 12a, 16c, 23-7a–d.
///
/// Высота 60 фиксированная, слоёная бумага с тенью карточки, radius 18, padding 0 18, маркер 22
/// слева с gap 14, текст по центру по вертикали. Язык внутри меняет только шрифт: NATIVE — Inter
/// 17/500, TARGET — Literata 18/500. В две строки не переносится — сервер держит ≤ 34 знаков; на
/// всякий случай длинная строка ужимается кеглем, а не режется.
///
/// Верный: 220 мс ease-out — маркер, потом фон. Неверный: shake 180 мс. Под «уменьшением
/// движения» — только цвет и непрозрачность.
class AnswerOption extends StatefulWidget {
  const AnswerOption({
    super.key,
    required this.text,
    required this.target,
    required this.verdict,
    this.onTap,
    this.marked,
  });

  final String text;

  /// На изучаемом языке — Literata; на языке поддержки — Inter.
  final bool target;
  final AnswerOptionVerdict verdict;
  final VoidCallback? onTap;

  /// Кусок текста, который подчёркивается волной после неверного ответа (`error_span` карточки
  /// pick_correct); null — ничего.
  final ({int start, int length})? marked;

  @override
  State<AnswerOption> createState() => _AnswerOptionState();
}

class _AnswerOptionState extends State<AnswerOption> with SingleTickerProviderStateMixin {
  late final AnimationController _shake = AnimationController(vsync: this, duration: AppMotion.answerWrong);

  @override
  void didUpdateWidget(AnswerOption old) {
    super.didUpdateWidget(old);
    if (widget.verdict == AnswerOptionVerdict.wrong &&
        old.verdict != AnswerOptionVerdict.wrong &&
        !MediaQuery.of(context).disableAnimations) {
      _shake.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _shake.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final verdict = widget.verdict;
    final tint = switch (verdict) {
      AnswerOptionVerdict.correct => AppColors.sageTint,
      AnswerOptionVerdict.wrong => AppColors.terracottaTint,
      _ => AppColors.surfaceRaised,
    };
    final marker = switch (verdict) {
      AnswerOptionVerdict.correct || AnswerOptionVerdict.correctQuiet => MarkerState.passed,
      AnswerOptionVerdict.wrong => MarkerState.failed,
      _ => MarkerState.empty,
    };
    final style = widget.target ? AppTextDay.optionTarget : AppTextDay.optionNative;

    Widget label;
    final marked = widget.marked;
    if (verdict == AnswerOptionVerdict.wrong &&
        marked != null &&
        marked.start >= 0 &&
        marked.start + marked.length <= widget.text.length) {
      label = Text.rich(
        TextSpan(
          style: style,
          children: [
            TextSpan(text: widget.text.substring(0, marked.start)),
            TextSpan(
              text: widget.text.substring(marked.start, marked.start + marked.length),
              style: const TextStyle(
                color: AppColors.destructiveText,
                decoration: TextDecoration.underline,
                decorationStyle: TextDecorationStyle.wavy,
              ),
            ),
            TextSpan(text: widget.text.substring(marked.start + marked.length)),
          ],
        ),
        maxLines: 1,
        softWrap: false,
        overflow: TextOverflow.visible,
      );
    } else {
      label = Text(widget.text, style: style, maxLines: 1, softWrap: false, overflow: TextOverflow.visible);
    }

    final body = AnimatedOpacity(
      duration: reduce ? Duration.zero : AppMotion.answerCorrect,
      opacity: verdict == AnswerOptionVerdict.dimmed ? 0.5 : 1,
      child: AnimatedContainer(
        duration: reduce ? Duration.zero : AppMotion.answerCorrect,
        // Маркер первым, фон вторым: тонировка начинается на второй половине 220 мс.
        curve: const Interval(0.5, 1, curve: Curves.easeOut),
        height: 60,
        padding: const EdgeInsets.symmetric(horizontal: 18),
        decoration: BoxDecoration(
          color: tint,
          borderRadius: BorderRadius.circular(AppRadii.field),
          boxShadow: AppShadows.card,
        ),
        child: Row(
          children: [
            VerdictMarker(state: marker),
            const SizedBox(width: 14),
            Expanded(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: label,
              ),
            ),
          ],
        ),
      ),
    );

    final enabled = widget.onTap != null && verdict == AnswerOptionVerdict.none;

    return Semantics(
      button: enabled,
      label: widget.text,
      child: AnimatedBuilder(
        animation: _shake,
        builder: (context, child) {
          // Три колебания ±3 px, затухающие к концу (4е «Неверный вариант»).
          final t = _shake.value;
          final dx = _shake.isAnimating ? math.sin(t * math.pi * 3) * 3 * (1 - t) : 0.0;
          return Transform.translate(offset: Offset(dx, 0), child: child);
        },
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(AppRadii.field),
          child: InkWell(
            onTap: enabled ? widget.onTap : null,
            borderRadius: BorderRadius.circular(AppRadii.field),
            child: body,
          ),
        ),
      ),
    );
  }
}
