import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/session/dialogue_feed.dart';
import '../../../../data/plan/session/heard_words.dart';
import '../../../../data/plan/session/live_line.dart';
import '../../../../data/plan/session/session_models.dart';

/// BUBBLES OF THE CONVERSATION (canvas components «Пузыри диалога 23-0d» as the session draws them: series 33, 34-3,
/// 34-5, 35-2, 35-5, 35-6; work order SESSION-1c). The partner's line is a paper bubble on the left with a brass
/// «listen» 28 ten to its right; the learner's is an ink bubble on the right with its marker 14 ten to its left;
/// Literata 22 over the translation 15; a bubble is at most 274 wide (80 % of the field); lines of one exchange stand
/// 8 apart, exchanges 16. No avatars — the role reads by side and colour.

/// The widest a bubble grows — 80 % of the 342 field.
const double kSessionBubbleMax = 274;

/// ONE BUBBLE — [own] ink on the right, otherwise paper on the left; [child] replaces the line (a wave, a frame with
/// its slot, the live line); [footer] stands under the translation («by meaning ✓», «Didn't catch that»).
class SessionBubble extends StatelessWidget {
  const SessionBubble({
    super.key,
    required this.own,
    this.text,
    this.translation,
    this.child,
    this.footer,
    this.padding = const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    this.shadow,
  });

  final bool own;
  final String? text;
  final String? translation;
  final Widget? child;
  final Widget? footer;

  /// 14 / 12, as every bubble of the canvas; a plate whose content brings its own 44 touch boxes trims it (37-6).
  final EdgeInsets padding;

  /// The talk's plates stand on the ribbon with the canvas shadow (37-6…37-9); the dialogue's bubbles do not.
  final List<BoxShadow>? shadow;

  /// The bubble's line style — Literata 22 in ink or paper.
  static TextStyle lineStyle({required bool own}) => AppTextSession.target22.copyWith(color: own ? AppColors.paper : AppColors.ink);

  /// The translation's style — 15 secondary, paper 72 % on ink.
  static TextStyle translationStyle({required bool own}) => AppTextSession.body.copyWith(color: own ? AppColors.paper72 : AppColors.secondary);

  @override
  Widget build(BuildContext context) => _MaxWidthBox(
    maxWidth: kSessionBubbleMax,
    child: Container(
      padding: padding,
      decoration: BoxDecoration(color: own ? AppColors.windowInk : AppColors.paper, borderRadius: BorderRadius.circular(16), boxShadow: shadow),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          child ?? Text(text ?? '', style: lineStyle(own: own)),
          if (translation != null && translation!.trim().isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(translation!, style: translationStyle(own: own)),
          ],
          ?footer,
        ],
      ),
    ),
  );
}

/// A MAX-WIDTH BOX MEASURED AT ITS OWN WIDTH. [ConstrainedBox] hands its child the parent's width when a height is
/// measured before layout, so a bubble in a 296 row was measured a line or two shorter than it lays out at 274 — and
/// a conversation taller than the screen (the feed, the review), whose height is measured that way, overflowed.
class _MaxWidthBox extends SingleChildRenderObjectWidget {
  const _MaxWidthBox({required this.maxWidth, super.child});

  final double maxWidth;

  @override
  RenderObject createRenderObject(BuildContext context) => _RenderMaxWidthBox(maxWidth);

  @override
  void updateRenderObject(BuildContext context, _RenderMaxWidthBox renderObject) => renderObject.maxWidth = maxWidth;
}

class _RenderMaxWidthBox extends RenderConstrainedBox {
  _RenderMaxWidthBox(double maxWidth) : super(additionalConstraints: BoxConstraints(maxWidth: maxWidth));

  set maxWidth(double value) => additionalConstraints = BoxConstraints(maxWidth: value);

  double get _cap => additionalConstraints.maxWidth;

  @override
  double computeMinIntrinsicHeight(double width) => super.computeMinIntrinsicHeight(math.min(width, _cap));

  @override
  double computeMaxIntrinsicHeight(double width) => super.computeMaxIntrinsicHeight(math.min(width, _cap));
}

/// THE PARTNER'S ROW — the bubble on the left, «listen» 28 ten to its right (12 from the top).
class SessionPartnerRow extends StatelessWidget {
  const SessionPartnerRow({super.key, required this.bubble, this.listen});

  final Widget bubble;
  final Widget? listen;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Flexible(child: bubble),
      if (listen != null) ...[
        const SizedBox(width: 2),
        // «Listen» 28 in a 44 tap area: the circle stands 10 from the bubble and 12 from its top, as in the canvas.
        Transform.translate(offset: const Offset(0, 4), child: listen!),
      ],
    ],
  );
}

/// THE LEARNER'S ROW — the bubble on the right, the marker ten to its left (19 from the top); [listen] — «listen» 28
/// between them, as the day window draws the learner's line (23-0d) — the review (34-3) plays every line.
class SessionOwnRow extends StatelessWidget {
  const SessionOwnRow({super.key, required this.bubble, this.mark = FeedMark.none, this.listen});

  final Widget bubble;
  final FeedMark mark;
  final Widget? listen;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisAlignment: MainAxisAlignment.end,
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      if (mark != FeedMark.none) ...[
        Padding(padding: const EdgeInsets.only(top: 19), child: SessionBubbleMarker(mark: mark)),
        const SizedBox(width: 10),
      ],
      if (listen != null) ...[Transform.translate(offset: const Offset(0, 4), child: listen!), const SizedBox(width: 2)],
      Flexible(child: bubble),
    ],
  );
}

/// THE MARKER 14 in the corner of an own bubble: said — sage with a paper check; coming back tomorrow — a brass dot;
/// both ringed with paper 2.
class SessionBubbleMarker extends StatelessWidget {
  const SessionBubbleMarker({super.key, required this.mark});

  final FeedMark mark;

  @override
  Widget build(BuildContext context) => Container(
    key: ValueKey('bubble-mark-${mark.name}'),
    width: 14,
    height: 14,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: mark == FeedMark.returns ? AppColors.brassInk : AppColors.verdictKnown,
      boxShadow: const [BoxShadow(color: AppColors.paper90, spreadRadius: 2)],
    ),
    child: mark == FeedMark.passed ? const Icon(LucideIcons.check, size: 8, color: AppColors.paper) : null,
  );
}

/// THE CONVERSATION SO FAR (33-7) — the feed's bubbles, 8 apart inside an exchange and 16 between exchanges.
/// [listen] builds the «listen» 28 of a partner's line.
class SessionFeedView extends StatelessWidget {
  const SessionFeedView({super.key, required this.lines, required this.listen});

  final List<FeedLine> lines;
  final Widget Function(CardLine line) listen;

  @override
  Widget build(BuildContext context) => Column(
    key: const ValueKey('session-feed'),
    crossAxisAlignment: CrossAxisAlignment.stretch,
    mainAxisSize: MainAxisSize.min,
    children: [
      for (final (i, f) in lines.indexed) ...[
        if (i > 0) SizedBox(height: lines[i - 1].exchangeRef == f.exchangeRef ? 8 : 16),
        if (f.own)
          SessionOwnRow(
            mark: f.mark,
            bubble: SessionBubble(own: true, text: f.line.textTarget, translation: f.line.textNative),
          )
        else
          SessionPartnerRow(
            bubble: SessionBubble(own: false, text: f.line.textTarget, translation: f.line.textNative),
            listen: listen(f.line),
          ),
      ],
    ],
  );
}

/// «PARTNER BUBBLE · APPEARS» — a fade and an 8 px rise, 200 ms ease-out; under «Reduce Motion» it simply stands.
class SessionAppear extends StatelessWidget {
  const SessionAppear({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.sessionBubbleIn,
      curve: AppMotion.sessionEaseOut,
      builder: (_, t, c) => Opacity(
        opacity: t,
        child: Transform.translate(offset: Offset(0, AppMotion.sessionBubbleShift * (1 - t)), child: c),
      ),
      child: child,
    );
  }
}

/// THE LIVE LINE IN THE OWN BUBBLE (35-2 «speaking») — Literata 22 on ink: the words that belong to the expected line
/// in sage, the last word while recording dimmed to 45 %, the rest in paper. A rejected line is not drawn here: it is
/// what the learner said, plain, in paper (кадр 35-2 «сказал · не зачтено»).
class SessionInkLiveLine extends StatelessWidget {
  const SessionInkLiveLine({super.key, required this.words});

  final List<LiveWord> words;

  @override
  Widget build(BuildContext context) {
    final style = SessionBubble.lineStyle(own: true);
    return Text.rich(
      key: const ValueKey('bubble-live-line'),
      TextSpan(
        children: [
          for (final (i, w) in words.indexed)
            TextSpan(
              text: i == 0 ? w.text : ' ${w.text}',
              style: style.copyWith(
                color: switch (w.tone) {
                  LiveTone.matched => AppColors.sessionSageOnInk,
                  LiveTone.pending => AppColors.sessionPaperDim,
                  LiveTone.plain => AppColors.paper,
                },
              ),
            ),
        ],
      ),
    );
  }
}

/// THE HINTED LINE WHILE IT IS SAID (33-3 «listening · text coming in») — the own line stays in the bubble, the words
/// already heard in paper, the rest dimmed to 45 %.
class SessionHeardLine extends StatelessWidget {
  const SessionHeardLine({super.key, required this.text, required this.heard});

  final String text;
  final String heard;

  @override
  Widget build(BuildContext context) {
    final style = SessionBubble.lineStyle(own: true);
    final spans = <InlineSpan>[];
    var at = 0;
    for (final r in HeardWords.matched(text, heard)) {
      if (r.start > at) spans.add(TextSpan(text: text.substring(at, r.start), style: style.copyWith(color: AppColors.sessionPaperDim)));
      spans.add(TextSpan(text: text.substring(r.start, r.end), style: style));
      at = r.end;
    }
    if (at < text.length) spans.add(TextSpan(text: text.substring(at), style: style.copyWith(color: AppColors.sessionPaperDim)));
    return Text.rich(key: const ValueKey('bubble-heard-line'), TextSpan(children: spans));
  }
}

/// A BRASS OUTLINE BUTTON 44 — «Didn't catch that» in the partner's bubble (33-6), «slowly» in the sheet (34-6): brass
/// 1.5 outline, radius 12, 15/500 brass text.
class SessionBrassButton extends StatelessWidget {
  const SessionBrassButton({super.key, required this.label, required this.onTap, this.semanticsLabel});

  final String label;
  final VoidCallback? onTap;

  /// What a screen reader says instead of [label].
  final String? semanticsLabel;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    enabled: onTap != null,
    label: semanticsLabel ?? label,
    excludeSemantics: true,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap == null
          ? null
          : () {
              AppHaptics.light();
              onTap!();
            },
      child: Container(
        height: 44,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.brassInk, width: 1.5)),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [Text(label, style: AppTextSession.skip.copyWith(color: AppColors.brassInk))],
        ),
      ),
    ),
  );
}

/// How a piece of a line is marked.
enum MarkLook {
  /// Understood / matched — a sage wash 15 % (34-3, 34-7, 35-3).
  sage,

  /// Where the answer was and was missed — a brass outline over 8 % brass (34-3).
  brass,
}

/// A marked range of a line, `[start, end)` in characters.
typedef TextMark = ({int start, int end, MarkLook look});

/// A LINE WITH MARKED PIECES — each marked range stands in its own rounded box (radius 6), the rest of the line
/// flows around it; ranges outside the text are ignored.
class SessionMarkedText extends StatelessWidget {
  const SessionMarkedText({
    super.key,
    required this.text,
    required this.marks,
    required this.style,
    this.textAlign = TextAlign.start,
    this.onInk = false,
  });

  final String text;
  final List<TextMark> marks;
  final TextStyle style;
  final TextAlign textAlign;

  /// The line is in an own (ink) bubble: a sage mark also turns its words sage — a 15 % wash alone does not read on ink.
  final bool onInk;

  @override
  Widget build(BuildContext context) {
    final valid = [
      for (final m in marks)
        if (m.start >= 0 && m.end <= text.length && m.start < m.end) m,
    ]..sort((a, b) => a.start.compareTo(b.start));
    final spans = <InlineSpan>[];
    var at = 0;
    for (final m in valid) {
      if (m.start < at) continue;
      if (m.start > at) spans.add(TextSpan(text: text.substring(at, m.start), style: style));
      spans.add(WidgetSpan(
        alignment: PlaceholderAlignment.baseline,
        baseline: TextBaseline.alphabetic,
        child: Container(
          key: ValueKey('mark-${m.look.name}-${m.start}'),
          padding: const EdgeInsets.symmetric(horizontal: 3),
          decoration: BoxDecoration(
            color: m.look == MarkLook.sage ? AppColors.sessionSageWash : AppColors.sessionWindowFill,
            borderRadius: BorderRadius.circular(6),
            border: m.look == MarkLook.brass ? Border.all(color: AppColors.brassInk, width: 1.5) : null,
          ),
          child: Text(
            text.substring(m.start, m.end),
            style: onInk && m.look == MarkLook.sage ? style.copyWith(color: AppColors.sessionSageOnInk) : style,
          ),
        ),
      ));
      at = m.end;
    }
    if (at < text.length) spans.add(TextSpan(text: text.substring(at), style: style));
    return Text.rich(TextSpan(children: spans), textAlign: textAlign);
  }
}
