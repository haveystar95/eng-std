import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart' show kDebugMode;
import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../session/parts/session_bits.dart';
import '../session/parts/session_bubbles.dart';
import '../session/parts/session_mic_panel.dart' show SessionDebugHeardField, SessionTextExit;
import '../session/session_mic.dart';

/// THE TALK RIBBON — the component of кадры 37-6…37-8, and the one «Ответь своими словами» stands on
/// (35-2, «Лента: пузыри и микрофон — компонент „Лента разговора" 37-6…37-8»).
///
/// Two differences between the two screens, and they are the caller's to state, not the widget's:
/// in the trainer the partner's text is OPEN from the start, in the talk it is CLOSED and opens on a
/// tap on «текст»; and the trainer has «Пропустить», while the talk has «Не понял».

/// THE PARTNER'S LINE (37-6, 37-8, 35-2) — a paper bubble on the left with «прослушать» 28 beside it.
///
/// The text is [open] or closed: closed, the bubble holds a wave instead of words, and «текст» under
/// it opens them. Under «Без подсказок» the caller passes no [onOpenText] at all — then there is no
/// way to open the text and no button offering one (кадр 37-7, «примечание · без подсказок»).
class TalkPartnerBubble extends StatelessWidget {
  const TalkPartnerBubble({
    super.key,
    required this.text,
    required this.translation,
    required this.open,
    required this.playing,
    required this.onListen,
    this.onOpenText,
    this.interrupted = false,
  });

  final String text;
  final String? translation;

  /// The words are shown; false — a wave stands in their place.
  final bool open;

  /// The line is sounding now.
  final bool playing;
  final VoidCallback? onListen;

  /// «текст» — null when the text may not be opened (the talk under «Без подсказок»).
  final VoidCallback? onOpenText;

  /// The learner cut this line off (кадр 37-9) — it stays in the ribbon with «прервано».
  final bool interrupted;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final below = <Widget>[
      if (interrupted)
        Text(l.planTalkInterrupted, key: const ValueKey('talk-interrupted'), style: AppTextSession.meta),
      if (!open && onOpenText != null)
        SessionTextExit(key: const ValueKey('talk-open-text'), label: l.planTalkOpenText, onTap: onOpenText),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        SessionPartnerRow(
          bubble: SessionBubble(
            own: false,
            text: open ? text : null,
            translation: open ? translation : null,
            child: open ? null : SessionWave(key: const ValueKey('talk-line-wave'), heights: SessionWave.five, width: 80, playing: playing),
          ),
          listen: SessionListenButton(size: 28, brass: true, label: l.planWindowListen, playing: playing, onTap: onListen),
        ),
        if (below.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Row(mainAxisSize: MainAxisSize.min, children: below),
          ),
      ],
    );
  }
}

/// THE LEARNER'S LINE (37-8) — an ink bubble on the right holding ONLY what was recognised: no
/// translation under it and no verdict on it. The phrases of the day the SERVER heard in it are
/// underlined in sage ([marks], `[start, end)` in characters of [text]).
class TalkOwnBubble extends StatelessWidget {
  const TalkOwnBubble({super.key, this.text, this.marks = const [], this.child});

  final String? text;

  /// Ranges of [text] the server matched to phrases of the plan.
  final List<({int start, int end})> marks;

  /// Replaces the line — the live line while a recording is on.
  final Widget? child;

  @override
  Widget build(BuildContext context) => SessionOwnRow(
    bubble: SessionBubble(
      own: true,
      child: child ?? TalkSageUnderline(text: text ?? '', marks: marks, style: SessionBubble.lineStyle(own: true)),
    ),
  );
}

/// A LINE WITH PHRASES OF THE DAY UNDER A THIN SAGE RULE (37-8, «фразы дня видны тонкой линией
/// шалфея»). Ranges outside the text, or crossing each other, are ignored — a mark the client cannot
/// place is not drawn somewhere else.
class TalkSageUnderline extends StatelessWidget {
  const TalkSageUnderline({super.key, required this.text, required this.marks, required this.style});

  final String text;
  final List<({int start, int end})> marks;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    final valid = [
      for (final m in marks)
        if (m.start >= 0 && m.end <= text.length && m.start < m.end) m,
    ]..sort((a, b) => a.start.compareTo(b.start));
    final underlined = style.copyWith(
      decoration: TextDecoration.underline,
      decorationColor: AppColors.sessionSageOnInk,
      decorationThickness: 1.5,
    );
    final spans = <InlineSpan>[];
    var at = 0;
    for (final m in valid) {
      if (m.start < at) continue;
      if (m.start > at) spans.add(TextSpan(text: text.substring(at, m.start), style: style));
      spans.add(TextSpan(
        text: text.substring(m.start, m.end),
        style: underlined,
      ));
      at = m.end;
    }
    if (at < text.length) spans.add(TextSpan(text: text.substring(at), style: style));

    return Text.rich(TextSpan(children: spans), key: const ValueKey('talk-own-line'));
  }
}

/// «ВРАЧ ДУМАЕТ» (37-8) — three dots where the answer will stand. The talk has no «thinking» state on
/// the wire and needs none: the dots run while the request is in flight.
class TalkThinking extends StatefulWidget {
  const TalkThinking({super.key});

  @override
  State<TalkThinking> createState() => _TalkThinkingState();
}

class _TalkThinkingState extends State<TalkThinking> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) {
      _c.stop();
    } else if (!_c.isAnimating) {
      unawaited(_c.repeat());
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SessionPartnerRow(
    bubble: SessionBubble(
      own: false,
      child: AnimatedBuilder(
        key: const ValueKey('talk-thinking'),
        animation: _c,
        builder: (_, _) => SizedBox(
          height: 8,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (var i = 0; i < 3; i++) ...[
                if (i > 0) const SizedBox(width: 6),
                Opacity(
                  opacity: _c.isAnimating ? 0.35 + 0.65 * (0.5 - 0.5 * math.cos(2 * math.pi * ((_c.value + i * 0.18) % 1))) : 0.6,
                  child: const SizedBox(
                    width: 8,
                    height: 8,
                    child: DecoratedBox(decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.tertiary)),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    ),
  );
}

/// THE HINT CHIP (37-7, 35-2) — a LIGHT plate with the intention on the learner's language. Never an
/// ink bubble: an ink bubble on the right is only ever what the learner actually said (37-7,
/// «Тёмный пузырь»).
class TalkHintChip extends StatelessWidget {
  const TalkHintChip({super.key, required this.text, this.alignEnd = false});

  final String text;

  /// «Спроси сам» (35-2) stands the plate on the right, where the learner's own line will go.
  final bool alignEnd;

  @override
  Widget build(BuildContext context) => SessionAppear(
    child: Align(
      alignment: alignEnd ? Alignment.centerRight : Alignment.center,
      child: Container(
        key: const ValueKey('talk-hint-chip'),
        constraints: const BoxConstraints(maxWidth: kSessionBubbleMax),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: AppColors.paper,
          borderRadius: BorderRadius.circular(16),
          boxShadow: kSessionSheetShadow,
        ),
        child: Text(text, style: AppTextSession.option),
      ),
    ),
  );
}

/// What the talk's microphone is doing — the screen's own reading, drawn by [TalkMicButton].
enum TalkMicLook {
  /// The role is speaking: the button is dimmed and a tap on it CUTS THE LINE OFF (37-6, 37-9).
  dimmed,

  /// The learner's move: a brass ring pulses 1.6 s (37-7).
  waiting,

  /// Recording: a sage ring pulses 1.2 s, a tap closes the move (37-7 «слушаю»).
  listening,

  /// Heard and taken — sage with a check (35-2 «сказал · зачтено»). The talk itself never stands
  /// here: its move goes to the server the moment the recording closes.
  heard,

  /// The move is with the server — nothing to tap (37-8).
  busy,
}

/// THE TALK'S MICROPHONE 72 (37-6…37-9). Four looks, ONE rule: it opens only on a tap — no state of
/// the talk starts a recording by itself.
class TalkMicButton extends StatefulWidget {
  const TalkMicButton({super.key, required this.look, required this.onTap, required this.label});

  final TalkMicLook look;
  final VoidCallback? onTap;
  final String label;

  @override
  State<TalkMicButton> createState() => _TalkMicButtonState();
}

class _TalkMicButtonState extends State<TalkMicButton> with TickerProviderStateMixin {
  late final AnimationController _brass = AnimationController(vsync: this, duration: AppMotion.sessionRolePulse);
  late final AnimationController _sage = AnimationController(vsync: this, duration: AppMotion.sessionListenPulse);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sync();
  }

  @override
  void didUpdateWidget(TalkMicButton old) {
    super.didUpdateWidget(old);
    _sync();
  }

  void _sync() {
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    void run(AnimationController c, bool on) {
      if (on && !reduce && !c.isAnimating) {
        unawaited(c.repeat());
      } else if ((!on || reduce) && c.isAnimating) {
        c.stop();
        c.value = 0;
      }
    }

    run(_brass, widget.look == TalkMicLook.waiting);
    run(_sage, widget.look == TalkMicLook.listening);
  }

  @override
  void dispose() {
    _brass.dispose();
    _sage.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final look = widget.look;
    final icon = switch (look) {
      TalkMicLook.listening => Container(
        width: 18,
        height: 18,
        decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(4)),
      ),
      TalkMicLook.heard => const Icon(LucideIcons.check, size: 30, color: AppColors.paper),
      _ => const Icon(LucideIcons.mic, size: 30, color: AppColors.paper),
    };
    final pulse = look == TalkMicLook.listening ? _sage : _brass;
    final ring = switch (look) {
      TalkMicLook.waiting => AppColors.sessionBrassRing,
      TalkMicLook.listening => AppColors.sessionListenRing,
      _ => null,
    };

    return Semantics(
      button: true,
      label: widget.label,
      child: GestureDetector(
        key: const ValueKey('talk-mic'),
        onTap: widget.onTap == null
            ? null
            : () {
                AppHaptics.light();
                widget.onTap!();
              },
        child: AnimatedBuilder(
          animation: pulse,
          builder: (_, child) {
            // om-pulse: ring 8 → 12 → 8, opacity .30 → .14 → .30.
            final t = pulse.isAnimating ? (0.5 - 0.5 * math.cos(pulse.value * 2 * math.pi)) : 0.0;
            return Opacity(
              opacity: look == TalkMicLook.dimmed || look == TalkMicLook.busy ? 0.45 : 1,
              child: Container(
                width: 72,
                height: 72,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: look == TalkMicLook.heard ? AppColors.verdictKnown : AppColors.windowInk,
                  boxShadow: [
                    const BoxShadow(color: AppColors.sessionMicShadow, blurRadius: 24, offset: Offset(0, 8)),
                    if (ring != null)
                      BoxShadow(color: Color.lerp(ring, ring.withValues(alpha: .14), t)!, spreadRadius: 8 + 4 * t),
                  ],
                ),
                child: child,
              ),
            );
          },
          child: icon,
        ),
      ),
    );
  }
}

/// THE TALK'S DOCK (37-6…37-9): the chip over everything, the caption, the live line, and the row of
/// three — the left exit, the microphone 72 in the middle, the right exit.
///
/// The two exits are the caller's: in the talk «Не понял» stands on the left in EVERY state of the
/// learner's move, and «Подсказать» on the right until the chip is up; in 35-2 they are «Пропустить»
/// and «Подсказать».
class TalkDock extends StatelessWidget {
  const TalkDock({
    super.key,
    required this.mic,
    this.debugMic,
    this.chip,
    this.caption,
    this.subCaption,
    this.liveLine,
    this.left,
    this.right,
    this.notice,
  });

  final Widget mic;

  /// The microphone behind the button — the debug build puts its «what was heard» field under the
  /// dock, and the simulator has no other way to say anything (наряд SESSION-1b).
  final SessionMic? debugMic;

  /// The hint chip, over everything in the dock.
  final Widget? chip;

  /// «тап — говорить» / «слушай» / «говори, я слушаю».
  final String? caption;

  /// «тишина — конец» under the caption.
  final String? subCaption;

  /// The live line of the recording, over the caption.
  final Widget? liveLine;

  final Widget? left;
  final Widget? right;

  /// A failure plate over the dock (37-10) — it replaces nothing, it stands above.
  final Widget? notice;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (notice case final n?) ...[n, const SizedBox(height: 14)],
      if (chip case final c?) ...[c, const SizedBox(height: 14)],
      if (liveLine case final line?) ...[line, const SizedBox(height: 14)],
      if (caption case final text?) ...[
        Text(text, key: const ValueKey('talk-caption'), textAlign: TextAlign.center, style: AppTextSession.meta),
        const SizedBox(height: 6),
      ],
      if (subCaption case final text?) ...[
        Text(text, key: const ValueKey('talk-sub-caption'), textAlign: TextAlign.center, style: AppTextSession.meta),
        const SizedBox(height: 8),
      ],
      SizedBox(
        height: 72,
        // THE MICROPHONE IS LAST, SO IT IS HIT FIRST. The exits sit at the sides and their tap areas
        // (44 high, opaque) reach towards the middle; on a 375 phone «Подсказать» already overlaps
        // the button's centre, and a tap meant for the microphone was swallowed by the exit behind
        // it. The row paints in this order and hit-tests in reverse: the button always wins.
        child: Stack(
          alignment: Alignment.center,
          children: [
            if (left case final w?) Align(alignment: Alignment.centerLeft, child: w),
            if (right case final w?) Align(alignment: Alignment.centerRight, child: w),
            Center(child: mic),
          ],
        ),
      ),
      if (kDebugMode && debugMic != null) ...[
        const SizedBox(height: 10),
        SessionDebugHeardField(mic: debugMic!),
      ],
    ],
  );
}

/// A FAILURE PLATE (37-10) — one sentence and, when there is something to repeat, «Повторить». It
/// says what happened in what the learner can see: no «error», no «server», no «API».
class TalkNotice extends StatelessWidget {
  const TalkNotice({super.key, required this.text, this.onRetry});

  final String text;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Container(
    key: const ValueKey('talk-notice'),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(12), boxShadow: kSessionSheetShadow),
    child: Row(
      children: [
        Expanded(child: Text(text, style: AppTextSession.meta)),
        if (onRetry != null) ...[
          const SizedBox(width: 12),
          SessionTextExit(
            key: const ValueKey('talk-retry'),
            label: AppLocalizations.of(context).planTabRetry,
            brass: true,
            onTap: onRetry,
          ),
        ],
      ],
    ),
  );
}
