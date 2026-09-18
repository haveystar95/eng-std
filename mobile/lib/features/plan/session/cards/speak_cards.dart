import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/session/dialogue_feed.dart';
import '../../../../data/plan/session/heard_words.dart';
import '../../../../data/plan/session/live_line.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../../../../data/plan/session/speech_coverage.dart';
import '../../../../data/speech/speech_turn.dart';
import '../parts/session_bits.dart';
import '../parts/session_bubbles.dart';
import '../parts/session_line_sheet.dart';
import '../parts/session_mic_panel.dart';
import '../session_mic.dart';
import '../session_texts.dart';
import 'card_kit.dart';
import 'word_cards.dart' show autoplayOnce, kAutoplayDelay;

/// SPEAK MYSELF — canvas series 35 (work orders SESSION-1c §4, SESSION-2b §4): the learner answers the partner in
/// their own words (judged by meaning), repeats a line after a pause, and says their own line of the exchange again
/// (graded on the phone by coverage).

/// The judge's verdict flow of the answer (35-2 / 35-5): the recording goes to `…/judge`; accepted — the card shows
/// the pass; rejected — the reason and the exits over the microphone; «Skip» — `skipped` through the ordinary answer.
/// The client never writes a judged pass itself.
mixin _JudgedCardState<T extends StatefulWidget> on State<T> {
  CardEnv get env;

  late SessionMic mic;
  SessionJudgeOutcome? verdict;
  String? reason;
  bool judging = false;
  bool finished = false;
  int attempts = 0;
  String heard = '';
  bool _noMicReported = false;

  /// Whether the frame was on screen before the attempt that goes to the judge.
  bool get hintedNow => false;

  /// How the hint was opened, for `response.hinted_at`; null — no hint.
  String? get hintedAt => null;

  void onVerdict(SessionJudgeOutcome outcome) {}

  void initJudgedMic(String expected, List<String> contextual) {
    mic = env.makeMic(expected, contextual)..onTurn = _onTurn;
    mic.addListener(_onMic);
  }

  void disposeJudgedMic() {
    mic.removeListener(_onMic);
    mic.dispose();
  }

  void _onMic() {
    final noMic = mic.state == MicState.unavailable;
    if (noMic != _noMicReported) {
      _noMicReported = noMic;
      env.reportNoMic(noMic);
    }
    // A new recording after a rejection — the reason and the exits give way to the live line.
    if (mic.state == MicState.listening && reason != null) {
      reason = null;
      verdict = null;
    }
    if (mounted) setState(() {});
  }

  void _onTurn(MicTurn turn) {
    if (finished) return;
    if (turn.outcome != SpeechTurnOutcome.heard || turn.transcript.trim().isEmpty) {
      // Silence is not sent to the judge: the server would refuse it by code anyway — «say something» is local.
      attempts++;
      mic.settle(accepted: false);
      setState(() => reason = null);
      return;
    }
    unawaited(_judge(turn.transcript));
  }

  Future<void> _judge(String said) async {
    if (judging || finished) return;
    final hinted = hintedNow;
    setState(() {
      judging = true;
      reason = null;
      heard = said;
    });
    try {
      final outcome = await env.judge(said, hinted: hinted);
      if (!mounted) return;
      attempts = outcome.attempts;
      if (outcome.accepted) {
        mic.settle(accepted: true);
        AppHaptics.success();
        SessionSounds.verdict(correct: true);
        setState(() {
          verdict = outcome;
          finished = true;
          judging = false;
        });
      } else {
        mic.settle(accepted: false);
        AppHaptics.warning();
        SessionSounds.verdict(correct: false);
        setState(() {
          verdict = outcome;
          reason = outcome.reasonNative ?? '';
          judging = false;
        });
      }
      onVerdict(outcome);
    } catch (e) {
      if (!mounted) return;
      mic.settle(accepted: false);
      setState(() {
        reason = AppLocalizations.of(context).planSessionOffline;
        judging = false;
      });
    }
  }

  /// A rejected attempt is on screen — the exits stand over the microphone.
  bool get rejected => reason != null && !finished;

  /// «Try again» — the microphone and the bubble return to idle.
  void tryAgain() {
    mic.reset();
    setState(() {
      reason = null;
      verdict = null;
      heard = '';
    });
  }

  void skip({bool noMic = false}) {
    if (finished) return;
    finished = true;
    unawaited(env.voice.stop());
    env.submit(SessionAnswer(
      result: SessionResult.skipped,
      attempts: attempts < 1 ? 1 : attempts,
      response: SessionResponse(heard: heard.isEmpty ? null : heard, noMic: noMic ? true : null, hintedAt: hintedAt),
    ));
    env.reportNoMic(false);
    unawaited(env.next());
  }

  Widget? noMicView() => mic.state == MicState.unavailable
      ? SessionNoMicView(
          stageName: (s) => SessionTexts.stage(AppLocalizations.of(context), s),
          current: env.card.stage,
          done: env.stageDone,
          onSkip: () => skip(noMic: true),
          onAllow: () async {
            final ok = await mic.askAgain();
            if (!ok && mic.blockedInSettings) await env.openSettings();
          },
        )
      : null;
}

// ── 35-2 · 35-5 ───────────────────────────────────────────────────────────────────────────────────

/// How the frame hint was opened.
enum _Hint { none, silence, button }

/// ANSWER THE PARTNER (35-2; rejected — 35-5): the partner's line (`partner_line`) sounds when the card opens; the own
/// bubble waits with a wave over the task (`task_native`); the microphone; while recording the live line stands in the
/// own bubble. 5 s of silence without a recording — the frame hint (`hint`) rises over the microphone with a brass
/// slot. The attempt goes to the judge with `hinted` — true when the hint was on screen before it. Accepted — the frame
/// in the bubble with the judge's `slot_value` in sage, «by meaning ✓», a check, auto-advance. Rejected — the words
/// that do not belong fade in the bubble, the reason, three exits over the microphone: «Try again» (brass), «Hint»,
/// «Skip»; after the hint — two. «No hints» — no hint by silence and no «Hint».
class SpeakAnswerCard extends StatefulWidget {
  const SpeakAnswerCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final SpeakAnswerPayload payload;

  @override
  State<SpeakAnswerCard> createState() => _SpeakAnswerCardState();
}

class _SpeakAnswerCardState extends State<SpeakAnswerCard> with _JudgedCardState<SpeakAnswerCard> {
  static const _partnerKey = 'speak-partner';

  _Hint _hint = _Hint.none;
  Timer? _silence;
  Timer? _autoplay;

  SpeakAnswerPayload get p => widget.payload;

  String get _framePart => SessionRules.framePart(p.frame.frameTarget);

  @override
  CardEnv get env => widget.env;

  @override
  bool get hintedNow => _hint != _Hint.none;

  @override
  String? get hintedAt => switch (_hint) {
    _Hint.none => null,
    _Hint.silence => 'silence',
    _Hint.button => 'button',
  };

  @override
  void initState() {
    super.initState();
    initJudgedMic(_framePart, [p.ownLine.textTarget, _framePart, for (final f in p.frame.fillers) f.target]);
    mic.addListener(_onRecording);
    final line = p.partnerLine;
    if (line != null) {
      _autoplay = Timer(kAutoplayDelay, () async {
        if (!mounted) return;
        await env.voice.play(line.audio, fallback: line.textTarget, key: _partnerKey);
        if (mounted) _armSilence();
      });
    } else {
      _armSilence();
    }
  }

  @override
  void dispose() {
    _silence?.cancel();
    _autoplay?.cancel();
    mic.removeListener(_onRecording);
    disposeJudgedMic();
    super.dispose();
  }

  /// 5 s of silence before the first recording raise the frame hint — never under «No hints».
  void _armSilence() {
    if (env.noHints || _hint != _Hint.none) return;
    _silence?.cancel();
    _silence = Timer(AppMotion.sessionHintSilence, () {
      if (mounted && mic.state == MicState.idle && !finished && !rejected && _hint == _Hint.none) {
        setState(() => _hint = _Hint.silence);
      }
    });
  }

  void _onRecording() {
    if (mic.state == MicState.listening) _silence?.cancel();
  }

  void _showHint() {
    if (env.noHints) return;
    setState(() => _hint = _Hint.button);
  }

  @override
  void onVerdict(SessionJudgeOutcome outcome) {
    if (outcome.accepted) {
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(env.next());
      }));
    }
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicView();
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final line = p.partnerLine;
    final accepted = verdict?.accepted == true;
    return CardLayout(
      feed: true,
      bodyGap: 16,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskAnswerOwnWords),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (line != null) ...[
            SessionPartnerRow(
              bubble: SessionBubble(own: false, text: line.textTarget, translation: line.textNative),
              listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _partnerKey, size: 28, brass: true),
            ),
            const SizedBox(height: 8),
          ],
          SessionOwnRow(mark: accepted ? FeedMark.passed : FeedMark.none, bubble: _ownBubble(l)),
        ],
      ),
      bottom: _dock(l),
    );
  }

  Widget _ownBubble(AppLocalizations l) {
    final style = SessionBubble.lineStyle(own: true);
    final v = verdict;
    if (v != null && v.accepted) {
      final slot = v.slotValue;
      final filler = slot == null ? null : p.frame.fillers.where((f) => SpeechCoverage.containsSequence(slot, f.target, env.articles)).firstOrNull;
      final parts = p.frame.parts;
      return SessionBubble(
        own: true,
        translation: filler?.nativeLine ?? (slot == null ? p.taskNative : null),
        // «By meaning ✓» is the JUDGE's verdict, and a replay has no judge (FIX-1 §5): the pass there is the
        // phone's coverage, which says nothing about meaning.
        footer: env.judgedHere
            ? Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(l.planSessionByMeaning, key: const ValueKey('speak-by-meaning'), style: AppTextSession.meta.copyWith(color: AppColors.sessionSageOnInk)),
              )
            : null,
        child: p.frame.hasSlot && slot != null
            ? SessionFrameText(before: parts.before, after: parts.after, style: style, slot: slot, look: SlotLook.sage, onInk: true)
            : Text(p.frame.hasSlot ? heard : p.frame.frameTarget, style: style.copyWith(color: AppColors.sessionSageOnInk)),
      );
    }
    if (rejected || judging) {
      return SessionBubble(own: true, child: SessionInkLiveLine(words: LiveLine.of(heard, _framePart, listening: false), dimPlain: rejected));
    }
    if (mic.isListening && mic.partial.trim().isNotEmpty) {
      return SessionBubble(own: true, child: SessionInkLiveLine(words: LiveLine.of(mic.partial, _framePart, listening: !mic.closed)));
    }
    // Waiting for the answer: the wave and the task in the native language.
    return SessionBubble(
      own: true,
      translation: p.taskNative,
      child: const SessionWave(heights: SessionWave.five, width: 80),
    );
  }

  /// The frame hint over the microphone — the frame with an empty brass slot (a frame without a slot — the frame) and
  /// its caption.
  Widget? _hintView(AppLocalizations l) {
    if (_hint == _Hint.none || env.noHints || finished) return null;
    final parts = p.frame.parts;
    return SessionAppear(
      key: const ValueKey('speak-hint'),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SessionFrameText(
            before: parts.before,
            after: parts.after,
            style: AppTextSession.target22,
            window: p.frame.hasSlot,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 14),
          Text(_hint == _Hint.silence ? l.planSessionHintSilence : l.planSessionHintOpened, style: AppTextSession.meta),
        ],
      ),
    );
  }

  Widget _dock(AppLocalizations l) {
    final hint = _hintView(l);
    if (rejected) {
      final hinted = _hint != _Hint.none;
      return SessionMicPanel(
        mic: mic,
        expected: _framePart,
        onSkip: null,
        liveLine: MicLiveLine.none,
        top: hint,
        missedCaption: (reason ?? '').trim().isEmpty ? l.planSessionNotThat : reason,
        exits: [
          SessionTextExit(key: const ValueKey('exit-again'), label: l.planSessionTryAgain, brass: !hinted, onTap: tryAgain),
          if (!hinted && !env.noHints) SessionTextExit(key: const ValueKey('exit-hint'), label: l.planSessionHintAction, onTap: _showHint),
          SessionTextExit(key: const ValueKey('exit-skip'), label: l.planSessionSkip, onTap: skip),
        ],
      );
    }
    return SessionMicPanel(
      mic: mic,
      expected: _framePart,
      onSkip: finished || judging ? null : skip,
      liveLine: MicLiveLine.none,
      top: hint,
    );
  }
}

// ── 35-3 ──────────────────────────────────────────────────────────────────────────────────────────

enum _Echo { listening, waiting, ready }

/// ECHO AFTER A PAUSE (35-3): the partner's line sounds (the wave moves), its text closed; after the sound a brass ring
/// around the microphone holds `pause_ms` (the microphone inactive, «waiting»), then the microphone opens — the
/// recording starts on a tap, as on every card (30-3). The pass is coverage (`coverage_min`); after the answer the line
/// opens with every word heard marked in sage (the marks by word, the punctuation outside), and the card waits for
/// «Next». Two misses — `skipped`, the line opens too.
class SpeakEchoCard extends StatefulWidget {
  const SpeakEchoCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final SpeakEchoPayload payload;

  @override
  State<SpeakEchoCard> createState() => _SpeakEchoCardState();
}

class _SpeakEchoCardState extends State<SpeakEchoCard> with VoiceCardState<SpeakEchoCard> {
  static const _key = 'echo-line';

  _Echo _phase = _Echo.listening;
  Timer? _start;
  Timer? _pause;
  String _lastHeard = '';

  SpeakEchoPayload get p => widget.payload;

  @override
  CardEnv get env => widget.env;

  @override
  String get expectedSpeech => p.expectedText;

  @override
  bool accepts(String heard) => SessionRules.voiceAccepted(p, heard, env.articles);

  @override
  bool get autoAdvanceOnPass => false;

  @override
  void onAttempt(String heard, {required bool accepted}) => _lastHeard = heard;

  bool _passed = false;

  @override
  void onAccepted(String heard) => _passed = true;

  @override
  void initState() {
    super.initState();
    initVoice();
    _start = Timer(kAutoplayDelay, () => unawaited(_listen()));
  }

  @override
  void dispose() {
    _start?.cancel();
    _pause?.cancel();
    disposeVoice();
    super.dispose();
  }

  Future<void> _listen() async {
    if (!mounted) return;
    setState(() => _phase = _Echo.listening);
    await env.voice.play(p.partnerLine.audio, fallback: p.partnerLine.textTarget, key: _key);
    if (!mounted || done) return;
    setState(() => _phase = _Echo.waiting);
    _pause = Timer(_pauseLength, () {
      if (mounted && !done) setState(() => _phase = _Echo.ready);
    });
  }

  Duration get _pauseLength => Duration(milliseconds: p.pauseMs > 0 ? p.pauseMs : AppMotion.sessionPauseRing.inMilliseconds);

  /// The answer is given — passed or two misses: the line opens.
  bool get _revealed => _passed || skippedAfterMisses;

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final line = p.partnerLine;
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskRepeatPause),
      body: ValueListenableBuilder<Object?>(
        valueListenable: env.voice.playing,
        builder: (_, playing, _) => SessionLineSheet(
          revealed: _revealed,
          plate: _revealed
              ? SessionMarkedText(
                  key: const ValueKey('echo-text'),
                  text: line.textTarget,
                  style: AppTextSession.question.copyWith(letterSpacing: -0.26),
                  marks: [for (final r in HeardWords.matched(line.textTarget, _lastHeard)) (start: r.start, end: r.end, look: MarkLook.sage)],
                )
              : SessionPlateWave(key: const ValueKey('echo-wave'), playing: playing == _key),
          listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _key),
          eyebrow: l.planSessionBrowLine,
          meta: _revealed ? l.planSessionMatchedSage : l.planSessionTextClosed,
        ),
      ),
      bottom: _dock(l),
    );
  }

  Widget _dock(AppLocalizations l) {
    if (_passed) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(child: Text(l.planSessionMicHeard, style: AppTextSession.meta)),
          const SizedBox(height: 14),
          Center(
            child: SessionTextExit(
              key: const ValueKey('echo-replay'),
              label: l.planSessionReplay,
              onTap: () => unawaited(env.voice.play(p.partnerLine.audio, fallback: p.partnerLine.textTarget, key: _key)),
            ),
          ),
          const SizedBox(height: 14),
          SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
        ],
      );
    }
    if (skippedAfterMisses) return voiceDock(context);
    final waiting = _phase == _Echo.waiting;
    return SessionMicPanel(
      mic: mic,
      expected: expectedSpeech,
      onSkip: done ? null : () => skip(),
      showHeardLine: false,
      idleCaption: switch (_phase) {
        _Echo.listening => l.planSessionListenCue,
        _Echo.waiting => l.planSessionWaiting,
        _Echo.ready => null,
      },
      enabled: _phase == _Echo.ready,
      ring: waiting ? (button) => _PauseRing(duration: _pauseLength, child: button) : null,
    );
  }
}

/// THE PAUSE RING (35-3 «waiting · ring 3 s»): brass 1.5 around the 72 button — 88 wide at the start, shrinking to 72
/// over the pause, linear; under «Reduce Motion» it stands at 88.
class _PauseRing extends StatefulWidget {
  const _PauseRing({required this.duration, required this.child});

  final Duration duration;
  final Widget child;

  @override
  State<_PauseRing> createState() => _PauseRingState();
}

class _PauseRingState extends State<_PauseRing> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: widget.duration);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!(MediaQuery.maybeDisableAnimationsOf(context) ?? false) && !_c.isAnimating && _c.value == 0) unawaited(_c.forward());
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const ValueKey('echo-pause-ring'),
    width: 88,
    height: 88,
    child: AnimatedBuilder(
      animation: _c,
      builder: (_, child) {
        final size = 88 - 16 * _c.value;
        return Stack(
          alignment: Alignment.center,
          children: [
            Container(
              width: size,
              height: size,
              decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppColors.brassInk, width: 1.5)),
            ),
            child!,
          ],
        );
      },
      child: widget.child,
    ),
  );
}

// ── 35-4 ──────────────────────────────────────────────────────────────────────────────────────────

/// SAY YOUR LINE AGAIN (35-4, work order SESSION-2b §4 on the contract of BACK-TAILS-1 §1.1): the LEARNER'S own line
/// of the exchange sounds when the card opens, its target text closed (a wave on the plate, «listen» 44 in the
/// corner); under the plate its native text stands as the hint of the meaning. The microphone; the pass is coverage
/// of `expected_text` by `coverage_min` — the same rule as the echo, and there is no judge on this card. After the
/// attempt the target line opens in place of the wave, and the card waits for «Next»; two misses — `skipped`, the
/// line opens too.
class SpeakRetellCard extends StatefulWidget {
  const SpeakRetellCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final SpeakRetellPayload payload;

  @override
  State<SpeakRetellCard> createState() => _SpeakRetellCardState();
}

class _SpeakRetellCardState extends State<SpeakRetellCard> with VoiceCardState<SpeakRetellCard> {
  static const _key = 'retell-line';

  Timer? _autoplay;
  String _lastHeard = '';
  bool _passed = false;

  SpeakRetellPayload get p => widget.payload;

  @override
  CardEnv get env => widget.env;

  @override
  String get expectedSpeech => p.expectedText;

  @override
  bool accepts(String heard) => SessionRules.voiceAccepted(p, heard, env.articles);

  @override
  bool get autoAdvanceOnPass => false;

  @override
  void onAttempt(String heard, {required bool accepted}) => _lastHeard = heard;

  @override
  void onAccepted(String heard) => _passed = true;

  @override
  void initState() {
    super.initState();
    initVoice();
    _autoplay = autoplayOnce(this, env, p.ownLine.audio, p.ownLine.textTarget, _key);
  }

  @override
  void dispose() {
    _autoplay?.cancel();
    disposeVoice();
    super.dispose();
  }

  /// The answer is given — passed or two misses: the line opens.
  bool get _revealed => _passed || skippedAfterMisses;

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final line = p.ownLine;
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskRetell),
      body: ValueListenableBuilder<Object?>(
        valueListenable: env.voice.playing,
        builder: (_, playing, _) => SessionLineSheet(
          revealed: _revealed,
          plate: _revealed
              ? SessionMarkedText(
                  key: const ValueKey('retell-text'),
                  text: line.textTarget,
                  style: AppTextSession.question.copyWith(letterSpacing: -0.26),
                  marks: [for (final r in HeardWords.matched(line.textTarget, _lastHeard)) (start: r.start, end: r.end, look: MarkLook.sage)],
                )
              : SessionPlateWave(key: const ValueKey('retell-wave'), playing: playing == _key),
          listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _key),
          below: Text(line.textNative, key: const ValueKey('retell-native'), style: AppTextSession.body),
        ),
      ),
      bottom: _dock(l),
    );
  }

  Widget _dock(AppLocalizations l) {
    if (_passed) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(child: Text(l.planSessionMicHeard, style: AppTextSession.meta)),
          const SizedBox(height: 14),
          Center(
            child: SessionTextExit(
              key: const ValueKey('retell-replay'),
              label: l.planSessionReplay,
              onTap: () => unawaited(env.voice.play(p.ownLine.audio, fallback: p.ownLine.textTarget, key: _key)),
            ),
          ),
          const SizedBox(height: 14),
          SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
        ],
      );
    }
    return voiceDock(context, showHeardLine: false);
  }
}
