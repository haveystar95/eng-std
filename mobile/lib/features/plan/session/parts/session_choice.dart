import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
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

/// A SOUND PLATE (кадр 34-5, наряд CLIENT-CONV-1a) — an option that is a LINE: before the answer it
/// has no text at all, only «играет/пауза» 44, a wave and its length.
///
/// Four states, and they are about LISTENING, not about being right: не слушал — the wave in the
/// colour of a rule; играет — brass and running; прослушан — a sage check by the length; выбран — an
/// ink outline (the second tap on a plate that has been heard). «Это ответ» comes alive only when
/// the chosen plate has been listened to: the card asks the learner to hear it, not to guess.
///
/// THE WAVE IS THE FILE'S OWN, not a shared drawing: its bars are derived from the sound's `ref`, so
/// three answers look like three different recordings and never like one picture repeated. It is not
/// the real amplitude — that would cost a decode of every file to draw a plate — and it says nothing
/// about the line; it is there so the plates are told apart by eye.
///
/// After the answer the plate opens both texts, the right one takes the sage wash and the check, and
/// the one that was chosen wrongly keeps the ink outline.
class SessionSoundPlate extends StatelessWidget {
  const SessionSoundPlate({
    super.key,
    required this.audioRef,
    required this.heard,
    required this.playing,
    required this.marked,
    required this.onTap,
    this.durationMs,
    this.look,
    this.textTarget,
    this.textNative,
  });

  /// The sound's `ref` — the seed of this plate's wave.
  final String audioRef;

  /// The line has been played to the end at least once.
  final bool heard;
  final bool playing;

  /// Chosen as the answer, not yet sent.
  final bool marked;
  final VoidCallback? onTap;

  /// «0:03»; null — the server sent no length and none is drawn.
  final int? durationMs;

  /// After the answer — how this option settled; null — the answer is not given yet and the texts
  /// stay closed.
  final OptionLook? look;
  final String? textTarget;
  final String? textNative;

  static const int _mask = 2147483647;

  /// «m:ss» of the plate's length.
  static String clock(int ms) {
    final total = (ms / 1000).round();
    return '${total ~/ 60}:${(total % 60).toString().padLeft(2, '0')}';
  }

  /// Twenty bars 8…24 from the sound's own `ref` — the same file always draws the same wave.
  /// (`_mask` is a 31-bit arithmetic mask, written in decimal: hex in `lib/` is for colours only.)
  static List<double> waveOf(String ref) {
    var seed = 0;
    for (final unit in ref.codeUnits) {
      seed = (seed * 31 + unit) & _mask;
    }
    return [
      for (var i = 0; i < 20; i++)
        () {
          seed = (seed * 1103515245 + 12345) & _mask;
          return 8 + (seed % 17).toDouble();
        }(),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final answered = look != null;
    final wash = look == OptionLook.correct || look == OptionLook.returns;
    final waveColor = playing
        ? AppColors.brassInk
        : heard
        ? AppColors.brassInk
        : AppColors.markerOutline;

    return Semantics(
      button: onTap != null,
      label: textNative ?? l.planWindowListen,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: AnimatedContainer(
          duration: AppMotion.sessionSageWash,
          curve: AppMotion.easeOut,
          constraints: BoxConstraints(minHeight: answered ? 80 : 64),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: wash ? AppColors.sessionSageWash : AppColors.paper,
            borderRadius: BorderRadius.circular(16),
            border: (!answered && marked) || look == OptionLook.wrong
                ? Border.all(color: AppColors.ink, width: 1.5)
                : null,
            boxShadow: wash || (!answered && marked) || look == OptionLook.wrong ? null : kSessionSheetShadow,
          ),
          child: Row(
            children: [
              SessionPlayCircle(key: ValueKey('plate-play-$audioRef'), playing: playing),
              const SizedBox(width: 8),
              Expanded(
                child: answered
                    ? Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(textTarget ?? '', style: AppTextSession.target22),
                          const SizedBox(height: 4),
                          Text(textNative ?? '', style: AppTextSession.body),
                        ],
                      )
                    : Align(
                        alignment: Alignment.centerLeft,
                        child: SessionWave(
                          key: ValueKey('plate-wave-$audioRef'),
                          heights: waveOf(audioRef),
                          barWidth: 2.5,
                          width: 140,
                          playing: playing,
                          color: waveColor,
                        ),
                      ),
              ),
              if (!answered) ...[
                const SizedBox(width: 12),
                if (durationMs != null) Text(clock(durationMs!), style: AppTextSession.meta),
                if (heard) ...[
                  const SizedBox(width: 6),
                  const Icon(LucideIcons.check, size: 16, color: AppColors.verdictKnown),
                ],
              ],
              if (look == OptionLook.correct) ...[
                const SizedBox(width: 12),
                const Icon(LucideIcons.check, size: 20, color: AppColors.verdictKnown),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// «ИГРАЕТ / ПАУЗА» 44 (кадр 34-5) — an outlined circle on the ground's fill, a solid triangle 18 in it, two bars while
/// the plate plays. The canvas' own glyphs (a 24 box drawn at 18): the Lucide set has no solid triangle.
class SessionPlayCircle extends StatelessWidget {
  const SessionPlayCircle({super.key, required this.playing});

  final bool playing;

  @override
  Widget build(BuildContext context) => Container(
    width: 44,
    height: 44,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: AppColors.ground,
      border: Border.all(color: AppColors.markerOutline, width: 1.5),
    ),
    child: CustomPaint(size: const Size.square(18), painter: _PlayGlyphPainter(playing: playing)),
  );
}

class _PlayGlyphPainter extends CustomPainter {
  _PlayGlyphPainter({required this.playing});

  final bool playing;

  @override
  void paint(Canvas canvas, Size size) {
    final u = size.width / 24;
    final ink = Paint()..color = AppColors.ink;
    if (playing) {
      for (final x in const [7.5, 12.9]) {
        canvas.drawRRect(RRect.fromRectAndRadius(Rect.fromLTWH(x * u, 5.5 * u, 3.6 * u, 13 * u), Radius.circular(1.2 * u)), ink);
      }
      return;
    }
    canvas.drawPath(
      Path()
        ..moveTo(8.5 * u, 5.5 * u)
        ..lineTo(18 * u, 12 * u)
        ..lineTo(8.5 * u, 18.5 * u)
        ..close(),
      ink,
    );
  }

  @override
  bool shouldRepaint(_PlayGlyphPainter old) => old.playing != playing;
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
