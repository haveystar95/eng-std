import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'session_bits.dart';

/// Состояние листа варианта (кадры 30-4, 30-9).
enum OptionLook {
  /// Ещё не ответили.
  idle,

  /// Верный: подложка шалфея 15 % и галка.
  correct,

  /// Выбранный неверный: контур чернил и покачивание.
  wrong,

  /// Верный у единицы, которая вернётся завтра: подложка шалфея и точка латунью.
  returns,

  /// Ответ уже дан, вариант не участвует.
  settled,
}

/// ЛИСТ ВАРИАНТА 56 — текст (на родном 17 или на языке цели Literata 22) и, если у варианта есть звук,
/// «прослушать» 28 справа (31-4, 31-7, 32-4). Звук у варианта не выдаёт ответ: тап по кругу играет, тап по
/// листу отвечает.
class SessionOption extends StatelessWidget {
  const SessionOption({
    super.key,
    required this.text,
    required this.look,
    required this.onTap,
    this.target = false,
    this.listen,
    this.shake = 0,
  });

  final String text;
  final OptionLook look;
  final VoidCallback? onTap;

  /// Вариант на языке цели — Literata 22.
  final bool target;

  /// «Прослушать» 28 справа; null — вариант молчит.
  final Widget? listen;

  /// Счётчик покачиваний — растёт, когда этот вариант выбран неверно.
  final int shake;

  @override
  Widget build(BuildContext context) {
    final wash = look == OptionLook.correct || look == OptionLook.returns;
    final trailing = switch (look) {
      OptionLook.correct => const Icon(LucideIcons.check, size: 20, color: AppColors.verdictKnown),
      OptionLook.returns => const SessionReturnDot(),
      _ => listen,
    };
    final sheet = AnimatedContainer(
      duration: AppMotion.sessionSageWash,
      curve: AppMotion.easeOut,
      constraints: const BoxConstraints(minHeight: 56),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      decoration: BoxDecoration(
        color: wash ? AppColors.sessionSageWash : AppColors.paper,
        borderRadius: BorderRadius.circular(16),
        border: look == OptionLook.wrong ? Border.all(color: AppColors.ink, width: 1.5) : null,
        boxShadow: wash || look == OptionLook.wrong ? null : kSessionSheetShadow,
      ),
      child: Row(
        children: [
          Expanded(child: Text(text, style: target ? AppTextSession.target22 : AppTextSession.option)),
          if (trailing != null) ...[const SizedBox(width: 12), trailing],
        ],
      ),
    );
    return Semantics(
      button: onTap != null,
      label: text,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: SessionShake(trigger: shake, child: sheet),
      ),
    );
  }
}

/// ЛИСТ ВОПРОСА ШАБЛОНА 30-9 — один на всех кадрах: верх (фото / волна / текст), бровь, зона текста в две
/// строки Literata 26 с «прослушать» 44 и строка перевода 20.
class SessionQuestionSheet extends StatelessWidget {
  const SessionQuestionSheet({
    super.key,
    this.media,
    this.mediaHeight = 208,
    required this.eyebrow,
    this.eyebrowTrailing,
    required this.text,
    this.listen,
    this.translation,
    this.translationStyle,
  });

  /// Фото или плашка с волной; null — верх текстом (без зоны медиа).
  final Widget? media;

  /// 208 в вопросе и «верно», 160 — когда под вариантами стоит «Дальше».
  final double mediaHeight;
  final String eyebrow;
  final String? eyebrowTrailing;

  /// Текст вопроса — обычно Text Literata 26 или строка каркаса с окном.
  final Widget text;
  final Widget? listen;

  /// Строка перевода под текстом (слот 20 держится всегда).
  final String? translation;
  final TextStyle? translationStyle;

  @override
  Widget build(BuildContext context) => SessionSheet(
    padding: EdgeInsets.zero,
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (media != null)
          AnimatedContainer(
            duration: AppMotion.sessionCardChange,
            height: mediaHeight,
            child: media,
          ),
        Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SessionEyebrow(eyebrow, trailing: eyebrowTrailing),
              const SizedBox(height: 6),
              ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 68),
                child: Row(
                  children: [
                    Expanded(child: text),
                    if (listen != null) ...[const SizedBox(width: 12), listen!],
                  ],
                ),
              ),
              const SizedBox(height: 4),
              SizedBox(
                height: translation == null ? 20 : null,
                child: translation == null ? null : Text(translation!, style: translationStyle ?? AppTextSession.body),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// ПЛАШКА С ВОЛНОЙ «НА СЛУХ» — `#EFEBE3` во всё поле, волна 80 × 24 посередине; тап — звук ещё раз.
class SessionWavePlate extends StatelessWidget {
  const SessionWavePlate({super.key, required this.playing, required this.onTap, this.heights = SessionWave.twenty, this.label});

  final bool playing;
  final VoidCallback onTap;
  final List<double> heights;
  final String? label;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: ColoredBox(
        color: AppColors.ground,
        child: Center(
          child: SessionWave(
            heights: heights,
            barWidth: heights.length > 5 ? 2.5 : 3,
            width: 80,
            playing: playing,
          ),
        ),
      ),
    ),
  );
}
