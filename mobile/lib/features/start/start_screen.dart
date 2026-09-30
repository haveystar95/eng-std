import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/brand/wordmark.dart';
import 'package:eng_std/ui/brand/wordmark_metrics.dart';

import '../../data/models.dart';
import '../auth/login_screen.dart';
import 'splash_choreography.dart';

/// THE START SCREEN — the splash (frame 41-1) and, on the same paper, the sign-in (41-4).
///
/// Paper #EFEBE3, the wordmark in the middle of the screen minus 64 at the bottom (the canvas column), and nothing else:
/// no loading indicator, no version, no company. It starts on the launch screen's own «R» (LaunchScreen.storyboard shows
/// the same image at the same place), so iOS hands over to Flutter without a flash or a jump.
///
/// [SplashMode.first] plays a → b → c and at [StartMotion.firstLaunch] the doors come up at the foot; [SplashMode.repeat]
/// plays a → b in [StartMotion.repeatLaunch] and calls [onPlayed] — the gate decides when to dissolve into the app;
/// [SplashMode.signedOut] stands finished and brings the doors up at once. [leaving] dissolves the whole screen in
/// [StartMotion.dissolve] (the app, or the first «why» sheet, is already drawn underneath).
class StartScreen extends StatefulWidget {
  const StartScreen({
    super.key,
    required this.mode,
    required this.onSignedIn,
    required this.onTerms,
    required this.onPrivacy,
    this.onPlayed,
    this.leaving = false,
    this.frozenAt,
  });

  final SplashMode mode;
  final ValueChanged<AppUser> onSignedIn;
  final VoidCallback onTerms;
  final VoidCallback onPrivacy;

  /// The splash has played its length.
  final VoidCallback? onPlayed;

  final bool leaving;

  /// Stand still at this moment of the splash instead of playing it — the snapshots of 41-1 a / b / c.
  @visibleForTesting
  final Duration? frozenAt;

  @override
  State<StartScreen> createState() => _StartScreenState();
}

class _StartScreenState extends State<StartScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _clock = AnimationController(vsync: this);
  bool _ticked = false;
  bool _played = false;
  bool _started = false;

  /// «Уменьшить движение», as the screen's MediaQuery says — read where dependencies may be read, so the clock's
  /// listener (which also runs from inside [_start]) never has to ask.
  bool _reduced = false;

  Duration get _elapsed => widget.frozenAt ?? _clock.duration! * _clock.value;

  @override
  void initState() {
    super.initState();
    _clock.addListener(_onTick);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _reduced = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (!_started) {
      _started = true;
      _start();
    }
  }

  @override
  void didUpdateWidget(StartScreen old) {
    super.didUpdateWidget(old);
    if (old.mode != widget.mode) _start();
  }

  void _start() {
    _ticked = false;
    _played = false;
    final length = SplashChoreography.length(widget.mode);
    if (widget.frozenAt != null || length == Duration.zero) {
      _clock.duration = const Duration(milliseconds: 1);
      _clock.value = 1;
      if (widget.frozenAt == null) WidgetsBinding.instance.addPostFrameCallback((_) => _finish());
      return;
    }
    _clock
      ..duration = length
      ..forward(from: 0).whenCompleteOrCancel(_finish);
  }

  void _onTick() {
    final landing = SplashChoreography.landingAt(mode: widget.mode, reduced: _reduced);
    if (!_ticked && widget.mode != SplashMode.signedOut && _elapsed >= landing) {
      _ticked = true;
      // «тик хаптики в момент посадки» — and it stays under «Уменьшить движение».
      AppHaptics.light();
    }
  }

  void _finish() {
    if (!mounted || _played) return;
    _played = true;
    setState(() {});
    widget.onPlayed?.call();
  }

  @override
  void dispose() {
    _clock.dispose();
    super.dispose();
  }

  bool get _signInVisible => switch (widget.mode) {
    SplashMode.first => _played || (widget.frozenAt != null && widget.frozenAt! >= StartMotion.firstLaunch),
    SplashMode.signedOut => true,
    SplashMode.repeat => false,
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: AnimatedOpacity(
        opacity: widget.leaving ? 0 : 1,
        duration: StartMotion.dissolve,
        // A Material, not a bare colour: the screen stands outside any Scaffold, and text with no Material above it
        // falls back to the framework's error style (yellow double underline).
        child: Material(
          color: AppColors.ground,
          child: Stack(
            fit: StackFit.expand,
            children: [
              AnimatedBuilder(
                animation: _clock,
                builder: (context, _) => _MarkLayer(
                  frame: SplashChoreography.at(_elapsed, mode: widget.mode, reduced: _reduced),
                  slogan: l.startSlogan,
                  withSlogan: widget.mode != SplashMode.repeat,
                ),
              ),
              Positioned(
                left: 24,
                right: 24,
                bottom: 40,
                child: SignInPanel(
                  visible: _signInVisible,
                  onSignedIn: widget.onSignedIn,
                  onTerms: widget.onTerms,
                  onPrivacy: widget.onPrivacy,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The mark at one moment: the word (or the lone «R»), the brass dot, the slogan — placed by the canvas column.
class _MarkLayer extends StatelessWidget {
  const _MarkLayer({required this.frame, required this.slogan, required this.withSlogan});

  final SplashFrame frame;
  final String slogan;
  final bool withSlogan;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, box) {
        final w = box.maxWidth;
        final h = box.maxHeight;
        // The row's top when it is centred alone (a, b) — the launch screen's «R» sits exactly here — and the rise
        // when the slogan joins the column (c).
        double rowTopAt(double lift) =>
            (h - WordmarkGeometry.columnBottomPad - kWordmarkHeight) / 2 - WordmarkGeometry.sloganLift * lift;
        final rowTop = rowTopAt(frame.lift);

        // 41-1a centres the lone «R» image (as the launch screen does), 41-1b the whole «Ritora.»; the row glides.
        final rImageLeft = (w - kRMarkImageWidth) / 2;
        final aWordLeft = rImageLeft + kRMarkInset;
        final bWordLeft = (w - WordmarkGeometry.rowWidth) / 2;

        final children = <Widget>[];
        void mark({
          required double spread,
          required List<double> letters,
          required double opacity,
          required bool loneR,
          required double top,
        }) {
          final wordLeft = aWordLeft + (bWordLeft - aWordLeft) * spread;
          final dotLeft = wordLeft +
              kWordmarkRWidth +
              (kWordmarkWidth - kWordmarkRWidth) * spread +
              WordmarkGeometry.gap +
              frame.dotShift;
          children
            ..add(Positioned(
              left: loneR ? rImageLeft : wordLeft,
              top: top,
              child: Opacity(
                opacity: opacity,
                child: loneR ? const WordmarkR() : WordmarkLetters(letterOpacity: letters),
              ),
            ))
            ..add(Positioned(
              left: dotLeft,
              top: top + WordmarkGeometry.dotTop,
              child: Opacity(opacity: (frame.dotOpacity * opacity).clamp(0.0, 1.0), child: const BrandDot()),
            ));
        }

        final fade = frame.wordFade;
        if (fade != null) {
          // «Уменьшить движение»: the «R» composition dissolves into the finished word — nothing travels.
          // The «R» stands where the launch screen left it; the word comes in at its final height.
          if (fade < 1) mark(spread: 0, letters: const [1, 0, 0, 0, 0, 0], opacity: 1 - fade, loneR: true, top: rowTopAt(0));
          if (fade > 0) mark(spread: 1, letters: const [1, 1, 1, 1, 1, 1], opacity: fade, loneR: false, top: rowTop);
        } else {
          // Until «itora» starts, the lone «R» image — the launch screen's pixels; then the six letters, the «R» among
          // them on the same pixels.
          final loneR = frame.letters.skip(1).every((o) => o == 0) && frame.spread == 0;
          mark(spread: frame.spread, letters: frame.letters, opacity: 1, loneR: loneR, top: rowTop);
        }

        if (withSlogan && frame.slogan > 0) {
          children.add(Positioned(
            left: 0,
            right: 0,
            top: rowTop + kWordmarkHeight + WordmarkGeometry.sloganGap + (1 - frame.slogan) * StartMotion.sloganRise,
            child: Opacity(
              opacity: frame.slogan,
              child: Text(slogan, key: const ValueKey('start-slogan'), textAlign: TextAlign.center, style: AppTextStart.slogan),
            ),
          ));
        }
        return Stack(children: children);
      },
    );
  }
}
