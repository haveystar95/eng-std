import 'dart:math' as math;

import 'package:flutter/animation.dart';
import 'package:flutter/foundation.dart';

import 'package:eng_std/theme/start_motion.dart';

/// Which splash plays (41-1).
enum SplashMode {
  /// No session: a → b → c, and at [StartMotion.firstLaunch] the sign-in comes up on the same screen (→ 41-4).
  first,

  /// A session: only a → b, squeezed into [StartMotion.repeatLaunch]; no slogan; then the app.
  repeat,

  /// Back from «Выйти» or a deleted account: the finished composition at once, and the sign-in fades in (→ 41-4a).
  signedOut,
}

/// ONE MOMENT OF THE SPLASH — everything the screen needs to place the mark, as numbers.
@immutable
class SplashFrame {
  const SplashFrame({
    required this.dotShift,
    required this.dotOpacity,
    required this.letters,
    required this.spread,
    required this.slogan,
    required this.lift,
    this.wordFade,
  });

  /// How far right of its resting place the dot still is (the flight of 41-1a; negative — the spring's overshoot).
  final double dotShift;
  final double dotOpacity;

  /// Opacity of «R», «i», «t», «o», «r», «a».
  final List<double> letters;

  /// 0 — the «R» alone is centred (41-1a); 1 — the whole «Ritora.» is (41-1b). The row glides between them.
  final double spread;

  /// 0…1 — the slogan's appearance (fade, and the last 12 px of its rise).
  final double slogan;

  /// 0…1 — how far the row has risen to make room for the slogan ([WordmarkGeometry.sloganLift] at 1).
  final double lift;

  /// «Уменьшить движение»: nothing moves — the «R»-only composition dissolves into the finished word. Null — motion is
  /// on, [spread] places the row.
  final double? wordFade;
}

/// THE SPLASH AS A FUNCTION OF TIME (41-1) — kept apart from the widget so a test can ask any millisecond of it.
abstract final class SplashChoreography {
  static SplashFrame at(Duration elapsed, {required SplashMode mode, bool reduced = false}) {
    final t = elapsed.inMicroseconds / 1000.0;
    if (mode == SplashMode.signedOut) return finished(withSlogan: true);
    final repeat = mode == SplashMode.repeat;
    final slogan = !repeat;

    if (reduced) return _reduced(t, slogan: slogan, repeat: repeat);

    final dotLand = _ms(repeat ? StartMotion.repeatDotLand : StartMotion.dotLand);
    final lettersAt = _ms(repeat ? StartMotion.repeatLettersAt : StartMotion.lettersAt);
    final letterStep = _ms(repeat ? StartMotion.repeatLetterStep : StartMotion.letterStep);
    final letterFade = _ms(repeat ? StartMotion.repeatLetterFade : StartMotion.letterFade);
    final wordSlide = _ms(repeat ? StartMotion.repeatWordSlide : StartMotion.wordSlide);

    final flight = dotSpring.transform((t / dotLand).clamp(0.0, 1.0));
    final spread = StartMotion.ease.transform(((t - lettersAt) / wordSlide).clamp(0.0, 1.0));
    final sloganT = slogan ? StartMotion.ease.transform(((t - _ms(StartMotion.sloganAt)) / _ms(StartMotion.sloganFade)).clamp(0.0, 1.0)) : 0.0;

    return SplashFrame(
      dotShift: StartMotion.dotTravel * (1 - flight),
      dotOpacity: (t / _ms(StartMotion.dotFadeIn)).clamp(0.0, 1.0),
      letters: [
        1,
        for (var k = 1; k < 6; k++)
          StartMotion.ease.transform(((t - lettersAt - (k - 1) * letterStep) / letterFade).clamp(0.0, 1.0)),
      ],
      spread: spread,
      slogan: sloganT,
      lift: sloganT,
    );
  }

  /// The finished mark — 41-1b ([withSlogan] false) or 41-1c.
  static SplashFrame finished({required bool withSlogan}) => SplashFrame(
    dotShift: 0,
    dotOpacity: 1,
    letters: const [1, 1, 1, 1, 1, 1],
    spread: 1,
    slogan: withSlogan ? 1 : 0,
    lift: withSlogan ? 1 : 0,
  );

  /// «Уменьшить движение» (41-1): the dot does not fly — it fades in on its place; the letters and the slogan dissolve
  /// in [StartMotion.reducedFade] at the moments the moving version starts them; the row does not glide — the «R»
  /// composition dissolves into the word's. Haptics stay (the screen ticks at [landingAt]).
  static SplashFrame _reduced(double t, {required bool slogan, required bool repeat}) {
    final fade = _ms(StartMotion.reducedFade);
    final lettersAt = _ms(repeat ? StartMotion.repeatLettersAt : StartMotion.lettersAt);
    final word = ((t - lettersAt) / fade).clamp(0.0, 1.0);
    final sloganT = slogan ? ((t - _ms(StartMotion.sloganAt)) / fade).clamp(0.0, 1.0) : 0.0;
    return SplashFrame(
      dotShift: 0,
      dotOpacity: (t / fade).clamp(0.0, 1.0),
      letters: const [1, 1, 1, 1, 1, 1],
      spread: 1,
      slogan: sloganT,
      // The slogan's room is made while nothing is on screen to move: the word dissolves in already at its final
      // height when a slogan will follow.
      lift: slogan ? 1 : 0,
      wordFade: word,
    );
  }

  /// When the dot first touches its resting place — the haptic tick of 41-1a («тик в момент посадки»).
  static Duration landingAt({required SplashMode mode, bool reduced = false}) {
    if (mode == SplashMode.signedOut) return Duration.zero;
    if (reduced) return StartMotion.reducedFade;
    final land = repeat(mode) ? StartMotion.repeatDotLand : StartMotion.dotLand;
    return land * DotSpring.firstArrival;
  }

  /// How long the splash plays before it hands over — to the sign-in (first) or to the app (repeat).
  static Duration length(SplashMode mode) => switch (mode) {
    SplashMode.first => StartMotion.firstLaunch,
    SplashMode.repeat => StartMotion.repeatLaunch,
    SplashMode.signedOut => Duration.zero,
  };

  static bool repeat(SplashMode mode) => mode == SplashMode.repeat;

  static const dotSpring = DotSpring();

  static double _ms(Duration d) => d.inMicroseconds / 1000.0;
}

/// THE DOT'S LANDING — a damped spring that overshoots its resting place by [StartMotion.dotOvershoot] and settles
/// within the flight's duration (41-1a: «с пружиной, overshoot 8 %»). Normalised: 0 at the start, 1 at rest.
class DotSpring extends Curve {
  const DotSpring();

  /// Damping ratio for the overshoot: overshoot = e^(−ζπ/√(1−ζ²)).
  static final double _zeta = () {
    final l = math.log(StartMotion.dotOvershoot);
    return -l / math.sqrt(math.pi * math.pi + l * l);
  }();

  /// Natural frequency chosen so the spring is within ~0.3 % of rest at t = 1 (then it snaps — under a third of a pixel
  /// on the 80 px flight).
  static final double _omega = 5.8 / _zeta;

  static final double _damped = _omega * math.sqrt(1 - _zeta * _zeta);

  /// Where the curve first reaches 1 — the moment the dot touches down.
  static double get firstArrival {
    final phase = math.pi - math.atan(math.sqrt(1 - _zeta * _zeta) / _zeta);
    return phase / _damped;
  }

  @override
  double transformInternal(double t) {
    if (t >= 1) return 1;
    final envelope = math.exp(-_zeta * _omega * t);
    return 1 - envelope * (math.cos(_damped * t) + _zeta / math.sqrt(1 - _zeta * _zeta) * math.sin(_damped * t));
  }
}
