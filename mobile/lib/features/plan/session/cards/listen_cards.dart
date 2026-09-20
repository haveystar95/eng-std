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

/// THE VISIT PLAYER (34-1): the two roles as ONE PAIR of circles, overlapping (the scene's photo — the partner, the
/// learner's circle behind it) under a single caption «the doctor and you»; a brass ring with a pulse on the one that
/// speaks; the bar with the exchange marks (`exchange_step`) and the time (`total_ms`, or the sum of the lines'
/// `duration_ms`); the files play one after another as one stream, 300 ms apart ([AppMotion.sessionVisitLineGap]).
///
/// The main action follows the state: playing — «Pause», paused — «Continue», listened to the end — «Next» →
/// `passed`. «In parts» (secondary, brass) pauses after every exchange and stands while the visit goes; «Once more»
/// is offered at the end only. A pause takes the line that was sounding out of the air, and «Continue» says it from
/// its beginning: a line is the smallest piece the visit is made of.
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

  /// The pause came by hand, in the middle of [_line] — «Continue» says that line again. False — the «in parts»
  /// pause, which stands between two exchanges.
  bool _midLine = false;

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
    setState(() {
      _state = _Player.playing;
      _midLine = false;
    });
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

  /// «Pause»: the loop is cut and the line that was sounding stops; [_line] stays as the line «Continue» starts from.
  Future<void> _pauseNow() async {
    _run++;
    _autostart?.cancel();
    _frame?.cancel();
    await env.voice.stop();
    if (!mounted) return;
    setState(() {
      _midLine = true;
      _lineStarted = null;
      _state = _Player.paused;
    });
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

  /// Who speaks now — the ring; on a pause the role of the line the player stands on keeps it without the pulse: the
  /// one cut in the middle (by hand) or the one just played («in parts»).
  String? get _activeRole => switch (_state) {
    _Player.playing when _line < _lines.length => _lines[_line].role,
    _Player.paused when _midLine && _line < _lines.length => _lines[_line].role,
    _Player.paused when !_midLine && _line > 0 => _lines[_line - 1].role,
    _ => null,
  };

  /// The exchange the pause stands at — the one being said (by hand) or the one just finished («in parts»).
  int get _pausedAt => _timeline.exchangeOrdinal(_midLine ? _line : _line - 1);

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
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    SizedBox(
                      // The pair: a circle 64 and the second one 12 into it — 116 wide, or the Stack clips the one
                      // that overlaps.
                      width: 116,
                      height: 68,
                      child: Stack(
                        clipBehavior: Clip.none,
                        alignment: Alignment.centerLeft,
                        children: [
                          // The learner stands behind the partner, overlapping by 12 — one pair, not two portraits.
                          Positioned(
                            left: 52,
                            child: _Face(
                              key: const ValueKey('player-role-learner'),
                              active: active == 'learner',
                              pulsing: pulsing,
                              child: const DecoratedBox(
                                decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.photoPlaceholder),
                                child: SizedBox(
                                  width: 60,
                                  height: 60,
                                  child: Center(child: Icon(LucideIcons.user, size: 24, color: AppColors.ink)),
                                ),
                              ),
                            ),
                          ),
                          _Face(
                            key: const ValueKey('player-role-partner'),
                            active: active == 'partner',
                            pulsing: pulsing,
                            child: SceneCircle(
                              image: photo == null ? null : CachedNetworkImage(photo.urlFor(60, dpr)),
                              tone: AppColors.wireTone(photo?.tone),
                              size: 60,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      role.isEmpty ? l.planSessionYou : l.planSessionPlayerRoles(role),
                      key: const ValueKey('player-roles'),
                      style: AppTextSession.meta,
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
                    _Player.paused => Text(l.planSessionPlayerPaused(_pausedAt), key: const ValueKey('player-status'), style: AppTextSession.meta),
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
            // «In parts» stands while the visit goes and on a pause — the canvas draws it on the «in parts» pause
            // itself; asking for it again changes nothing.
            _Player.idle || _Player.playing || _Player.paused => Center(
              child: SessionTextExit(
                key: const ValueKey('player-by-parts'),
                label: l.planSessionByPartsAction,
                brass: true,
                onTap: () => setState(() => _byParts = true),
              ),
            ),
            _Player.done => Center(
              child: SessionTextExit(key: const ValueKey('player-again'), label: l.planSessionReplay, brass: true, onTap: () => unawaited(_again())),
            ),
          },
          const SizedBox(height: 14),
          switch (_state) {
            _Player.idle || _Player.playing => SessionDockButton(label: l.planSessionPause, onTap: () => unawaited(_pauseNow())),
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

/// A face of the pair: the circle 60 in a paper rim 2 (so the two read as separate where they overlap); the one
/// speaking wears a brass ring 3 at 30 % with a pulse (1.6 s, only while playing; under «Reduce Motion» the ring
/// stands still).
class _Face extends StatefulWidget {
  const _Face({super.key, required this.active, required this.pulsing, required this.child});

  final bool active;
  final bool pulsing;
  final Widget child;

  @override
  State<_Face> createState() => _FaceState();
}

class _FaceState extends State<_Face> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(vsync: this, duration: AppMotion.sessionRolePulse);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sync();
  }

  @override
  void didUpdateWidget(_Face old) {
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
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _pulse,
    builder: (_, child) {
      final t = Curves.easeOut.transform(_pulse.value);
      return Container(
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.paper,
          boxShadow: widget.active
              ? [
                  const BoxShadow(color: AppColors.sessionBrassRing, spreadRadius: 3),
                  if (_pulse.isAnimating) BoxShadow(color: AppColors.sessionBrassRing.withValues(alpha: .30 * (1 - t)), spreadRadius: 3 + 6 * t),
                ]
              : null,
        ),
        // The paper rim 2 around the circle 60 — the pair overlaps and must not merge into one blot.
        padding: const EdgeInsets.all(2),
        child: child,
      );
    },
    child: widget.child,
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

/// WHAT WILL THE ANSWER BE? (кадр 34-5, наряд CLIENT-CONV-1a on the contract of BACK-TAILS-1 §1.2).
///
/// The learner's OWN question stands at the top as their own dark bubble with a wave and «прослушать»
/// — it is their line, so it is drawn the way their lines are drawn everywhere else. Under it, three
/// sound plates: three of the partner's replies, with no text at all. A tap plays one (and a tap
/// while it plays stops it); a second tap on one that has been HEARD to the end marks it. «Это ответ»
/// comes alive only then — the card asks the learner to listen, not to guess by length.
///
/// Right — the card leaves by itself after 600 ms. Wrong — every plate opens its line and its
/// translation, the learner's own question opens above them, the right plate takes the sage wash and
/// the check, and the one chosen keeps its ink outline; «Дальше» appears only after a miss.
class ListenPredictCard extends StatefulWidget {
  const ListenPredictCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final ListenPredictPayload payload;

  @override
  State<ListenPredictCard> createState() => _ListenPredictCardState();
}

class _ListenPredictCardState extends State<ListenPredictCard> with ChoiceCardState<ListenPredictCard> {
  static const _ownKey = 'predict-own';

  Timer? _autoplay;

  /// The options played to the end — their `id`.
  final Set<String> _heard = {};

  /// The option marked as the answer; null — nothing chosen yet.
  String? _marked;

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

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

  /// A tap on a plate: it plays — or stops, if it is the one playing. A plate already heard, tapped
  /// again, becomes the answer to send.
  Future<void> _tap(CardLineOption option, {required bool playing}) async {
    _autoplay?.cancel();
    if (playing) {
      await env.voice.stop();
      return;
    }
    if (!answered && _heard.contains(option.id) && _marked != option.id) {
      setState(() => _marked = option.id);
      AppHaptics.light();
      return;
    }
    await env.voice.stop();
    if (!mounted) return;
    await env.voice.play(option.audio, fallback: option.textTarget, key: 'predict-${option.id}');
    if (!mounted) return;
    setState(() => _heard.add(option.id));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final own = widget.payload.ownLine;
    return CardLayout(
      feed: true,
      bodyGap: 16,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskListenChoose, companion: l.planSessionListenWholeOne),
      body: ValueListenableBuilder<Object?>(
        valueListenable: env.voice.playing,
        builder: (_, playing, _) => SessionOwnRow(
          listen: SessionListenButton(
            size: 28,
            brass: true,
            label: l.planWindowListen,
            playing: playing == _ownKey,
            onTap: () => unawaited(env.voice.play(own.audio, fallback: own.textTarget, key: _ownKey)),
          ),
          bubble: SessionBubble(
            own: true,
            // The learner's own question opens with the rest of the texts — on a pass too, in the
            // 600 ms the card has left (кадр 34-5, «тексты открываются вместе со своей репликой»).
            text: answered ? own.textTarget : null,
            translation: answered ? own.textNative : null,
            child: answered
                ? null
                : SessionWave(
                    key: const ValueKey('predict-own-wave'),
                    heights: SessionWave.five,
                    width: 80,
                    playing: playing == _ownKey,
                    color: AppColors.paper,
                  ),
          ),
        ),
      ),
      bottom: _dock(l),
    );
  }

  Widget _dock(AppLocalizations l) => ValueListenableBuilder<Object?>(
    valueListenable: env.voice.playing,
    builder: (_, playing, _) => Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final o in widget.payload.options) ...[
          if (o != widget.payload.options.first) const SizedBox(height: 8),
          SessionSoundPlate(
            key: ValueKey('option-${o.id}'),
            audioRef: o.audio?.ref ?? o.id,
            durationMs: o.audio?.durationMs,
            heard: _heard.contains(o.id),
            playing: playing == 'predict-${o.id}',
            marked: _marked == o.id,
            look: answered ? lookOf(o.id) : null,
            textTarget: o.textTarget,
            textNative: o.textNative,
            onTap: () => unawaited(_tap(o, playing: playing == 'predict-${o.id}')),
          ),
        ],
        const SizedBox(height: 16),
        if (answeredWrong)
          SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next()))
        else if (!answered)
          SessionDockButton(
            key: const ValueKey('predict-answer'),
            label: l.planSessionThisIsAnswer,
            // Only a plate that has been HEARD can be marked, so an active button is always one the
            // learner has listened to.
            enabled: _marked != null,
            onTap: _marked == null ? null : () => choose(_marked!),
          ),
      ],
    ),
  );
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
