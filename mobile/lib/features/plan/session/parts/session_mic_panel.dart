import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/live_line.dart';
import '../session_mic.dart';
import 'session_bits.dart';

/// MICROPHONE (canvas 30-3) — the bottom zone of a voice card: caption, live line, wave, «Skip» and the
/// 72 button. Recording only on a press; «Skip» — only here.
///
/// States — per the canvas: idle («tap to speak») · listening, empty (caret, «go ahead, I'm listening») ·
/// listening, text coming in (live line: the matched part in sage, the last word in grey) · heard (sage button
/// with a check) · didn't catch («once more»). In a debug build, under the button — the «what was heard» field.
///
/// Series 33–35 (SESSION-1c) set the same panel differently: the live line may stand in the card ([liveLine]
/// `none` — the own bubble of the dialogue) or be the native retelling ([liveLine] `native`, 35-4); the captions of
/// a state may be the card's own («listen», «waiting», «in the native language»); [top] stands over everything (the
/// frame hint of 35-2); [exits] replace «Skip» (the three exits of 35-5); [ring] wraps the button (the pause ring
/// of 35-3) and [enabled] false leaves it inactive.
class SessionMicPanel extends StatelessWidget {
  const SessionMicPanel({
    super.key,
    required this.mic,
    required this.expected,
    required this.onSkip,
    this.missedCaption,
    this.showHeardLine = true,
    this.heardText,
    this.liveLine = MicLiveLine.target,
    this.idleCaption,
    this.showIdleCaption = true,
    this.listeningCaption,
    this.heardCaption,
    this.top,
    this.exits,
    this.ring,
    this.enabled = true,
  });

  final SessionMic mic;

  /// The reference text for the live line.
  final String expected;

  /// «Skip»; null — don't show it.
  final VoidCallback? onSkip;

  /// Caption instead of «didn't catch that, once more» (the judge's rejection reason, 32-9).
  final String? missedCaption;

  /// The line with what was heard, above «heard» (in 31-2 its place is taken by the echo in the sheet).
  final bool showHeardLine;

  /// What to write in the «heard» line — by default the transcript itself.
  final String? heardText;

  /// Where the live line stands and how it reads.
  final MicLiveLine liveLine;

  /// Captions instead of «tap to speak» / «go ahead, I'm listening» / «heard».
  final String? idleCaption;
  final String? listeningCaption;

  /// False — no caption in the idle state at all: the card's task line already says what to do (32-9, SESSION-2a §5).
  final bool showIdleCaption;
  final String? heardCaption;

  /// Over everything in the panel — the frame hint (35-2, 35-5).
  final Widget? top;

  /// Instead of «Skip» — the card's own exits, in their order (35-5).
  final List<Widget>? exits;

  /// Wraps the 72 button (the pause ring, 35-3).
  final Widget Function(Widget button)? ring;

  /// False — the button does not record (the pause of 35-3).
  final bool enabled;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: mic,
    builder: (context, _) {
      final l = AppLocalizations.of(context);
      final state = mic.state;
      final children = <Widget>[];
      void gap() => children.add(const SizedBox(height: 14));
      void skipOrExits({required bool active}) {
        if (exits case final list?) {
          for (final exit in list) {
            gap();
            children.add(exit);
          }
          return;
        }
        if (onSkip != null) {
          gap();
          children.add(_Skip(onTap: active ? onSkip! : null));
        }
      }

      if (top case final widget?) children.add(widget);
      if (children.isNotEmpty) gap();
      switch (state) {
        case MicState.idle || MicState.unavailable:
          if (showIdleCaption) {
            children.add(Text(idleCaption ?? l.planSessionMicTap, key: const ValueKey('session-mic-caption'), style: AppTextSession.meta));
          }
          skipOrExits(active: true);
        case MicState.listening:
          switch (liveLine) {
            case MicLiveLine.target:
              final words = LiveLine.of(mic.partial, expected, listening: !mic.closed);
              children.add(_LiveLineText(key: const ValueKey('session-live-line'), words: words));
              gap();
              if (mic.partial.trim().isEmpty) {
                children.add(Text(listeningCaption ?? l.planSessionMicListening, style: AppTextSession.meta));
                gap();
              }
              children.add(SessionWave(heights: SessionWave.five, playing: !mic.closed));
            case MicLiveLine.native:
              if (mic.partial.trim().isNotEmpty) {
                children.add(Text(
                  mic.partial,
                  key: const ValueKey('session-live-line'),
                  textAlign: TextAlign.center,
                  style: AppTextSession.option,
                ));
                gap();
              }
              children.add(SessionWave(heights: SessionWave.five, playing: !mic.closed));
              gap();
              children.add(Text(listeningCaption ?? l.planSessionMicListening, style: AppTextSession.meta));
            case MicLiveLine.none:
              children.add(SessionWave(heights: SessionWave.five, playing: !mic.closed));
              gap();
              children.add(Text(listeningCaption ?? l.planSessionMicListening, style: AppTextSession.meta));
          }
          skipOrExits(active: !mic.closed);
        case MicState.heard:
          if (showHeardLine && liveLine == MicLiveLine.target) {
            children.add(Text(
              heardText ?? mic.partial,
              key: const ValueKey('session-heard-line'),
              textAlign: TextAlign.center,
              style: AppTextSession.target22.copyWith(color: AppColors.verdictKnown),
            ));
            gap();
          }
          children.add(Text(heardCaption ?? l.planSessionMicHeard, key: const ValueKey('session-mic-caption'), style: AppTextSession.meta));
          if (exits case final list?) {
            for (final exit in list) {
              gap();
              children.add(exit);
            }
          }
        case MicState.missed:
          children.add(Text(
            missedCaption ?? l.planSessionMicMissed,
            key: const ValueKey('session-mic-caption'),
            textAlign: TextAlign.center,
            style: AppTextSession.meta,
          ));
          skipOrExits(active: true);
      }
      gap();
      final button = _MicButton(mic: mic, enabled: enabled);
      children.add(ring == null ? button : ring!(button));
      if (kDebugMode && state != MicState.heard && enabled) {
        children.add(const SizedBox(height: 10));
        children.add(_DebugHeardField(mic: mic));
      }
      return Column(mainAxisSize: MainAxisSize.min, children: children);
    },
  );
}

/// Where the microphone's live line stands (30-3).
enum MicLiveLine {
  /// In the dock, Literata 22: the matched words in sage, the last one grey (1b).
  target,

  /// In the card itself — the own bubble of the dialogue (33-3, 35-2); the dock keeps the wave and the caption.
  none,

  /// In the dock, Inter 17 in ink: the retelling in the native language, nothing to match (35-4).
  native,
}

/// A text exit of the dock — «Try again» in brass, «Hint», «Skip» (35-5); [brass] — the one brass exit.
class SessionTextExit extends StatelessWidget {
  const SessionTextExit({super.key, required this.label, required this.onTap, this.brass = false});

  final String label;
  final VoidCallback? onTap;
  final bool brass;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        child: Text(label, style: AppTextSession.skip.copyWith(color: brass ? AppColors.brassInk : null)),
      ),
    ),
  );
}

class _Skip extends StatelessWidget {
  const _Skip({required this.onTap});

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        child: Text(AppLocalizations.of(context).planSessionSkip, style: AppTextSession.skip),
      ),
    ),
  );
}

/// LIVE LINE — Literata 22: the matched part in sage, the last word in grey, empty — a caret.
class _LiveLineText extends StatelessWidget {
  const _LiveLineText({super.key, required this.words});

  final List<LiveWord> words;

  @override
  Widget build(BuildContext context) {
    if (words.isEmpty) {
      return const SizedBox(height: 28, child: Center(child: SessionCaret(width: 16)));
    }
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 28),
      child: Text.rich(
        TextSpan(
          children: [
            for (var i = 0; i < words.length; i++)
              TextSpan(
                text: i == 0 ? words[i].text : ' ${words[i].text}',
                style: AppTextSession.target22.copyWith(
                  color: switch (words[i].tone) {
                    LiveTone.matched => AppColors.verdictKnown,
                    LiveTone.pending => AppColors.tertiary,
                    LiveTone.plain => AppColors.ink,
                  },
                ),
              ),
          ],
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}

/// MICROPHONE BUTTON 72: idle — charcoal with a microphone; listening — «stop» and a 30 % sage ring with a
/// 1.2 s pulse; heard — sage with a check; didn't catch — «repeat».
class _MicButton extends StatefulWidget {
  const _MicButton({required this.mic, this.enabled = true});

  final SessionMic mic;

  /// False — the button looks the same and does not record (the pause ring of 35-3 holds it).
  final bool enabled;

  @override
  State<_MicButton> createState() => _MicButtonState();
}

class _MicButtonState extends State<_MicButton> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(vsync: this, duration: AppMotion.sessionListenPulse);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncPulse();
  }

  @override
  void didUpdateWidget(_MicButton old) {
    super.didUpdateWidget(old);
    _syncPulse();
  }

  /// The ring pulse — only while recording is on; under «Reduce Motion» the ring stands still.
  void _syncPulse() {
    final mic = widget.mic;
    final listening = mic.state == MicState.listening && !mic.closed;
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (listening && !reduce && !_pulse.isAnimating) {
      unawaited(_pulse.repeat());
    } else if ((!listening || reduce) && _pulse.isAnimating) {
      _pulse.stop();
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final mic = widget.mic;
    final state = mic.state;
    final listening = state == MicState.listening && !mic.closed;
    final heard = state == MicState.heard;
    final icon = switch (state) {
      MicState.listening => Container(
        width: 18,
        height: 18,
        decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(4)),
      ),
      MicState.heard => const Icon(LucideIcons.check, size: 30, color: AppColors.paper),
      MicState.missed => const Icon(LucideIcons.rotateCw, size: 28, color: AppColors.paper),
      MicState.idle || MicState.unavailable => const Icon(LucideIcons.mic, size: 30, color: AppColors.paper),
    };
    return Semantics(
      button: true,
      label: l.planSessionMicTap,
      child: GestureDetector(
        onTap: heard || !widget.enabled ? null : () => unawaited(mic.tap()),
        child: AnimatedBuilder(
          animation: _pulse,
          builder: (_, child) {
            // om-pulse: ring 8 → 12 → 8, opacity .30 → .14 → .30.
            final t = listening ? (0.5 - 0.5 * math.cos(_pulse.value * 2 * math.pi)) : 0.0;
            return Container(
              width: 72,
              height: 72,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: heard ? AppColors.verdictKnown : AppColors.windowInk,
                boxShadow: [
                  const BoxShadow(color: AppColors.sessionMicShadow, blurRadius: 24, offset: Offset(0, 8)),
                  if (state == MicState.listening)
                    BoxShadow(
                      color: Color.lerp(AppColors.sessionListenRing, AppColors.sessionListenRing.withValues(alpha: .14), t)!,
                      spreadRadius: 8 + 4 * t,
                    ),
                ],
              ),
              child: child,
            );
          },
          child: icon,
        ),
      ),
    );
  }
}

/// «WHAT WAS HEARD» FIELD — debug build only: the text is sent as if recognized (a simulator has no microphone).
class _DebugHeardField extends StatefulWidget {
  const _DebugHeardField({required this.mic});

  final SessionMic mic;

  @override
  State<_DebugHeardField> createState() => _DebugHeardFieldState();
}

class _DebugHeardFieldState extends State<_DebugHeardField> {
  final _text = TextEditingController();

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  void _submit() {
    final value = _text.text;
    if (value.trim().isEmpty) return;
    _text.clear();
    FocusScope.of(context).unfocus();
    widget.mic.submitDebug(value);
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 36,
    child: TextField(
      key: const ValueKey('session-debug-heard'),
      controller: _text,
      style: AppTextSession.meta.copyWith(color: AppColors.ink),
      textInputAction: TextInputAction.done,
      onSubmitted: (_) => _submit(),
      decoration: InputDecoration(
        isDense: true,
        hintText: AppLocalizations.of(context).planSessionDebugHeard,
        hintStyle: AppTextSession.meta,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        filled: true,
        fillColor: AppColors.paper,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.markerOutline)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.markerOutline)),
        suffixIcon: IconButton(
          icon: const Icon(LucideIcons.arrowUp, size: 16, color: AppColors.ink),
          onPressed: _submit,
        ),
      ),
    ),
  );
}

/// «MICROPHONE NEEDED» (canvas 30-3, «no permission · screen»): title, text, a sheet with the two stages that
/// cannot be passed without a microphone and their state — «ahead» from the words and phrases, as the canvas draws
/// it; met inside «Listen and answer» or «Speak myself» (SESSION-1c) the stage passed says so and the one under way
/// says «in progress» — the microphone button; at the bottom «Skip» and «Allow».
class SessionNoMicView extends StatelessWidget {
  const SessionNoMicView({
    super.key,
    required this.onAllow,
    required this.onSkip,
    required this.stageName,
    this.current,
    this.done,
  });

  final VoidCallback onAllow;
  final VoidCallback onSkip;
  final String Function(PlanStage stage) stageName;

  /// The stage the card belongs to.
  final PlanStage? current;

  /// Whether a stage is answered in full; null — none is.
  final bool Function(PlanStage stage)? done;

  String _state(AppLocalizations l, PlanStage stage) {
    if (done?.call(stage) ?? false) return l.planSessionStateDone;
    if (stage == current) return l.planWindowStateInProgress;
    return l.planSessionStateAhead;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(kSessionGutter, 50, kSessionGutter, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l.planSessionNoMicTitle, style: AppTextSession.stageTitle),
                const SizedBox(height: 14),
                Text(l.planSessionNoMicBody, style: AppTextSession.body),
                const SizedBox(height: 32),
                SessionSheet(
                  child: Column(
                    children: [
                      for (final s in const [PlanStage.listen, PlanStage.speak]) ...[
                        if (s == PlanStage.speak) const SizedBox(height: 8),
                        SizedBox(
                          height: 20,
                          child: Row(
                            children: [
                              _StageGlyph(stage: s),
                              const SizedBox(width: 12),
                              Expanded(child: Text(stageName(s), style: AppTextSession.text15)),
                              Text(_state(l, s), key: ValueKey('no-mic-state-${s.name}'), style: AppTextSession.meta),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 110),
                Center(
                  child: Container(
                    width: 72,
                    height: 72,
                    alignment: Alignment.center,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.windowInk,
                      boxShadow: [BoxShadow(color: AppColors.sessionMicShadow, blurRadius: 24, offset: Offset(0, 8))],
                    ),
                    child: const Icon(LucideIcons.mic, size: 30, color: AppColors.paper),
                  ),
                ),
              ],
            ),
          ),
        ),
        SessionDock(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(child: _Skip(onTap: onSkip)),
              const SizedBox(height: 14),
              SessionDockButton(label: l.planSessionNoMicAllow, onTap: onAllow),
            ],
          ),
        ),
      ],
    );
  }
}

class _StageGlyph extends StatelessWidget {
  const _StageGlyph({required this.stage});

  final PlanStage stage;

  @override
  Widget build(BuildContext context) => sessionStageGlyph(stage, AppColors.tertiary);
}
