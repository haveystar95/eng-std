import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../splash_choreography.dart' show DotSpring;
import 'intro_screen.dart' show IntroStartButton;

/// Geometry shared by the five sheets (41-2): the picture is the top 560 of a 390 × 844 frame whose status bar is 52 —
/// on a taller notch everything in the picture moves down by the difference.
abstract final class IntroPages {
  static const pictureHeight = 560.0;
  static const canvasStatusBar = 52.0;

  /// c and d stand on a photo: the status bar is light and «Пропустить» sits on a paper plate.
  static bool photoTop(int page) => page == 2 || page == 3;

  /// Where the picture melts into the paper, per sheet (the canvas scrims: clear until [0], .78 at [1], paper at [2]).
  static const scrims = <List<double>>[
    [.46, .59, .65],
    [.28, .36, .40],
    [.31, .41, .45],
    [.21, .27, .30],
    [.39, .50, .55],
  ];

  /// The sample lines are said in their own languages — they are the product's material, not its interface, and
  /// read the same in any interface language (41-2a, 41-2b, 41-2d).
  static const lineEn = 'I’d like to make an appointment.';
  static const lines = [lineEn, 'Ich möchte einen Termin vereinbaren.', 'Aș vrea să fac o programare.', 'Chciałbym umówić wizytę.'];
  static const partnerLine = 'Good morning. What brings you in today?';
  static const ownLine = 'Sorry, could you say that again?';
}

/// ONE SHEET: the picture (with its own choreography), the scrim into the paper, the text at the foot.
///
/// [swipe] — how far the page is from the centre (−1…1): its picture lags at [StartMotion.sheetParallax] of the swipe.
/// [reveal] — the entrance from the sign-in: the picture rises out of the paper from the bottom, the text lifts.
/// [alive] — the page has settled; its picture plays (again for every new [generation]).
class IntroPage extends StatelessWidget {
  const IntroPage({
    super.key,
    required this.index,
    required this.swipe,
    required this.reveal,
    required this.alive,
    required this.generation,
    required this.still,
    required this.onStart,
  });

  final int index;
  final double swipe;
  final double reveal;
  final bool alive;
  final int generation;
  final bool still;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final width = MediaQuery.sizeOf(context).width;
    final inset = math.max(0.0, MediaQuery.paddingOf(context).top - IntroPages.canvasStatusBar);
    final scrim = IntroPages.scrims[index];
    final life = _Life(alive: alive, generation: generation, still: still);

    final picture = switch (index) {
      0 => _SceneStack(life: life, inset: inset),
      1 => _FlagWall(life: life, inset: inset),
      2 => _Photo(asset: 'assets/intro/doctor-office.jpg', inset: inset),
      3 => _Photo(asset: 'assets/intro/cafe.jpg', inset: inset),
      _ => _Covers(life: life, inset: inset),
    };

    final text = switch (index) {
      0 => _Text(
        above: _SaidLine(line: IntroPages.lineEn, native: l.introALineNative),
        title: l.introATitle,
        thought: l.introAThought,
        page: 0,
      ),
      1 => _Text(above: _TypedLines(life: life), title: l.introBTitle, thought: l.introBThought, page: 1),
      2 => _Text(above: _Route(life: life), title: l.introCTitle, thought: l.introCThought, page: 2),
      3 => _Text(above: _Talk(life: life), title: l.introDTitle, thought: l.introDThought, page: 3),
      _ => _Text(
        above: Text(l.introECaption, style: AppTextStart.sheetLineNative),
        title: l.introETitle,
        thought: l.introEThought,
        page: 4,
        footer: _StartButtonIn(life: life, onTap: onStart),
      ),
    };

    return Stack(
      fit: StackFit.expand,
      children: [
        // The picture lags behind the swipe (parallax 0.6) and, on the way in from the sign-in, rises out of the paper.
        Positioned(
          left: 0,
          right: 0,
          top: 0,
          height: IntroPages.pictureHeight + inset,
          child: Transform.translate(
            offset: Offset(swipe * width * (1 - StartMotion.sheetParallax), 0),
            child: _RiseFromPaper(reveal: reveal, child: picture),
          ),
        ),
        Positioned.fill(
          child: IgnorePointer(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: const [AppColors.groundClear, AppColors.groundClear, AppColors.introScrim, AppColors.ground, AppColors.ground],
                  stops: [0, scrim[0], scrim[1], scrim[2], 1],
                ),
              ),
            ),
          ),
        ),
        Positioned(
          left: 24,
          right: 24,
          bottom: math.max(24.0, MediaQuery.paddingOf(context).bottom),
          child: Opacity(
            opacity: reveal,
            child: Transform.translate(offset: Offset(0, (1 - reveal) * StartMotion.sheetTextRise), child: text),
          ),
        ),
      ],
    );
  }
}

/// «Фото листа a проявляется из бумаги снизу вверх» — a mask whose edge climbs from the bottom as [reveal] goes 0 → 1.
class _RiseFromPaper extends StatelessWidget {
  const _RiseFromPaper({required this.reveal, required this.child});

  final double reveal;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (reveal >= 1) return child;
    return ShaderMask(
      blendMode: BlendMode.dstIn,
      shaderCallback: (rect) {
        // The edge travels from below the picture to above it, soft over a fifth of its height.
        final edge = 1.2 - reveal * 1.4;
        return LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: const [AppColors.groundClear, AppColors.ground],
          stops: [edge.clamp(0.0, 1.0), (edge + .2).clamp(0.0, 1.0)],
        ).createShader(rect);
      },
      child: child,
    );
  }
}

/// When a sheet's picture plays: [alive] after its page settles, again for each new [generation]; [still] — the
/// final frame, nothing moving (snapshots, «Уменьшить движение»).
class _Life {
  const _Life({required this.alive, required this.generation, required this.still});

  final bool alive;
  final int generation;
  final bool still;
}

/// A picture's clock: runs from 0 when its page comes alive, holds when it leaves, stands at the end when still.
mixin _LifeClock<T extends StatefulWidget> on State<T>, TickerProviderStateMixin<T> {
  _Life get life;

  /// How long the entrance runs.
  Duration get entrance;

  late final AnimationController clock = AnimationController(vsync: this, duration: entrance);
  int _seenGeneration = -1;

  /// The picture stands finished.
  bool get finished => life.still || clock.isCompleted;

  void initLife() {
    if (life.still) {
      clock.value = 1;
    } else if (life.alive) {
      _start();
    }
  }

  void updateLife() {
    if (life.still) {
      clock.value = 1;
      return;
    }
    if (life.alive && life.generation != _seenGeneration) _start();
  }

  void _start() {
    _seenGeneration = life.generation;
    clock.forward(from: 0).whenCompleteOrCancel(onEntered);
  }

  /// The entrance has played — rest cycles start here.
  void onEntered() {}

  /// Elapsed time of the entrance, in ms.
  double get ms => clock.value * entrance.inMilliseconds;

  /// 0…1 of a stretch [start, start + length] of the entrance, eased.
  double span(num start, num length, {Curve curve = StartMotion.ease}) =>
      curve.transform(((ms - start) / length).clamp(0.0, 1.0));
}

// ── The text at the foot ────────────────────────────────────────────────────────────────────────────────────────

class _Text extends StatelessWidget {
  const _Text({required this.above, required this.title, required this.thought, required this.page, this.footer});

  final Widget above;
  final String title;
  final String thought;
  final int page;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        above,
        const SizedBox(height: 32),
        Text.rich(BrassDots.span(title, AppTextStart.sheetTitle), key: ValueKey('intro-title-$page')),
        const SizedBox(height: 14),
        Text(thought, style: AppTextStart.sheetThought),
        const SizedBox(height: 32),
        _Dots(active: page),
        if (footer != null) ...[const SizedBox(height: 16), footer!],
      ],
    );
  }
}

/// THE SENTENCE DOTS IN BRASS — «точка — отдельный span цветом латуни, как в словомарке» (41-2). A full stop that
/// ends a sentence (before a space, a line break or the end) is drawn in brass; the rest of the text in [style].
abstract final class BrassDots {
  static TextSpan span(String text, TextStyle style) {
    final spans = <InlineSpan>[];
    var from = 0;
    for (var i = 0; i < text.length; i++) {
      final end = i == text.length - 1 || text[i + 1] == ' ' || text[i + 1] == '\n';
      if (text[i] == '.' && end) {
        if (i > from) spans.add(TextSpan(text: text.substring(from, i)));
        spans.add(const TextSpan(text: '.', style: AppTextStart.brassDot));
        from = i + 1;
      }
    }
    if (from < text.length) spans.add(TextSpan(text: text.substring(from)));
    return TextSpan(style: style, children: spans);
  }
}

/// The five points: the current one a brass pill 16 × 6, the rest brass rings Ø 6, 6 apart.
class _Dots extends StatelessWidget {
  const _Dots({required this.active});

  final int active;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 6,
    child: Row(
      children: [
        for (var i = 0; i < 5; i++) ...[
          if (i > 0) const SizedBox(width: 6),
          Container(
            width: i == active ? 16 : 6,
            height: 6,
            decoration: i == active
                ? BoxDecoration(color: AppColors.brassInk, borderRadius: BorderRadius.circular(3))
                : BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppColors.brassInk)),
          ),
        ],
      ],
    ),
  );
}

/// A line of the target language with its dot in brass, and its translation under it (41-2a).
class _SaidLine extends StatelessWidget {
  const _SaidLine({required this.line, required this.native});

  final String line;
  final String native;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text.rich(BrassDots.span(line, AppTextStart.sheetLine)),
      if (native.isNotEmpty) ...[
        const SizedBox(height: 4),
        Text(native, style: AppTextStart.sheetLineNative),
      ],
    ],
  );
}

// ── a · the stack of scenes ───────────────────────────────────────────────────────────────────────────────────

/// Five scene cards 160 × 208 overlapping — 16 right and 28 down from one to the next, back to front: airport, bank,
/// work, rent, doctor. They ride in from the right 80 ms apart; at rest every 4 s the back card lifts to the top and the
/// cards breathe ±3 px, each on its own phase.
class _SceneStack extends StatefulWidget {
  const _SceneStack({required this.life, required this.inset});

  final _Life life;
  final double inset;

  @override
  State<_SceneStack> createState() => _SceneStackState();
}

class _SceneStackState extends State<_SceneStack> with TickerProviderStateMixin, _LifeClock {
  static const _assets = ['airport', 'bank', 'interview', 'rent', 'doctor'];
  static const _w = 160.0;
  static const _h = 208.0;

  @override
  _Life get life => widget.life;

  @override
  Duration get entrance => StartMotion.stackCardStep * 4 + StartMotion.stackCardIn;

  /// Which card stands in which slot, back (0) to front (4).
  final List<int> _order = [0, 1, 2, 3, 4];
  late final AnimationController _lift = AnimationController(vsync: this, duration: StartMotion.stackLift);
  late final AnimationController _breath = AnimationController(vsync: this, duration: StartMotion.stackBreath);
  Timer? _cycle;

  @override
  void initState() {
    super.initState();
    initLife();
  }

  @override
  void didUpdateWidget(_SceneStack old) {
    super.didUpdateWidget(old);
    if (!widget.life.alive) _stopRest();
    updateLife();
  }

  @override
  void onEntered() {
    if (!mounted || widget.life.still || !widget.life.alive) return;
    _breath.repeat();
    _cycle?.cancel();
    _cycle = Timer.periodic(StartMotion.stackCycle, (_) {
      if (!mounted) return;
      _lift.forward(from: 0).whenComplete(() {
        if (!mounted) return;
        setState(() => _order.add(_order.removeAt(0)));
        _lift.value = 0;
      });
    });
  }

  void _stopRest() {
    _cycle?.cancel();
    _breath.stop();
  }

  @override
  void dispose() {
    _cycle?.cancel();
    _lift.dispose();
    _breath.dispose();
    clock.dispose();
    super.dispose();
  }

  Offset _slot(double s) => Offset(24 + 16 * s, 114 + 28 * s + widget.inset);

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final captions = [l.introSceneAirport, l.introSceneBank, l.introSceneInterview, l.introSceneRent, l.introSceneDoctor];
    final width = MediaQuery.sizeOf(context).width;
    return AnimatedBuilder(
      animation: Listenable.merge([clock, _lift, _breath]),
      builder: (context, _) {
        final lift = Curves.easeInOut.transform(_lift.value);
        final cards = <(int, Widget)>[];
        for (var slot = 0; slot < 5; slot++) {
          final card = _order[slot];
          // The entrance: card by card from beyond the right edge, back to front.
          final enter = span(StartMotion.stackCardStep.inMilliseconds * card, StartMotion.stackCardIn.inMilliseconds);
          var position = _slot(slot.toDouble());
          var scale = 1.0;
          var z = slot;
          if (lift > 0) {
            if (slot == 0) {
              // The back card lifts out and lands on top: an arc 24 px up-right, 1.03 at its height.
              final arc = math.sin(math.pi * lift);
              position = Offset.lerp(_slot(0), _slot(4), lift)! + Offset(24 * arc, -24 * arc);
              scale = 1 + (StartMotion.stackLiftScale - 1) * arc;
              z = 10;
            } else {
              position = Offset.lerp(_slot(slot.toDouble()), _slot(slot - 1.0), lift)!;
            }
          }
          final breath = widget.life.still
              ? 0.0
              : StartMotion.stackBreathAmplitude * math.sin(2 * math.pi * (_breath.value + card / 5));
          position += Offset((1 - enter) * (width - position.dx + 20), breath);
          cards.add((
            z,
            Positioned(
              left: position.dx,
              top: position.dy,
              child: Transform.scale(scale: scale, child: _SceneCard(asset: _assets[card], caption: captions[card])),
            ),
          ));
        }
        cards.sort((a, b) => a.$1.compareTo(b.$1));
        return Stack(clipBehavior: Clip.hardEdge, children: [for (final c in cards) c.$2]);
      },
    );
  }
}

class _SceneCard extends StatelessWidget {
  const _SceneCard({required this.asset, required this.caption});

  final String asset;
  final String caption;

  @override
  Widget build(BuildContext context) => Container(
    width: _SceneStackState._w,
    height: _SceneStackState._h,
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(16),
      color: AppColors.photoSlot,
      boxShadow: const [BoxShadow(color: AppColors.introCardShadow, blurRadius: 32, offset: Offset(0, 12))],
    ),
    clipBehavior: Clip.antiAlias,
    child: Stack(
      fit: StackFit.expand,
      children: [
        Image.asset('assets/intro/scene-$asset.jpg', fit: BoxFit.cover, excludeFromSemantics: true),
        Positioned(
          left: 8,
          top: 4,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
            decoration: BoxDecoration(color: AppColors.introPlate, borderRadius: BorderRadius.circular(6)),
            child: Text(caption.toUpperCase(), style: AppTextStart.sceneCaps),
          ),
        ),
      ],
    ),
  );
}

// ── b · the wall of flags and the four lines ─────────────────────────────────────────────────────────────────

/// The canvas's wall, row by row (6 × 5, step 56, flags 22): the flags of the app's language reference — the canvas's
/// Dutch and Swedish flags, which the reference does not have, are its Turkish and Ukrainian ones.
const _wall = [
  ['en', 'pt', 'de', 'es', 'es', 'ro'],
  ['pl', 'de', 'tr', 'uk', 'en', 'pt'],
  ['de', 'es', 'ro', 'ro', 'pl', 'it'],
  ['tr', 'uk', 'en', 'pl', 'de', 'es'],
  ['fr', 'fr', 'pl', 'it', 'tr', 'uk'],
];

/// Lit with its line (the diagonal): English, German, Romanian, Polish — the order of [IntroPages.lines].
const _lit = [(0, 0), (1, 1), (2, 2), (3, 3)];

/// In colour from the typing on, without a ring: Spanish, Italian, French.
const _coloured = [(0, 4), (2, 5), (4, 1)];

/// When each line starts typing and how long it types (ms from the entrance's start).
List<(double, double)> _typing() {
  final out = <(double, double)>[];
  var at = StartMotion.flagsWave.inMilliseconds.toDouble();
  for (final line in IntroPages.lines) {
    final length = line.length * StartMotion.typePerChar.inMilliseconds.toDouble();
    out.add((at, length));
    at += length + StartMotion.lineGap.inMilliseconds;
  }
  return out;
}

class _FlagWall extends StatefulWidget {
  const _FlagWall({required this.life, required this.inset});

  final _Life life;
  final double inset;

  @override
  State<_FlagWall> createState() => _FlagWallState();
}

class _FlagWallState extends State<_FlagWall> with TickerProviderStateMixin, _LifeClock {
  @override
  _Life get life => widget.life;

  @override
  Duration get entrance {
    final last = _typing().last;
    return Duration(milliseconds: (last.$1 + last.$2).round() + StartMotion.flagLight.inMilliseconds);
  }

  late final AnimationController _drift = AnimationController(vsync: this, duration: StartMotion.flagDrift);

  @override
  void initState() {
    super.initState();
    initLife();
  }

  @override
  void didUpdateWidget(_FlagWall old) {
    super.didUpdateWidget(old);
    if (!widget.life.alive) _drift.stop();
    updateLife();
  }

  @override
  void onEntered() {
    if (mounted && !widget.life.still && widget.life.alive) _drift.repeat();
  }

  @override
  void dispose() {
    _drift.dispose();
    clock.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final typing = _typing();
    return AnimatedBuilder(
      animation: Listenable.merge([clock, _drift]),
      builder: (context, _) {
        final flags = <Widget>[];
        for (var r = 0; r < 5; r++) {
          for (var c = 0; c < 6; c++) {
            // The wave from the centre: a flag fades in 200 ms, late by its distance from the middle of the wall.
            final distance = math.sqrt(math.pow(c - 2.5, 2) + math.pow(r - 2, 2)) / math.sqrt(2.5 * 2.5 + 4);
            final waveAt = distance * (StartMotion.flagsWave - StartMotion.flagFade).inMilliseconds;
            final wave = span(waveAt, StartMotion.flagFade.inMilliseconds);

            final litIndex = _lit.indexOf((r, c));
            final coloured = _coloured.contains((r, c));
            var opacity = .25 * wave;
            var scale = 1.0;
            double ring = 0;
            var drift = Offset.zero;
            if (litIndex >= 0) {
              final light = span(typing[litIndex].$1, StartMotion.flagLight.inMilliseconds, curve: Curves.linear);
              opacity = wave * (.25 + .75 * light);
              scale = 1 + (StartMotion.flagLightScale - 1) * math.sin(math.pi * light);
              ring = light;
            } else if (coloured) {
              opacity = wave * (.25 + .75 * span(typing.first.$1, StartMotion.flagLight.inMilliseconds));
            } else if (!widget.life.still && _drift.isAnimating) {
              // At rest the unlit ones drift ±4 px on their own phases and breathe 25 → 35 → 25 %.
              final phase = 2 * math.pi * (_drift.value + (r * 6 + c) / 30);
              drift = Offset(math.cos(phase), math.sin(phase)) * StartMotion.flagDriftAmplitude;
              opacity = .25 + .10 * (0.5 - 0.5 * math.cos(2 * math.pi * _drift.value + r + c));
            }
            flags.add(Positioned(
              left: 24 + 56.0 * c - 2 + drift.dx,
              top: 84 + 56.0 * r + widget.inset - 2 + drift.dy,
              child: Transform.scale(
                scale: scale,
                child: SizedBox.square(
                  dimension: 26,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Opacity(opacity: opacity.clamp(0.0, 1.0), child: MiniFlag(languageCode: _wall[r][c])),
                      if (ring > 0) CustomPaint(size: const Size.square(26), painter: _RingPainter(ring)),
                    ],
                  ),
                ),
              ),
            ));
          }
        }
        return Stack(children: flags);
      },
    );
  }
}

/// The brass ring 2 px drawn around a lit flag over [StartMotion.flagLight].
class _RingPainter extends CustomPainter {
  const _RingPainter(this.progress);

  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = AppColors.brassInk
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    canvas.drawArc(Offset.zero & size, -math.pi / 2, 2 * math.pi * progress, false, paint);
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.progress != progress;
}

/// The four lines, typed one after another at 40 ms a character with a brass caret (41-2b).
class _TypedLines extends StatefulWidget {
  const _TypedLines({required this.life});

  final _Life life;

  @override
  State<_TypedLines> createState() => _TypedLinesState();
}

class _TypedLinesState extends State<_TypedLines> with TickerProviderStateMixin, _LifeClock {
  @override
  _Life get life => widget.life;

  @override
  Duration get entrance {
    final last = _typing().last;
    return Duration(milliseconds: (last.$1 + last.$2).round() + StartMotion.flagLight.inMilliseconds);
  }

  late final AnimationController _caret = AnimationController(vsync: this, duration: AppMotion.sessionCaretPeriod)
    ..repeat();

  @override
  void initState() {
    super.initState();
    initLife();
  }

  @override
  void didUpdateWidget(_TypedLines old) {
    super.didUpdateWidget(old);
    updateLife();
  }

  @override
  void dispose() {
    _caret.dispose();
    clock.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final typing = _typing();
    return ConstrainedBox(
      // «min-height:136px» — the block does not grow as the lines arrive, so the headline below never moves.
      constraints: const BoxConstraints(minHeight: 136),
      child: AnimatedBuilder(
        animation: Listenable.merge([clock, _caret]),
        builder: (context, _) {
          final children = <Widget>[];
          for (var i = 0; i < IntroPages.lines.length; i++) {
            final line = IntroPages.lines[i];
            final (start, length) = typing[i];
            final shown = widget.life.still ? line.length : (((ms - start) / length).clamp(0.0, 1.0) * line.length).floor();
            if (shown == 0) continue;
            final typingNow = !widget.life.still && shown < line.length && ms >= start;
            final spanText = BrassDots.span(line.substring(0, shown), AppTextStart.sheetLine);
            children.add(Padding(
              padding: EdgeInsets.only(top: children.isEmpty ? 0 : 8),
              child: Text.rich(TextSpan(
                children: [
                  spanText,
                  if (typingNow && _caret.value < .5)
                    const WidgetSpan(
                      alignment: PlaceholderAlignment.baseline,
                      baseline: TextBaseline.alphabetic,
                      child: Padding(
                        padding: EdgeInsets.only(left: 2),
                        child: SizedBox(width: 24, height: 2, child: ColoredBox(color: AppColors.brassInk)),
                      ),
                    ),
                ],
              )),
            ));
          }
          return Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: children);
        },
      ),
    );
  }
}

// ── c · the route ─────────────────────────────────────────────────────────────────────────────────────────────

class _Photo extends StatelessWidget {
  const _Photo({required this.asset, required this.inset});

  final String asset;
  final double inset;

  @override
  Widget build(BuildContext context) =>
      Image.asset(asset, fit: BoxFit.cover, alignment: Alignment.topCenter, excludeFromSemantics: true);
}

/// The route of a plan, larger (41-2c): the brow, the line 2 px across the field with six days at 52 — day 1 passed
/// (sage with a check), day 2 «сегодня» (an ink ring), the rest ahead in brass — and the event's brass point Ø 20 at
/// the end with its date above and its name below; under it the day's six stages with the plan's own icons.
class _Route extends StatefulWidget {
  const _Route({required this.life});

  final _Life life;

  @override
  State<_Route> createState() => _RouteState();
}

class _RouteState extends State<_Route> with TickerProviderStateMixin, _LifeClock {
  @override
  _Life get life => widget.life;

  static const _draw = 600;
  static const _land = 450;
  static const _check = 150;
  static const _icons = 6;

  @override
  Duration get entrance => Duration(
    milliseconds: _draw +
        _land +
        _check +
        StartMotion.routeStageStep.inMilliseconds * (_icons - 1) +
        StartMotion.routeStageFade.inMilliseconds,
  );

  bool _ticked = false;

  @override
  void initState() {
    super.initState();
    initLife();
    clock.addListener(() {
      // The tick of the event point's landing — once per life.
      if (!_ticked && ms >= _draw + _land * DotSpring.firstArrival && !widget.life.still) {
        _ticked = true;
        AppHaptics.light();
      }
      if (ms == 0) _ticked = false;
    });
  }

  @override
  void didUpdateWidget(_Route old) {
    super.didUpdateWidget(old);
    updateLife();
  }

  @override
  void dispose() {
    clock.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final stages = [
      (PlanStageMarkKind.words, l.introStageWords),
      (PlanStageMarkKind.phrases, l.introStagePhrases),
      (PlanStageMarkKind.dialogue, l.introStageDialogue),
      (PlanStageMarkKind.listen, l.introStageListen),
      (PlanStageMarkKind.speak, l.introStageSpeak),
      (PlanStageMarkKind.talk, l.introStageTalk),
    ];
    return AnimatedBuilder(
      animation: clock,
      builder: (context, _) {
        final drawn = span(0, _draw, curve: Curves.linear);
        final land = DotSpring().transform(((ms - _draw) / _land).clamp(0.0, 1.0));
        final check = span(_draw + _land, _check);
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(l.introCBrow.toUpperCase(), style: AppTextStart.routeBrow),
            const SizedBox(height: 36),
            SizedBox(
              height: 2,
              child: LayoutBuilder(
                builder: (context, box) => _RouteLine(
                  width: box.maxWidth,
                  drawn: drawn,
                  land: land,
                  check: check,
                  today: l.introCToday,
                  date: l.introCDate.toUpperCase(),
                  event: l.introCEvent,
                ),
              ),
            ),
            const SizedBox(height: 56),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                for (var i = 0; i < stages.length; i++)
                  Opacity(
                    opacity: span(_draw + _land + _check + StartMotion.routeStageStep.inMilliseconds * i, StartMotion.routeStageFade.inMilliseconds),
                    child: SizedBox(
                      width: 52,
                      child: Column(
                        children: [
                          SizedBox.square(
                            dimension: 44,
                            child: Center(
                              child: PlanStageGlyph(kind: stages[i].$1, color: i < 2 ? AppColors.ink : AppColors.tertiary, size: 24),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            stages[i].$2,
                            maxLines: 1,
                            softWrap: false,
                            overflow: TextOverflow.visible,
                            style: AppTextStart.routeStage.copyWith(color: i < 2 ? AppColors.ink : AppColors.tertiary),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ],
        );
      },
    );
  }
}

/// The line itself, its nodes and the event point — laid out by the canvas numbers over [width].
class _RouteLine extends StatelessWidget {
  const _RouteLine({
    required this.width,
    required this.drawn,
    required this.land,
    required this.check,
    required this.today,
    required this.date,
    required this.event,
  });

  final double width;
  final double drawn;
  final double land;
  final double check;
  final String today;
  final String date;
  final String event;

  static const _step = 52.0;
  static const _node = 16.0;

  @override
  Widget build(BuildContext context) {
    final front = width * drawn;
    final nodes = <Widget>[];
    for (var i = 0; i < 6; i++) {
      final x = _step * i;
      // A node pops as the line reaches it (0 → 1 in 150 ms of the 600 ms draw).
      final pop = ((front - x) / (width * StartMotion.routeNodePop.inMilliseconds / StartMotion.routeDraw.inMilliseconds))
          .clamp(0.0, 1.0);
      final passed = i == 0;
      final todayNode = i == 1;
      nodes.add(Positioned(
        left: x - _node / 2,
        top: -7,
        child: Transform.scale(
          scale: StartMotion.ease.transform(pop),
          child: Container(
            width: _node,
            height: _node,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: passed ? AppColors.verdictKnown : AppColors.ground,
              border: passed ? null : Border.all(color: todayNode ? AppColors.ink : AppColors.brassInk, width: 2),
            ),
            child: passed && check > 0
                ? Transform.scale(scale: check, child: const Icon(LucideIcons.check, size: 10, color: AppColors.paper))
                : null,
          ),
        ),
      ));
      if (i < 5) {
        nodes.add(Positioned(
          left: x - 20,
          top: 14,
          width: 40,
          child: Opacity(
            opacity: pop,
            child: Text('${i + 1}', textAlign: TextAlign.center, style: AppTextStart.routeNumber),
          ),
        ));
      }
    }
    return Stack(
      clipBehavior: Clip.none,
      children: [
        // Passed in sage to day 2, ahead in brass to the event.
        Positioned(left: 0, top: 0, width: math.min(front, _step), height: 2, child: const ColoredBox(color: AppColors.verdictKnown)),
        if (front > _step)
          Positioned(left: _step, top: 0, width: front - _step, height: 2, child: const ColoredBox(color: AppColors.brassInk)),
        ...nodes,
        // «сегодня» under day 2 — after the check of day 1.
        Positioned(
          left: _step - 24,
          top: 28,
          width: 48,
          child: Opacity(opacity: check, child: Text(today, textAlign: TextAlign.center, style: AppTextStart.routeNumber)),
        ),
        // The event lands last: its point with a spring, its date above, its name below.
        Positioned(
          left: width - 10,
          top: -9 - (1 - land) * 12,
          child: Opacity(
            opacity: land.clamp(0.0, 1.0),
            child: Transform.scale(
              scale: land,
              child: Container(
                width: 20,
                height: 20,
                decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.brassInk),
              ),
            ),
          ),
        ),
        Positioned(
          right: -6,
          top: -34,
          child: Opacity(opacity: land.clamp(0.0, 1.0), child: Text(date, style: AppTextStart.routeBrow)),
        ),
        Positioned(
          right: -6,
          top: 16,
          child: Opacity(opacity: land.clamp(0.0, 1.0), child: Text(event, style: AppTextStart.routeEvent)),
        ),
      ],
    );
  }
}

// ── d · the talk ──────────────────────────────────────────────────────────────────────────────────────────────

/// The role's bubble (37-6 with «прослушать» 28), the own dark bubble, the mic 30-3 at rest (41-2d). The role's line
/// types, «прослушать» pulses brass twice, 600 ms later the own line types, the mic comes last.
class _Talk extends StatefulWidget {
  const _Talk({required this.life});

  final _Life life;

  @override
  State<_Talk> createState() => _TalkState();
}

class _TalkState extends State<_Talk> with TickerProviderStateMixin, _LifeClock {
  @override
  _Life get life => widget.life;

  static double get _partner => IntroPages.partnerLine.length * StartMotion.typePerChar.inMilliseconds.toDouble();
  static double get _own => IntroPages.ownLine.length * StartMotion.typePerChar.inMilliseconds.toDouble();
  static final double _pulses = StartMotion.talkPulse.inMilliseconds * 2.0;
  static double get _ownAt => _partner + StartMotion.talkOwnDelay.inMilliseconds;
  static double get _micAt => _ownAt + _own;

  @override
  Duration get entrance => Duration(milliseconds: (_micAt + StartMotion.talkMicFade.inMilliseconds).round());

  @override
  void initState() {
    super.initState();
    initLife();
  }

  @override
  void didUpdateWidget(_Talk old) {
    super.didUpdateWidget(old);
    updateLife();
  }

  @override
  void dispose() {
    clock.dispose();
    super.dispose();
  }

  String _typed(String line, double start, double length) {
    if (widget.life.still) return line;
    final shown = (((ms - start) / length).clamp(0.0, 1.0) * line.length).floor();
    return line.substring(0, shown);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return AnimatedBuilder(
      animation: clock,
      builder: (context, _) {
        final partner = _typed(IntroPages.partnerLine, 0, _partner);
        final own = _typed(IntroPages.ownLine, _ownAt, _own);
        final nativeIn = span(_partner, 200);
        // Two brass pulses of «прослушать» once the line is out.
        final pulseT = ((ms - _partner) / _pulses).clamp(0.0, 1.0);
        final pulse = widget.life.still || pulseT >= 1 ? 0.0 : math.sin(math.pi * ((pulseT * 2) % 1));
        return Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 312),
                child: Container(
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                  decoration: BoxDecoration(
                    color: AppColors.paper,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: const [BoxShadow(color: AppColors.windowSourceShadow, blurRadius: 8, offset: Offset(0, 2))],
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // The whole line holds its place while it types, so the bubble does not grow letter by
                            // letter: the untyped rest is there, transparent.
                            Text.rich(TextSpan(
                              style: AppTextStart.sheetLine,
                              children: [
                                TextSpan(text: partner),
                                TextSpan(
                                  text: IntroPages.partnerLine.substring(partner.length),
                                  style: const TextStyle(color: AppColors.groundClear),
                                ),
                              ],
                            )),
                            if (l.introDLineNative.isNotEmpty) ...[
                              const SizedBox(height: 2),
                              Opacity(opacity: nativeIn, child: Text(l.introDLineNative, style: AppTextStart.bubbleNative)),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.brassInk, width: 1.5),
                          boxShadow: [
                            if (pulse > 0) BoxShadow(color: AppColors.sessionBrassRing.withValues(alpha: .3 * pulse), spreadRadius: 6 * pulse),
                          ],
                        ),
                        child: const Icon(LucideIcons.volume1, size: 14, color: AppColors.brassInk),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Align(
              alignment: Alignment.centerRight,
              child: Opacity(
                opacity: own.isEmpty && !widget.life.still ? 0 : 1,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 312),
                  child: Container(
                    padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                    decoration: BoxDecoration(color: AppColors.windowInk, borderRadius: BorderRadius.circular(16)),
                    child: Text.rich(TextSpan(
                      style: AppTextStart.sheetLine.copyWith(color: AppColors.paper),
                      children: [
                        TextSpan(text: own),
                        TextSpan(
                          text: IntroPages.ownLine.substring(own.length),
                          style: const TextStyle(color: AppColors.groundClear),
                        ),
                      ],
                    )),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 24),
            Opacity(
              opacity: span(_micAt, StartMotion.talkMicFade.inMilliseconds),
              child: Row(
                children: [
                  Container(
                    width: 72,
                    height: 72,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.windowInk,
                      boxShadow: [BoxShadow(color: AppColors.sessionMicShadow, blurRadius: 24, offset: Offset(0, 8))],
                    ),
                    child: const Icon(LucideIcons.mic, size: 28, color: AppColors.paper),
                  ),
                  const SizedBox(width: 14),
                  Text(l.introDTap, style: AppTextStart.sheetLineNative),
                ],
              ),
            ),
          ],
        );
      },
    );
  }
}

// ── e · the covers ────────────────────────────────────────────────────────────────────────────────────────────

/// Three store covers fanned out (112 × 210, 8 apart, ±5°, the middle one 28 higher), their names in Literata 17 and
/// word counts in 13; they fan up from below 80 ms apart.
class _Covers extends StatefulWidget {
  const _Covers({required this.life, required this.inset});

  final _Life life;
  final double inset;

  @override
  State<_Covers> createState() => _CoversState();
}

class _CoversState extends State<_Covers> with TickerProviderStateMixin, _LifeClock {
  @override
  _Life get life => widget.life;

  @override
  Duration get entrance => StartMotion.coverStep * 2 + StartMotion.coverIn;

  @override
  void initState() {
    super.initState();
    initLife();
  }

  @override
  void didUpdateWidget(_Covers old) {
    super.didUpdateWidget(old);
    updateLife();
  }

  @override
  void dispose() {
    clock.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final covers = [
      (-176.0, 20.0, -5.0, l.introECoverCity, 24, 'city'),
      (-56.0, -8.0, 0.0, l.introECoverHealth, 16, 'health'),
      (64.0, 20.0, 5.0, l.introECoverWork, 22, 'work'),
    ];
    return AnimatedBuilder(
      animation: clock,
      builder: (context, _) => LayoutBuilder(
        builder: (context, box) {
          final centre = box.maxWidth / 2;
          final children = <(int, Widget)>[];
          for (var i = 0; i < covers.length; i++) {
            final (dx, top, angle, title, count, asset) = covers[i];
            final t = span(StartMotion.coverStep.inMilliseconds * i, StartMotion.coverIn.inMilliseconds);
            children.add((
              i == 1 ? 3 : 1,
              Positioned(
                left: centre + dx,
                top: 180 + top + widget.inset + (1 - t) * 160,
                child: Opacity(
                  opacity: t,
                  child: Transform.rotate(
                    angle: angle * t * math.pi / 180,
                    child: _Cover(title: title, count: l.introEWordCount(count), asset: asset),
                  ),
                ),
              ),
            ));
          }
          children.sort((a, b) => a.$1.compareTo(b.$1));
          return Stack(clipBehavior: Clip.hardEdge, children: [for (final c in children) c.$2]);
        },
      ),
    );
  }
}

class _Cover extends StatelessWidget {
  const _Cover({required this.title, required this.count, required this.asset});

  final String title;
  final String count;
  final String asset;

  @override
  Widget build(BuildContext context) => Container(
    width: 112,
    height: 210,
    decoration: BoxDecoration(
      color: AppColors.paper,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [BoxShadow(color: AppColors.introCardShadow, blurRadius: 32, offset: Offset(0, 12))],
    ),
    clipBehavior: Clip.antiAlias,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: AppTextStart.coverTitle, maxLines: 2),
              const SizedBox(height: 2),
              Text(count, style: AppTextStart.sheetLineNative),
            ],
          ),
        ),
        Expanded(
          child: ColoredBox(
            color: AppColors.photoSlot,
            child: Image.asset('assets/intro/cover-$asset.jpg', fit: BoxFit.cover, excludeFromSemantics: true),
          ),
        ),
      ],
    ),
  );
}

/// «Начать» comes up last, after the covers (fade + 12 px).
class _StartButtonIn extends StatefulWidget {
  const _StartButtonIn({required this.life, required this.onTap});

  final _Life life;
  final VoidCallback onTap;

  @override
  State<_StartButtonIn> createState() => _StartButtonInState();
}

class _StartButtonInState extends State<_StartButtonIn> with TickerProviderStateMixin, _LifeClock {
  @override
  _Life get life => widget.life;

  static final _at = StartMotion.coverStep * 2 + StartMotion.coverIn;

  @override
  Duration get entrance => _at + StartMotion.sheetTextFade;

  @override
  void initState() {
    super.initState();
    initLife();
  }

  @override
  void didUpdateWidget(_StartButtonIn old) {
    super.didUpdateWidget(old);
    updateLife();
  }

  @override
  void dispose() {
    clock.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: clock,
    builder: (context, child) {
      final t = span(_at.inMilliseconds, StartMotion.sheetTextFade.inMilliseconds);
      return IgnorePointer(
        // Not tappable before it has come up.
        ignoring: t < .5,
        child: Opacity(
          opacity: t,
          child: Transform.translate(offset: Offset(0, (1 - t) * StartMotion.sheetTextRise), child: child),
        ),
      );
    },
    child: IntroStartButton(onTap: widget.onTap),
  );
}
