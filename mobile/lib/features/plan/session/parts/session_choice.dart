import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'session_bits.dart';

/// State of an option sheet (canvases 30-4, 30-9).
enum OptionLook {
  /// Not answered yet.
  idle,

  /// Correct: a 15 % sage backing and a check.
  correct,

  /// Chosen and wrong: an ink outline and a shake.
  wrong,

  /// Correct for a unit that comes back tomorrow: a sage backing and a brass dot.
  returns,

  /// The answer has already been given, the option takes no part.
  settled,
}

/// OPTION SHEET 56 — the text (native 17 or target-language Literata 22) and, if the option has audio,
/// «listen» 28 on the right (31-4, 31-7, 32-4). The option's audio does not give the answer away: a tap on the
/// circle plays, a tap on the sheet answers.
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

  /// An option in the target language — Literata 22.
  final bool target;

  /// «Listen» 28 on the right; null — the option is silent.
  final Widget? listen;

  /// Shake counter — grows when this option is chosen wrongly.
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

/// QUESTION SHEET OF TEMPLATE 30-9 — one for all canvases: the top (photo / wave / text), eyebrow, a two-line
/// Literata 26 text zone with «listen» 44, and a translation line 20.
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

  /// A photo or a wave plate; null — a text top (no media zone).
  final Widget? media;

  /// 208 in the question and «correct», 160 — when «Next» stands under the options.
  final double mediaHeight;
  final String eyebrow;
  final String? eyebrowTrailing;

  /// The question text — usually a Literata 26 Text or a frame line with a slot.
  final Widget text;
  final Widget? listen;

  /// Translation line under the text (the 20-high space is always kept).
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

/// «BY EAR» WAVE PLATE — `#EFEBE3` across the whole area, an 80 × 24 wave in the middle; tap — the audio once more.
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
