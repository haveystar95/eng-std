import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/start/splash_choreography.dart';
import 'package:eng_std/theme/start_motion.dart';

/// THE SPLASH AS NUMBERS (frame 41-1, work order CLIENT-START §1) — the key frames the canvas draws, asked of the
/// choreography at their milliseconds; the animation itself is not a golden.
void main() {
  SplashFrame at(int ms, SplashMode mode, {bool reduced = false}) =>
      SplashChoreography.at(Duration(milliseconds: ms), mode: mode, reduced: reduced);

  group('first launch — a → b → c', () {
    test('0 ms: the launch screen\'s «R» alone — no dot yet, the other letters out', () {
      final f = at(0, SplashMode.first);
      expect(f.dotOpacity, 0);
      expect(f.letters, [1, 0, 0, 0, 0, 0]);
      expect(f.spread, 0);
      expect(f.slogan, 0);
    });

    test('a · the dot flies in from 80 px and has landed by 600 ms', () {
      expect(at(0, SplashMode.first).dotShift, StartMotion.dotTravel);
      expect(at(600, SplashMode.first).dotShift, closeTo(0, .001));
    });

    test('a · the spring overshoots by 8 % of the flight before it settles', () {
      var least = double.infinity;
      for (var ms = 0; ms <= 600; ms += 5) {
        final shift = at(ms, SplashMode.first).dotShift;
        if (shift < least) least = shift;
      }
      expect(-least / StartMotion.dotTravel, closeTo(StartMotion.dotOvershoot, .005));
    });

    test('a · the tick is at the landing: the first time the dot reaches its place', () {
      final landing = SplashChoreography.landingAt(mode: SplashMode.first);
      expect(landing, greaterThan(Duration.zero));
      expect(landing, lessThan(StartMotion.dotLand));
      final ms = landing.inMilliseconds;
      expect(at(ms - 5, SplashMode.first).dotShift, greaterThan(0));
      expect(at(ms + 5, SplashMode.first).dotShift, lessThanOrEqualTo(0));
    });

    test('b · «itora» one by one, 40 ms apart, each in 120 ms; the word centred by 1100 ms', () {
      final f = at(600 + 40 + 60, SplashMode.first);
      expect(f.letters[1], greaterThan(f.letters[2]));
      expect(f.letters[5], 0);
      expect(at(600 + 4 * 40 + 120, SplashMode.first).letters, everyElement(1.0));
      expect(at(1100, SplashMode.first).spread, 1);
    });

    test('c · the slogan fades in rising, done at 1400 ms; the row rises with it', () {
      expect(at(1100, SplashMode.first).slogan, 0);
      final c = at(1400, SplashMode.first);
      expect(c.slogan, 1);
      expect(c.lift, 1);
    });

    test('the first launch becomes the sign-in at 1.6 s', () {
      expect(SplashChoreography.length(SplashMode.first), const Duration(milliseconds: 1600));
    });
  });

  group('repeat launch — a → b in 800 ms, no slogan', () {
    test('the word is whole and centred at 800 ms; no slogan, no rise', () {
      final f = at(800, SplashMode.repeat);
      expect(f.letters, everyElement(1.0));
      expect(f.spread, 1);
      expect(f.slogan, 0);
      expect(f.lift, 0);
      expect(SplashChoreography.length(SplashMode.repeat), const Duration(milliseconds: 800));
    });
  });

  group('«Уменьшить движение»', () {
    test('the dot does not fly: it fades in on its place in 200 ms', () {
      expect(at(0, SplashMode.first, reduced: true).dotShift, 0);
      expect(at(200, SplashMode.first, reduced: true).dotOpacity, 1);
    });

    test('the «R» dissolves into the whole word in 200 ms from 600 ms; the slogan dissolves at 1100', () {
      expect(at(599, SplashMode.first, reduced: true).wordFade, 0);
      expect(at(800, SplashMode.first, reduced: true).wordFade, 1);
      expect(at(1300, SplashMode.first, reduced: true).slogan, 1);
    });
  });

  test('back from «Выйти»: the finished composition at once', () {
    final f = at(0, SplashMode.signedOut);
    expect(f.letters, everyElement(1.0));
    expect(f.slogan, 1);
    expect(f.dotOpacity, 1);
  });
}
