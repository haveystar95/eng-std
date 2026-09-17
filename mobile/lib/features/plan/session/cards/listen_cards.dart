import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/session/listen_timeline.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../ui/scene_circle.dart';
import '../parts/session_bits.dart';
import '../parts/session_bubbles.dart';
import '../parts/session_choice.dart';
import '../parts/session_line_sheet.dart';
import '../parts/session_mic_panel.dart' show SessionTextExit;
import 'card_kit.dart';
import 'word_cards.dart' show autoplayOnce, kAutoplayDelay;

/// LISTEN AND ANSWER — canvas series 34 (work order SESSION-1c, section 3): the whole visit by ear, questions about it
/// from memory, the review, the pause-prediction, two tempos, a number in the stream. Unit `day` (a question — `L2`):
/// nothing here comes back tomorrow, and a failed question is final — the server deals no copy.

/// «m:ss» of the player's clock.
String _clock(Duration d) {
  final s = d.inSeconds;
  return '${s ~/ 60}:${(s % 60).toString().padLeft(2, '0')}';
}

// ── 34-1 ──────────────────────────────────────────────────────────────────────────────────────────

enum _Player { idle, playing, paused, done }

/// THE VISIT PLAYER (34-1): two roles as circles (the scene's photo — the partner, the learner's circle), a brass ring
/// with a pulse on the one that speaks; the bar with the exchange marks (`exchange_step`) and the time (`total_ms`, or
/// the sum of the lines' `duration_ms`); the files play one after another as one stream, 300 ms apart
/// ([AppMotion.sessionVisitLineGap]). «In parts» pauses after every
/// exchange — «Continue»; «Once more» starts over; no text of the lines. Listened to the end — «Next» → `passed`.
class ListenDialogueCard extends StatefulWidget {
  const ListenDialogueCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final ListenDialoguePayload payload;

  @override
  State<ListenDialogueCard> createState() => _ListenDialogueCardState();
}

class _ListenDialogueCardState extends State<ListenDialogueCard> {
  late final ListenTimeline _timeline = ListenTimeline.of(widget.payload);

  _Player _state = _Player.idle;

  /// The line playing, or the next one to play (on a pause); `lines.length` — played to the end.
  int _line = 0;
  bool _byParts = false;

  /// A new run cuts the loop of the old one.
  int _run = 0;
  DateTime? _lineStarted;

  /// Played time measured by the clock — when the lines' lengths are unknown.
  Duration _played = Duration.zero;
  Timer? _frame;
  Timer? _autostart;

  CardEnv get env => widget.env;
  List<CardVisitLine> get _lines => widget.payload.lines;

  @override
  void initState() {
    super.initState();
    _autostart = Timer(kAutoplayDelay, () {
      if (mounted && _state == _Player.idle) unawaited(_play(from: 0));
    });
  }

  @override
  void dispose() {
    _run++;
    _autostart?.cancel();
    _frame?.cancel();
    super.dispose();
  }

  Future<void> _play({required int from}) async {
    final run = ++_run;
    _frame?.cancel();
    _frame = Timer.periodic(AppMotion.sessionPlayerFrame, (_) {
      if (mounted) setState(() {});
    });
    setState(() => _state = _Player.playing);
    for (var i = from; i < _lines.length; i++) {
      if (!mounted || run != _run) return;
      setState(() {
        _line = i;
        _lineStarted = DateTime.now();
      });
      final line = _lines[i];
      await env.voice.play(line.audio, fallback: line.textTarget, key: 'visit-$i');
      if (!mounted || run != _run) return;
      _played += DateTime.now().difference(_lineStarted!);
      if (_byParts && _timeline.endsExchange(i) && i < _lines.length - 1) {
        _frame?.cancel();
        setState(() {
          _line = i + 1;
          _lineStarted = null;
          _state = _Player.paused;
        });
        return;
      }
      if (i < _lines.length - 1) {
        await Future<void>.delayed(AppMotion.sessionVisitLineGap);
        if (!mounted || run != _run) return;
      }
    }
    _frame?.cancel();
    setState(() {
      _line = _lines.length;
      _lineStarted = null;
      _state = _Player.done;
    });
  }

  Future<void> _again() async {
    _run++;
    _autostart?.cancel();
    await env.voice.stop();
    if (!mounted) return;
    _played = Duration.zero;
    await _play(from: 0);
  }

  Duration get _intoLine => _lineStarted == null ? Duration.zero : DateTime.now().difference(_lineStarted!);

  double get _progress => switch (_state) {
    _Player.idle => 0,
    _Player.done => 1,
    _Player.paused => _timeline.progress(_line, Duration.zero),
    _Player.playing => _timeline.progress(_line, _intoLine),
  };

  /// The clock: the played time while the visit goes, its whole length before and after.
  String get _time {
    final known = _timeline.totalMs;
    switch (_state) {
      case _Player.idle || _Player.done:
        if (known != null) return _clock(Duration(milliseconds: known));
        return _clock(_played);
      case _Player.paused || _Player.playing:
        final byLengths = _timeline.elapsed(_line, _state == _Player.playing ? _intoLine : Duration.zero);
        return _clock(byLengths ?? _played + (_state == _Player.playing ? _intoLine : Duration.zero));
    }
  }

  /// Who speaks now — the ring; on a pause the role of the line just played keeps it without the pulse.
  String? get _activeRole => switch (_state) {
    _Player.playing when _line < _lines.length => _lines[_line].role,
    _Player.paused when _line > 0 => _lines[_line - 1].role,
    _ => null,
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final scene = env.scene;
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final photo = scene?.image;
    final role = env.role.isEmpty ? '' : env.role[0].toLowerCase() + env.role.substring(1);
    final active = _activeRole;
    final pulsing = _state == _Player.playing;
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskListenTalk),
      body: SessionSheet(
        padding: EdgeInsets.zero,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 420),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _Role(
                      key: const ValueKey('player-role-partner'),
                      label: role,
                      active: active == 'partner',
                      pulsing: pulsing,
                      child: SceneCircle(
                        image: photo == null ? null : CachedNetworkImage(photo.urlFor(64, dpr)),
                        tone: AppColors.wireTone(photo?.tone),
                        size: 64,
                      ),
                    ),
                    const SizedBox(width: 24),
                    _Role(
                      key: const ValueKey('player-role-learner'),
                      label: l.planSessionYou,
                      active: active == 'learner',
                      pulsing: pulsing,
                      child: Container(
                        width: 64,
                        height: 64,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.paper,
                          border: Border.all(color: AppColors.markerOutline, width: 1.5),
                        ),
                        child: const Icon(LucideIcons.user, size: 24, color: AppColors.ink),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 120),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _PlayerBar(
                      progress: _progress,
                      marks: _timeline.marks,
                      played: (m) => _state == _Player.done || m.lastLine < _line,
                      current: (m) => _state == _Player.paused && m.lastLine == _line - 1,
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        Text(_time, key: const ValueKey('player-time'), style: AppTextSession.meta.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
                        const Spacer(),
                        Text(l.planSessionExchangesCount(_timeline.exchanges), style: AppTextSession.meta),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 120),
              Padding(
                padding: const EdgeInsets.all(20),
                child: SizedBox(
                  height: 24,
                  child: switch (_state) {
                    _Player.idle || _Player.playing => Row(
                      children: [
                        ValueListenableBuilder<Object?>(
                          valueListenable: env.voice.playing,
                          builder: (_, playing, _) => SessionWave(heights: SessionWave.five, width: 80, playing: _state == _Player.playing && playing != null),
                        ),
                        const SizedBox(width: 12),
                        Text(l.planSessionPlaying, style: AppTextSession.meta),
                      ],
                    ),
                    _Player.paused => Text(l.planSessionPlayerPaused(_timeline.exchangeOrdinal(_line - 1)), key: const ValueKey('player-status'), style: AppTextSession.meta),
                    _Player.done => Text(l.planSessionPlayerDone, key: const ValueKey('player-status'), style: AppTextSession.meta),
                  },
                ),
              ),
            ],
          ),
        ),
      ),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          switch (_state) {
            _Player.idle || _Player.playing => _byParts
                ? const SizedBox.shrink()
                : Center(
                    child: SessionTextExit(
                      key: const ValueKey('player-by-parts'),
                      label: l.planSessionByPartsAction,
                      brass: true,
                      onTap: () => setState(() => _byParts = true),
                    ),
                  ),
            _Player.paused || _Player.done => Center(
              child: SessionTextExit(key: const ValueKey('player-again'), label: l.planSessionReplay, brass: true, onTap: () => unawaited(_again())),
            ),
          },
          const SizedBox(height: 14),
          switch (_state) {
            _Player.idle || _Player.playing => SessionDockButton(label: l.planSessionReplay, onTap: () => unawaited(_again())),
            _Player.paused => SessionDockButton(label: l.planSessionContinue, onTap: () => unawaited(_play(from: _line))),
            _Player.done => SessionDockButton(
              label: l.planSessionNext,
              busy: env.advancing,
              onTap: () {
                env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
                unawaited(env.next());
              },
            ),
          },
        ],
      ),
    );
  }
}

/// A role of the player: the circle 64 and its name; the one speaking wears a brass ring 3 at 30 % with a pulse (1.6 s,
/// only while playing; under «Reduce Motion» the ring stands still).
class _Role extends StatefulWidget {
  const _Role({super.key, required this.label, required this.active, required this.pulsing, required this.child});

  final String label;
  final bool active;
  final bool pulsing;
  final Widget child;

  @override
  State<_Role> createState() => _RoleState();
}

class _RoleState extends State<_Role> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(vsync: this, duration: AppMotion.sessionRolePulse);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sync();
  }

  @override
  void didUpdateWidget(_Role old) {
    super.didUpdateWidget(old);
    _sync();
  }

  void _sync() {
    final run = widget.active && widget.pulsing && !(MediaQuery.maybeDisableAnimationsOf(context) ?? false);
    if (run && !_pulse.isAnimating) {
      unawaited(_pulse.repeat());
    } else if (!run && _pulse.isAnimating) {
      _pulse.stop();
      _pulse.value = 0;
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      AnimatedBuilder(
        animation: _pulse,
        builder: (_, child) {
          final t = Curves.easeOut.transform(_pulse.value);
          return Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              boxShadow: widget.active
                  ? [
                      const BoxShadow(color: AppColors.sessionBrassRing, spreadRadius: 3),
                      if (_pulse.isAnimating) BoxShadow(color: AppColors.sessionBrassRing.withValues(alpha: .30 * (1 - t)), spreadRadius: 3 + 6 * t),
                    ]
                  : null,
            ),
            child: child,
          );
        },
        child: widget.child,
      ),
      const SizedBox(height: 8),
      Text(widget.label, style: AppTextSession.meta),
    ],
  );
}

/// The player's bar: 4 high, the played part in sage; a mark 6 at the end of every exchange — played sage, ahead an
/// outline on the ground; the mark of the pause — brass 8.
class _PlayerBar extends StatelessWidget {
  const _PlayerBar({required this.progress, required this.marks, required this.played, required this.current});

  final double progress;
  final List<ListenMark> marks;
  final bool Function(ListenMark mark) played;
  final bool Function(ListenMark mark) current;

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const ValueKey('player-bar'),
    height: 12,
    child: LayoutBuilder(
      builder: (_, c) {
        final width = c.maxWidth;
        return Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.centerLeft,
          children: [
            Container(height: 4, decoration: BoxDecoration(color: AppColors.markerOutline, borderRadius: BorderRadius.circular(2))),
            Container(
              width: width * progress.clamp(0.0, 1.0),
              height: 4,
              decoration: BoxDecoration(color: AppColors.verdictKnown, borderRadius: BorderRadius.circular(2)),
            ),
            for (final (i, m) in marks.indexed)
              Positioned(
                left: width * m.at - (current(m) ? 4 : 3),
                top: current(m) ? 2 : 3,
                child: Container(
                  key: ValueKey('player-mark-$i'),
                  width: current(m) ? 8 : 6,
                  height: current(m) ? 8 : 6,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: current(m)
                        ? AppColors.brassInk
                        : played(m)
                        ? AppColors.verdictKnown
                        : AppColors.ground,
                    border: current(m) || played(m) ? null : Border.all(color: AppColors.markerOutline, width: 1.5),
                  ),
                ),
              ),
          ],
        );
      },
    ),
  );
}

// ── 34-2 ──────────────────────────────────────────────────────────────────────────────────────────

/// A QUESTION ABOUT THE VISIT (34-2, template 30-9 with a text top, no sound): the question in the native language,
/// «from memory · no sound», four options in the native language. Wrong — final: `failed`, no copy (the server deals
/// none in this stage), «Next».
class ListenQuestionCard extends StatefulWidget {
  const ListenQuestionCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final ListenQuestionPayload payload;

  @override
  State<ListenQuestionCard> createState() => _ListenQuestionCardState();
}

class _ListenQuestionCardState extends State<ListenQuestionCard> with ChoiceCardState<ListenQuestionCard> {
  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return CardLayout(
      bodyGap: 16,
      taskInBody: true,
      task: SessionTask(l.planSessionTaskWhatUnderstood),
      body: SessionQuestionSheet(
        eyebrow: l.planSessionBrowQuestion,
        text: Text(widget.payload.questionNative, style: AppTextSession.question),
        translation: l.planSessionFromMemory,
        translationStyle: AppTextSession.meta,
      ),
      bottom: optionsDock(context),
    );
  }
}

// ── 34-3 ──────────────────────────────────────────────────────────────────────────────────────────

/// THE REVIEW (34-3): the whole visit as bubbles with their translations — the partner on the left, the learner on the
/// right, «listen» 28 at every line; where a question's answer lives (`answers[].line_ref` / `span`; no span — the
/// whole line) is marked in sage when the question was answered right and in brass where it was missed. «Next» →
/// `passed`.
class ListenReviewCard extends StatelessWidget {
  const ListenReviewCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final ListenReviewPayload payload;

  /// Every question's result by its `ref` — read off the stage's answered question cards.
  Map<String, SessionResult?> get _results => {
    for (final c in env.stageCards)
      if (c.payload case ListenQuestionPayload(:final questionRef)) questionRef: c.result,
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final results = _results;
    final lines = payload.lines;
    return CardLayout(
      bodyGap: 16,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskWhereHeard),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final (i, line) in lines.indexed) ...[
            if (i > 0) SizedBox(height: lines[i - 1].exchangeStep == line.exchangeStep ? 8 : 16),
            _line(line, results),
          ],
        ],
      ),
      bottom: SessionDockButton(
        label: l.planSessionNext,
        busy: env.advancing,
        onTap: () {
          env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
          unawaited(env.next());
        },
      ),
    );
  }

  Widget _line(CardVisitLine line, Map<String, SessionResult?> results) {
    final own = line.role == 'learner';
    final marks = <TextMark>[
      for (final a in payload.answers)
        if (a.lineRef == line.ref)
          (
            start: a.span?.start ?? 0,
            end: a.span?.end ?? line.textTarget.length,
            look: results[a.questionRef] == SessionResult.passed ? MarkLook.sage : MarkLook.brass,
          ),
    ];
    final bubble = SessionBubble(
      key: ValueKey('review-${line.ref}'),
      own: own,
      translation: line.textNative,
      child: SessionMarkedText(text: line.textTarget, marks: marks, style: SessionBubble.lineStyle(own: own), onInk: own),
    );
    final listen = CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: 'review-${line.ref}', size: 28, brass: true);
    return own ? SessionOwnRow(bubble: bubble, listen: listen) : SessionPartnerRow(bubble: bubble, listen: listen);
  }
}

// ── 34-5 ──────────────────────────────────────────────────────────────────────────────────────────

/// THE PAUSE-PREDICTION (34-5): the learner's own line sounds and stands in its bubble; the partner's bubble is empty
/// and holds the wave-pause; three options in the native language. A choice — the partner's line opens in place and
/// sounds; correct / wrong as in 30-9 (no copy); a correct answer leaves once the line has sounded.
class ListenPredictCard extends StatefulWidget {
  const ListenPredictCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final ListenPredictPayload payload;

  @override
  State<ListenPredictCard> createState() => _ListenPredictCardState();
}

class _ListenPredictCardState extends State<ListenPredictCard> with ChoiceCardState<ListenPredictCard> {
  static const _ownKey = 'predict-own';
  static const _partnerKey = 'predict-partner';

  Timer? _autoplay;

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  bool get autoAdvanceOnCorrect => false;

  @override
  void initState() {
    super.initState();
    final own = widget.payload.ownLine;
    _autoplay = autoplayOnce(this, env, own.audio, own.textTarget, _ownKey);
  }

  @override
  void dispose() {
    _autoplay?.cancel();
    super.dispose();
  }

  @override
  void onChosen({required bool correct}) {
    _autoplay?.cancel();
    unawaited(_reveal(correct: correct));
  }

  Future<void> _reveal({required bool correct}) async {
    final line = widget.payload.partnerLine;
    await env.voice.stop();
    if (!mounted) return;
    final started = DateTime.now();
    await env.voice.play(line.audio, fallback: line.textTarget, key: _partnerKey);
    if (!mounted || !correct) return;
    final left = AppMotion.sessionAutoAdvance - DateTime.now().difference(started);
    if (left > Duration.zero) await Future<void>.delayed(left);
    if (mounted) unawaited(env.next());
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final partner = p.partnerLine;
    return CardLayout(
      feed: true,
      bodyGap: 16,
      task: SessionTask(l.planSessionTaskWhatNext),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionOwnRow(bubble: SessionBubble(own: true, text: p.ownLine.textTarget, translation: p.ownLine.textNative)),
          const SizedBox(height: 8),
          SessionPartnerRow(
            bubble: ValueListenableBuilder<Object?>(
              valueListenable: env.voice.playing,
              builder: (_, playing, _) => SessionBubble(
                own: false,
                translation: answered ? partner.textNative : null,
                child: AnimatedSwitcher(
                  duration: AppMotion.sessionTextReveal,
                  switchInCurve: AppMotion.sessionEaseOut,
                  child: answered
                      ? Text(partner.textTarget, key: const ValueKey('predict-text'), style: SessionBubble.lineStyle(own: false))
                      : SessionWave(key: const ValueKey('predict-wave'), heights: SessionWave.five, width: 80, playing: playing == _ownKey),
                ),
              ),
            ),
            listen: answered
                ? CardListen(env: env, audio: partner.audio, fallback: partner.textTarget, playKey: _partnerKey, size: 28, brass: true)
                : CardListen(env: env, audio: p.ownLine.audio, fallback: p.ownLine.textTarget, playKey: _ownKey, size: 28, brass: true),
          ),
        ],
      ),
      bottom: optionsDock(context),
    );
  }
}

// ── 34-6 ──────────────────────────────────────────────────────────────────────────────────────────

/// FAST / SLOW (34-6), one sheet in two states: the partner's line at `rates[0]` (0.75×) with its text, then at
/// `rates[1]` (1.0×) without it. «slowly» 44 in the sheet goes back to the slow tempo with the text, «Once more» plays
/// both again. No pass: «Got it» → `passed`.
class ListenPaceCard extends StatefulWidget {
  const ListenPaceCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final ListenPacePayload payload;

  @override
  State<ListenPaceCard> createState() => _ListenPaceCardState();
}

class _ListenPaceCardState extends State<ListenPaceCard> {
  static const _key = 'pace-line';

  /// The sheet shows the slow tempo with the text; false — the normal tempo without it.
  bool _slow = true;
  int _run = 0;
  Timer? _autostart;

  CardEnv get env => widget.env;

  double get _slowRate => widget.payload.rates.isNotEmpty ? widget.payload.rates.first : 0.75;
  double get _normalRate => widget.payload.rates.length > 1 ? widget.payload.rates[1] : 1.0;

  @override
  void initState() {
    super.initState();
    _autostart = Timer(kAutoplayDelay, () {
      if (mounted) unawaited(_both());
    });
  }

  @override
  void dispose() {
    _run++;
    _autostart?.cancel();
    super.dispose();
  }

  /// Slow with the text, then normal without it.
  Future<void> _both() async {
    final run = ++_run;
    await env.voice.stop();
    if (!mounted || run != _run) return;
    setState(() => _slow = true);
    await _say(_slowRate);
    if (!mounted || run != _run) return;
    setState(() => _slow = false);
    await _say(_normalRate);
  }

  /// «slowly» — the slow tempo with the text, once.
  Future<void> _slowOnce() async {
    final run = ++_run;
    await env.voice.stop();
    if (!mounted || run != _run) return;
    setState(() => _slow = true);
    await _say(_slowRate);
  }

  Future<void> _say(double rate) {
    final line = widget.payload.partnerLine;
    return env.voice.play(line.audio, fallback: line.textTarget, rate: rate, slowFallback: rate < 1, key: _key);
  }

  String _rateLabel(double rate) => rate.toStringAsFixed(2).replaceFirst(RegExp(r'0$'), '');

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final line = widget.payload.partnerLine;
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskNormalPace),
      body: ValueListenableBuilder<Object?>(
        valueListenable: env.voice.playing,
        builder: (_, playing, _) => SessionLineSheet(
          plateHeight: 288,
          revealed: _slow,
          plate: _slow
              ? Text(line.textTarget, key: const ValueKey('pace-text'), style: AppTextSession.question.copyWith(letterSpacing: -0.26))
              : SessionPlateWave(key: const ValueKey('pace-wave'), playing: playing == _key),
          listen: SessionListenButton(
            label: l.planWindowListen,
            playing: playing == _key,
            onTap: () => unawaited(_say(_slow ? _slowRate : _normalRate)),
          ),
          eyebrow: _slow ? l.planSessionBrowSlowRate(_rateLabel(_slowRate)) : l.planSessionBrowNormalPace,
          meta: _slow ? l.planSessionTextOpen : l.planSessionTextClosed,
          below: Padding(
            padding: const EdgeInsets.only(top: 10),
            child: Align(
              alignment: Alignment.centerLeft,
              child: SessionBrassButton(key: const ValueKey('pace-slowly'), label: l.planSessionSlowly, onTap: () => unawaited(_slowOnce())),
            ),
          ),
        ),
      ),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(child: SessionTextExit(key: const ValueKey('pace-again'), label: l.planSessionReplay, brass: true, onTap: () => unawaited(_both()))),
          const SizedBox(height: 14),
          SessionDockButton(
            label: l.planSessionUnderstoodAction,
            busy: env.advancing,
            onTap: () {
              env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
              unawaited(env.next());
            },
          ),
        ],
      ),
    );
  }
}

// ── 34-7 ──────────────────────────────────────────────────────────────────────────────────────────

/// CATCH THE NUMBER (34-7): the line with a number sounds, its text closed (a wave and «listen» 44 on the plate); the
/// question «Which number did you hear?» is the client's — the contract sends none; three options in the native
/// language. After the answer the
/// text opens in the plate with the number (`span`) marked — sage when caught, brass when missed; no copy.
class ListenNumberCard extends StatefulWidget {
  const ListenNumberCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final ListenNumberPayload payload;

  @override
  State<ListenNumberCard> createState() => _ListenNumberCardState();
}

class _ListenNumberCardState extends State<ListenNumberCard> with ChoiceCardState<ListenNumberCard> {
  static const _key = 'number-line';

  Timer? _autoplay;

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  void initState() {
    super.initState();
    final line = widget.payload.line;
    _autoplay = autoplayOnce(this, env, line.audio, line.textTarget, _key);
  }

  @override
  void dispose() {
    _autoplay?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final line = p.line;
    final span = p.span;
    return CardLayout(
      bodyGap: 12,
      task: SessionTask(l.planSessionTaskCatchNumber),
      body: ValueListenableBuilder<Object?>(
        valueListenable: env.voice.playing,
        builder: (_, playing, _) => SessionSheet(
          padding: EdgeInsets.zero,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ColoredBox(
                color: AppColors.ground,
                child: Stack(
                  children: [
                    Container(
                      constraints: BoxConstraints(minHeight: answeredWrong ? 160 : 208),
                      padding: const EdgeInsets.fromLTRB(20, 20, 20, 68),
                      alignment: answered ? Alignment.centerLeft : Alignment.center,
                      child: AnimatedSwitcher(
                        duration: AppMotion.sessionTextReveal,
                        child: answered
                            ? SessionMarkedText(
                                key: const ValueKey('number-text'),
                                text: line.textTarget,
                                style: AppTextSession.question.copyWith(letterSpacing: -0.26),
                                marks: [
                                  if (span != null) (start: span.start, end: span.end, look: answeredCorrectly ? MarkLook.sage : MarkLook.brass),
                                ],
                              )
                            : SessionPlateWave(key: const ValueKey('number-wave'), playing: playing == _key),
                      ),
                    ),
                    Positioned(
                      right: 16,
                      bottom: 16,
                      child: SessionListenButton(
                        label: l.planWindowListen,
                        playing: playing == _key,
                        onTap: () => unawaited(env.voice.play(line.audio, fallback: line.textTarget, key: _key)),
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SessionEyebrow(l.planSessionBrowQuestion),
                    const SizedBox(height: 6),
                    ConstrainedBox(
                      constraints: const BoxConstraints(minHeight: 68),
                      child: Align(alignment: Alignment.centerLeft, child: Text(l.planSessionWhichNumber, style: AppTextSession.question)),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      bottom: optionsDock(context),
    );
  }
}
