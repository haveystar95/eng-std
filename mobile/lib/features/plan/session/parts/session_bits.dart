import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../ui/plan_marks.dart';
import '../../plan_stage_text.dart';

/// SHARED BUILDING BLOCKS OF THE DAY SESSION (canvas `session-canvas.dc.html`, series 30–32): sheet, eyebrow, task
/// line, wave, «listen», the action button and its dock, photo, frame line with a slot, «correct / wrong» reactions.
/// One widget per type — cards are assembled from them and do not introduce styles of their own.

/// The screen's side margin — 24.
const double kSessionGutter = 24;

/// An empty slot in a phrase frame — 96 × 30 (canvases 32-x).
const Size kSessionEmptyWindow = Size(96, 30);

/// Inside a slot with text: 8 on the sides, 4 above and below — the slot is sized by its text and the text never
/// touches the outline (polish pass SESSION-1b′, item 10).
const EdgeInsets kSessionSlotPadding = EdgeInsets.symmetric(horizontal: 8, vertical: 4);

/// Shadow of the material sheet — `0 4px 16px rgba(46,38,32,.08)`.
const List<BoxShadow> kSessionSheetShadow = [
  BoxShadow(color: AppColors.sessionSheetShadow, blurRadius: 16, offset: Offset(0, 4)),
];

/// SHEET — `#F6F3EC`, corner radius 16, shadow; the caller sets the padding.
class SessionSheet extends StatelessWidget {
  const SessionSheet({super.key, required this.child, this.padding = const EdgeInsets.all(20), this.color = AppColors.paper});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(16), boxShadow: kSessionSheetShadow),
    padding: padding,
    child: child,
  );
}

/// SHEET EYEBROW — 11/600 in small caps.
class SessionEyebrow extends StatelessWidget {
  const SessionEyebrow(this.text, {super.key, this.trailing});

  final String text;

  /// On the right — «comes back tomorrow» (30-9d).
  final String? trailing;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.baseline,
    textBaseline: TextBaseline.alphabetic,
    children: [
      Expanded(child: Text(text.toUpperCase(), style: AppTextSession.eyebrow)),
      if (trailing != null) Text(trailing!, style: AppTextSession.meta),
    ],
  );
}

/// TASK LINE ABOVE THE SHEET — 17/600 and, if present, the companion's voice 13 below it.
class SessionTask extends StatelessWidget {
  const SessionTask(this.title, {super.key, this.companion});

  final String title;
  final String? companion;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(title, style: AppTextSession.task),
      if (companion != null) ...[
        const SizedBox(height: 4),
        Text(companion!, style: AppTextSession.meta),
      ],
    ],
  );
}

/// THE QUESTION OF A CHECK (33-1, 33-5; наряды FIX-1 доработка, CLIENT-CONV-1b).
///
/// The task line small above it («Что тебе сказали?»), the question itself in the card's own question type (Literata
/// 26) — the head of the block of options in the dock, APART from the conversation: between the two lines it read as a
/// line of the talk (правка прохода 21.09). Never the grey task line at the top edge of the screen: over a
/// conversation of six exchanges the question read as chrome there, and the learner was left with four options and
/// nothing to answer (живой проход 18.09).
class SessionCheckQuestion extends StatelessWidget {
  const SessionCheckQuestion({super.key, required this.task, required this.question});

  final String task;
  final String question;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(task, style: AppTextSession.task),
      const SizedBox(height: 6),
      Text(question, key: const ValueKey('check-question'), style: AppTextSession.question),
    ],
  );
}

/// «УСЛЫШАЛ: …» (правки прохода 21.09, наряд CLIENT-CONV-1b) — what the phone's recogniser gave, under a refusal of
/// the judge (35-2, the own-word round of 32-7): the learner sees whether the verdict was about their sentence or about
/// a misheard one. 13 in the tertiary ink; nothing heard — nothing drawn.
class SessionHeardText extends StatelessWidget {
  const SessionHeardText({super.key, required this.heard, this.center = false});

  final String heard;
  final bool center;

  @override
  Widget build(BuildContext context) {
    final text = heard.trim();
    if (text.isEmpty) return const SizedBox.shrink();
    return Text(
      AppLocalizations.of(context).planSessionHeardLine(text),
      key: const ValueKey('session-heard-text'),
      textAlign: center ? TextAlign.center : TextAlign.left,
      style: AppTextSession.meta,
    );
  }
}

/// BRASS WAVE — the bars are live only while [playing], static afterwards (table «Timing · session»).
class SessionWave extends StatefulWidget {
  const SessionWave({super.key, required this.heights, this.barWidth = 3, this.width, this.playing = false, this.color = AppColors.brassInk});

  /// Five bars of the «listen» button and of the microphone (10/18/24/14/20).
  static const List<double> five = [10, 18, 24, 14, 20];

  /// Twenty bars of the «By ear» plate 80 × 24 (31-5).
  static const List<double> twenty = [10, 16, 22, 12, 24, 18, 10, 20, 14, 22, 16, 11, 24, 18, 12, 20, 15, 10, 22, 16];

  final List<double> heights;
  final double barWidth;

  /// Width of the whole wave (bars spread across the width, `space-between`); null — side by side with a gap of 3.
  final double? width;
  final bool playing;
  final Color color;

  @override
  State<SessionWave> createState() => _SessionWaveState();
}

class _SessionWaveState extends State<SessionWave> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sync();
  }

  @override
  void didUpdateWidget(SessionWave old) {
    super.didUpdateWidget(old);
    _sync();
  }

  void _sync() {
    final animate = widget.playing && !(MediaQuery.maybeDisableAnimationsOf(context) ?? false);
    if (animate && !_c.isAnimating) {
      unawaited(_c.repeat());
    } else if (!animate && _c.isAnimating) {
      _c.stop();
      _c.value = 0;
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final maxH = widget.heights.fold<double>(0, math.max);
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    return SizedBox(
      width: widget.width,
      height: maxH,
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, _) {
          final bars = <Widget>[
            for (var i = 0; i < widget.heights.length; i++)
              Container(
                width: widget.barWidth,
                height: widget.heights[i] * (widget.playing && !reduce ? _scale(i) : 1),
                decoration: BoxDecoration(color: widget.color, borderRadius: BorderRadius.circular(2)),
              ),
          ];
          return Row(
            mainAxisSize: widget.width == null ? MainAxisSize.min : MainAxisSize.max,
            mainAxisAlignment: widget.width == null ? MainAxisAlignment.center : MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: widget.width == null
                ? [for (var i = 0; i < bars.length; i++) ...[if (i > 0) const SizedBox(width: 3), bars[i]]]
                : bars,
          );
        },
      ),
    );
  }

  /// `om-wave`: bar scale .35 ↔ 1 with its own period and shift.
  double _scale(int i) {
    final period = 0.42 + 0.11 * (i % 6);
    final t = (_c.value * 1.2 / period + i * 0.07) % 1.0;
    return 0.35 + 0.65 * (0.5 - 0.5 * math.cos(2 * math.pi * t));
  }
}

/// «LISTEN» — circle 44 (on a sheet) or 28 (on an option); while it plays — a brass outline and the wave. [brass] —
/// the bubble's «listen» 28 of the dialogue (23-0d, series 33–35): a brass outline and a brass glyph on the ground.
class SessionListenButton extends StatelessWidget {
  const SessionListenButton({
    super.key,
    required this.onTap,
    required this.label,
    this.size = 44,
    this.playing = false,
    this.brass = false,
    this.onPaper = false,
  });

  final VoidCallback? onTap;
  final String label;
  final double size;
  final bool playing;
  final bool brass;

  /// The circle stands on a paper sheet — it takes the ground's fill, or it would vanish into the paper (32-1, 32-7:
  /// «прослушать» 44 `#EFEBE3` in the sheet's corner).
  final bool onPaper;

  @override
  Widget build(BuildContext context) {
    final big = size >= 40;
    return Semantics(
      button: true,
      label: label,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: SizedBox(
          width: math.max(size, 44),
          height: math.max(size, 44),
          child: Center(
            child: Container(
              width: size,
              height: size,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: brass ? null : (onPaper ? AppColors.ground : AppColors.paper),
                border: Border.all(color: playing || brass ? AppColors.brassInk : AppColors.markerOutline, width: 1.5),
              ),
              child: playing
                  ? SessionWave(
                      heights: big ? const [8, 14, 18, 12, 16] : const [4, 7, 9, 6, 8],
                      barWidth: big ? 2.5 : 1.5,
                      playing: true,
                    )
                  : brass
                  ? Icon(LucideIcons.volume1, size: size / 2, color: AppColors.brassInk)
                  : Icon(LucideIcons.volume2, size: big ? 20 : 13, color: AppColors.ink),
            ),
          ),
        ),
      ),
    );
  }
}

/// ACTION BUTTON 56 — `#1B1A18`, corner radius 18, 17/600 in paper; disabled — an 8 % backing.
class SessionDockButton extends StatelessWidget {
  const SessionDockButton({super.key, required this.label, required this.onTap, this.enabled = true, this.busy = false});

  final String label;
  final VoidCallback? onTap;
  final bool enabled;

  /// The answer is still being sent to the server — the button waits.
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onTap != null && !busy;
    return Semantics(
      button: true,
      enabled: on,
      label: label,
      child: GestureDetector(
        onTap: on
            ? () {
                AppHaptics.light();
                onTap!();
              }
            : null,
        child: AnimatedContainer(
          duration: AppMotion.sessionChipSelect,
          height: 56,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: enabled ? AppColors.windowInk : AppColors.sessionSheetShadow,
            borderRadius: BorderRadius.circular(18),
          ),
          child: busy
              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.paper))
              : Text(label, style: enabled ? AppTextSession.dock : AppTextSession.dock.copyWith(color: AppColors.tertiary)),
        ),
      ),
    );
  }
}

/// DOCK AT THE BOTTOM OF THE SCREEN — a paper gradient over the feed and padding 14 / 24 / 24 (+ safe area).
class SessionDock extends StatelessWidget {
  const SessionDock({super.key, required this.child, this.fadeStop = 0.34});

  final Widget child;

  /// Where the gradient becomes solid: 34 % for the button, 22 % for options, 30 % for the microphone.
  final double fadeStop;

  /// The dock's top inset — a transparent edge that the card's area goes under.
  static const double topInset = 14;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: const [AppColors.groundClear, AppColors.ground],
        stops: [0, fadeStop],
      ),
    ),
    padding: EdgeInsets.fromLTRB(kSessionGutter, topInset, kSessionGutter, math.max(24, MediaQuery.paddingOf(context).bottom + 8)),
    child: child,
  );
}

/// WORD PHOTO — the tone while the photo is on its way; no photo — a `#E3DCCF` plate.
class SessionPhoto extends StatelessWidget {
  const SessionPhoto({super.key, required this.image, this.height, this.radius = 12});

  final CardImage? image;
  final double? height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final url = image?.url;
    final tone = AppColors.wireTone(image?.tone) ?? AppColors.photoPlaceholder;
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: SizedBox(
        height: height,
        width: double.infinity,
        child: ColoredBox(
          color: url == null ? AppColors.photoPlaceholder : tone,
          child: url == null
              ? null
              : Image(
                  image: CachedNetworkImage(url),
                  fit: BoxFit.cover,
                  frameBuilder: (_, child, frame, sync) => sync || frame != null ? child : const SizedBox.expand(),
                  errorBuilder: (_, _, _) => const SizedBox.expand(),
                ),
        ),
      ),
    );
  }
}

/// What is shown in the frame's slot.
enum SlotLook {
  /// Empty slot — brass outline, 8 % backing.
  empty,

  /// Filler in ink in a brass slot.
  filled,

  /// Passed — sage.
  sage,

  /// Mistake — ink outline (32-2c).
  wrong,
}

/// FRAME LINE WITH A SLOT — the text before the slot, the slot ([SlotLook]) and the text after; the slot is not
/// split and wraps as a whole. [frameColor] — the color of the frame itself (sage when the frame is passed
/// separately from the slot).
class SessionFrameText extends StatelessWidget {
  const SessionFrameText({
    super.key,
    required this.before,
    required this.after,
    required this.style,
    this.window = true,
    this.slot,
    this.look = SlotLook.empty,
    this.frameColor,
    this.underline,
    this.caret = false,
    this.textAlign = TextAlign.start,
    this.emptyWindow = kSessionEmptyWindow,
    this.onInk = false,
  });

  /// The card's frame: the slot in place of `___`; for a frame without a slot — the whole line, no slot.
  factory SessionFrameText.frame(
    CardFrame frame, {
    required TextStyle style,
    String? slot,
    SlotLook look = SlotLook.empty,
    Color? frameColor,
    bool caret = false,
    bool onInk = false,
    TextAlign textAlign = TextAlign.start,
  }) {
    final parts = frame.parts;
    return SessionFrameText(
      before: parts.before,
      after: parts.after,
      style: style,
      window: frame.hasSlot,
      slot: slot,
      look: look,
      frameColor: frameColor,
      caret: caret,
      onInk: onInk,
      textAlign: textAlign,
    );
  }

  /// A line without a slot — [before] as a whole (the key can be underlined).
  const SessionFrameText.plain(
    this.before, {
    super.key,
    required this.style,
    this.underline,
    this.frameColor,
    this.textAlign = TextAlign.start,
    this.onInk = false,
  }) : after = '',
       window = false,
       slot = null,
       look = SlotLook.empty,
       caret = false,
       emptyWindow = kSessionEmptyWindow;

  final String before;
  final String after;
  final TextStyle style;

  /// Whether to draw the slot between [before] and [after].
  final bool window;

  /// Text in the slot; null — an empty slot.
  final String? slot;
  final SlotLook look;
  final Color? frameColor;

  /// Underline a piece of [before] in brass (the phrase key, 32-6).
  final TextRange? underline;

  /// Caret in the slot — the slot is still being filled by voice (32-9b).
  final bool caret;
  final TextAlign textAlign;

  /// Size of the empty slot: 96 × 30 in phrase frames, 56 × 28 for a word in a line (31-7).
  final Size emptyWindow;

  /// The frame stands in the own (dark) bubble of the dialogue (33-2, 33-4, 35-2): an empty slot is filled with 18 %
  /// brass, a filled one keeps paper text, a passed one is sage on ink.
  final bool onInk;

  @override
  Widget build(BuildContext context) {
    final base = style.copyWith(color: frameColor ?? style.color);
    final spans = <InlineSpan>[..._withUnderline(before, base)];
    var rest = after;
    if (window) {
      // A filled slot sits on the text line (the baseline of the word in the slot is the phrase's baseline),
      // an empty one and one with a caret — in the middle of the line (the caret has no baseline, and the dry
      // computation of the paragraph height fails on it).
      final onLine = slot != null && !caret;
      // The punctuation mark right after the slot rides in the same placeholder: otherwise the line wraps
      // between the slot and the period.
      final mark = _closingMark.firstMatch(after)?.group(0) ?? '';
      rest = after.substring(mark.length);
      final box = _window(context);
      spans.add(WidgetSpan(
        alignment: onLine ? PlaceholderAlignment.baseline : PlaceholderAlignment.middle,
        baseline: onLine ? TextBaseline.alphabetic : null,
        child: mark.isEmpty
            ? box
            : Row(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: onLine ? CrossAxisAlignment.baseline : CrossAxisAlignment.center,
                textBaseline: onLine ? TextBaseline.alphabetic : null,
                // The slot shrinks and wraps its own text, the mark stays next to it.
                children: [Flexible(child: box), Text(mark, style: base)],
              ),
      ));
    }
    if (rest.isNotEmpty) spans.add(TextSpan(text: rest, style: base));
    return Text.rich(TextSpan(children: spans), textAlign: textAlign);
  }

  static final RegExp _closingMark = RegExp(r'^[.,!?;:…)»]+');

  List<InlineSpan> _withUnderline(String text, TextStyle base) {
    final u = underline;
    if (u == null || !u.isValid || u.end > text.length || u.start >= u.end) return [TextSpan(text: text, style: base)];
    return [
      TextSpan(text: u.textBefore(text), style: base),
      TextSpan(
        text: u.textInside(text),
        style: base.copyWith(decoration: TextDecoration.underline, decorationColor: AppColors.brassInk, decorationThickness: 1.5),
      ),
      TextSpan(text: u.textAfter(text), style: base),
    ];
  }

  Widget _window(BuildContext context) {
    final (border, fill, textColor) = onInk
        ? switch (look) {
            SlotLook.empty => (AppColors.brassInk, AppColors.sessionWindowFillOnInk, AppColors.paper),
            SlotLook.filled => (AppColors.brassInk, AppColors.sessionWindowFill, AppColors.paper),
            SlotLook.sage => (AppColors.brassInk, AppColors.sessionWindowFill, AppColors.sessionSageOnInk),
            SlotLook.wrong => (AppColors.paper, Colors.transparent, AppColors.paper),
          }
        : switch (look) {
            SlotLook.empty || SlotLook.filled => (AppColors.brassInk, AppColors.sessionWindowFill, AppColors.ink),
            SlotLook.sage => (AppColors.verdictKnown, AppColors.sessionSageWash, AppColors.verdictKnown),
            SlotLook.wrong => (AppColors.ink, Colors.transparent, AppColors.ink),
          };
    final value = slot;
    // An empty slot has the canvas size: otherwise a container without a child stretches across the whole line. A
    // slot with text is sized by the text — its own line height plus [kSessionSlotPadding] — and grows with it. The
    // padding is the same in every state: a tweened padding would put the text against the outline while the slot
    // fills.
    return AnimatedContainer(
      key: const ValueKey('session-slot'),
      duration: AppMotion.sessionSlotSage,
      margin: const EdgeInsets.symmetric(horizontal: 2),
      padding: kSessionSlotPadding,
      width: value == null ? emptyWindow.width : null,
      height: value == null ? emptyWindow.height : null,
      decoration: BoxDecoration(
        color: fill,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: border, width: 1.5),
      ),
      child: value == null
          ? (caret ? const _Caret() : null)
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Flexible(child: Text(value, key: const ValueKey('session-slot-text'), style: style.copyWith(color: textColor))),
                if (caret) const _Caret(),
              ],
            ),
    );
  }
}

/// Caret in a slot that is being filled by voice.
class _Caret extends StatelessWidget {
  const _Caret();

  @override
  Widget build(BuildContext context) => const Padding(padding: EdgeInsets.only(left: 2, top: 16), child: SessionCaret());
}

/// UNDERSCORE CARET — [width] × 2 in ink, blinks once a second (`om-caret`, steps(1)); under «Reduce Motion» it
/// stands still.
class SessionCaret extends StatefulWidget {
  const SessionCaret({super.key, this.width = 24});

  final double width;

  @override
  State<SessionCaret> createState() => _SessionCaretState();
}

class _SessionCaretState extends State<SessionCaret> {
  Timer? _t;
  bool _on = true;

  @override
  void initState() {
    super.initState();
    _t = Timer.periodic(AppMotion.sessionCaretPeriod ~/ 2, (_) {
      if (!mounted) return;
      if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return;
      setState(() => _on = !_on);
    });
  }

  @override
  void dispose() {
    _t?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) =>
      Opacity(opacity: _on ? 1 : 0, child: Container(width: widget.width, height: 2, color: AppColors.ink));
}

/// «WRONG» SHAKE — ±4 px, 120 ms × 2, when [trigger] changes to a new value.
class SessionShake extends StatefulWidget {
  const SessionShake({super.key, required this.trigger, required this.child});

  /// A new value — shake once (0 — don't shake).
  final int trigger;
  final Widget child;

  @override
  State<SessionShake> createState() => _SessionShakeState();
}

class _SessionShakeState extends State<SessionShake> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: AppMotion.sessionShake * 2);

  @override
  void didUpdateWidget(SessionShake old) {
    super.didUpdateWidget(old);
    if (widget.trigger != old.trigger && widget.trigger != 0 && !(MediaQuery.maybeDisableAnimationsOf(context) ?? false)) {
      unawaited(_c.forward(from: 0));
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    builder: (_, child) {
      // 0 → −4 → +4 → 0 twice, over two beats of 120 ms each.
      final t = _c.value * 2 % 1;
      final dx = _c.isAnimating ? AppMotion.sessionShakeOffset * math.sin(t * 2 * math.pi) * -1 : 0.0;
      return Transform.translate(offset: Offset(dx, 0), child: child);
    },
    child: widget.child,
  );
}

/// «CORRECT» CHECK — a sage circle 28 with a paper check 17, appears by scaling 0→1 (180 ms, ease-out-back).
class SessionCheckBadge extends StatelessWidget {
  const SessionCheckBadge({super.key, this.size = 28});

  final double size;

  @override
  Widget build(BuildContext context) {
    final badge = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
      child: Icon(LucideIcons.check, size: size * 0.6, color: AppColors.paper),
    );
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return badge;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.sessionCheckPop,
      curve: AppMotion.sessionEaseOutBack,
      builder: (_, s, child) => Transform.scale(scale: s, child: child),
      child: badge,
    );
  }
}

/// The «comes back tomorrow» dot 8 in brass — grows in (180 ms, ease-out-back).
class SessionReturnDot extends StatelessWidget {
  const SessionReturnDot({super.key});

  @override
  Widget build(BuildContext context) {
    const dot = SizedBox(
      width: 8,
      height: 8,
      child: DecoratedBox(decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.brassInk)),
    );
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return dot;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.sessionReturnDot,
      curve: AppMotion.sessionEaseOutBack,
      builder: (_, s, child) => Transform.scale(scale: s, child: child),
      child: dot,
    );
  }
}

/// Stage glyph 20 in a single color. One rule for the whole plan — `planStageMark`.
Widget sessionStageGlyph(PlanStage stage, Color color) => PlanStageGlyph(kind: planStageMark(stage), color: color);
