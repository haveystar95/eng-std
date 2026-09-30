import 'package:flutter/animation.dart';

/// EVERY TIMING OF THE APP'S START — the splash, the sign-in on it, the five «why» sheets, the pre-permission sheets and
/// the profile sheets (work order CLIENT-START, canvas `account-canvas.dc.html` series 41–43). One file on purpose: the
/// canvas states them as one table of captions, and a number that lives next to its widget is a number nobody finds.
///
/// The splash and the sheets run longer than [AppMotion.maxDuration] — they are the one place the product allows a
/// choreography, and the canvas measures them in whole seconds.
abstract final class StartMotion {
  // ── Splash 41-1 ──────────────────────────────────────────────────────────────────────────────────────────────

  /// The canvas curve for everything that appears: `cubic-bezier(.2,.8,.2,1)`.
  static const ease = Cubic(.2, .8, .2, 1);

  /// a · 0 → 600: the brass dot flies in from 80 px to the right and lands on the baseline with a spring.
  static const dotLand = Duration(milliseconds: 600);
  static const dotTravel = 80.0;

  /// The spring's overshoot — 8 % of the travel, past the landing point and back.
  static const dotOvershoot = 0.08;

  /// The dot is not there at the first frame (the launch screen has only the «R») — it fades in while it flies.
  static const dotFadeIn = Duration(milliseconds: 120);

  /// b · 600 → 1100: «itora» — each letter fades in over [letterFade], the next one [letterStep] later; the dot slides
  /// out by the width of the word over [wordSlide], and the whole word glides to the centre with it.
  static const lettersAt = Duration(milliseconds: 600);
  static const letterFade = Duration(milliseconds: 120);
  static const letterStep = Duration(milliseconds: 40);
  static const wordSlide = Duration(milliseconds: 500);

  /// c · 1100 → 1400: the slogan fades in rising 12 px (and the word rises with it to the column's centre).
  static const sloganAt = Duration(milliseconds: 1100);
  static const sloganFade = Duration(milliseconds: 300);
  static const sloganRise = 12.0;

  /// The first launch holds the whole composition and becomes the sign-in (41-4) here — «первый запуск 1,6 с».
  static const firstLaunch = Duration(milliseconds: 1600);

  /// A repeat launch plays only a → b, squeezed into 800 ms, then dissolves into the app.
  static const repeatDotLand = Duration(milliseconds: 440);
  static const repeatLettersAt = Duration(milliseconds: 440);
  static const repeatLetterFade = Duration(milliseconds: 90);
  static const repeatLetterStep = Duration(milliseconds: 30);
  static const repeatWordSlide = Duration(milliseconds: 360);
  static const repeatLaunch = Duration(milliseconds: 800);
  static const dissolve = Duration(milliseconds: 200);

  /// No loading indicator, ever: the splash waits for the server's first answer (41-1: it normally comes inside
  /// 1.5 s, and the splash looks the same either way) and at this mark gives up and opens the app in its offline state.
  static const serverCap = Duration(milliseconds: 4000);

  /// «Уменьшить движение»: the dot does not spring, the letters and the slogan dissolve — all in this.
  static const reducedFade = Duration(milliseconds: 200);

  // ── Sign-in on the splash 41-4 ──────────────────────────────────────────────────────────────────────────────

  /// Apple → Google → the legal line: fade + 12 px, 300 ms each, 60 ms apart.
  static const buttonsFade = Duration(milliseconds: 300);
  static const buttonsStep = Duration(milliseconds: 60);
  static const buttonsRise = 12.0;

  /// The tap: the button presses to 0.98 for 150 ms.
  static const buttonPress = Duration(milliseconds: 150);
  static const buttonPressScale = 0.98;

  /// Success: the spinner turns into a sage check for 300 ms (haptic success), then the screen moves on.
  static const signedInCheck = Duration(milliseconds: 300);

  /// → the first sheet: the wordmark and the buttons dissolve ([dissolve], 200 ms), the sheet's picture rises out of
  /// the paper from the bottom (500 ms) and its text lifts after it.
  static const toSheetsReveal = Duration(milliseconds: 500);

  // ── The five «why» sheets 41-2 ──────────────────────────────────────────────────────────────────────────────

  /// A sheet comes alive 300 ms after the page has settled; its background moves at 0.6 of the swipe.
  static const sheetAliveDelay = Duration(milliseconds: 300);
  static const sheetParallax = 0.6;

  /// Text of a sheet appears with fade + 12 px.
  static const sheetTextFade = Duration(milliseconds: 300);
  static const sheetTextRise = 12.0;

  /// «Пропустить» / «Начать» → the Plan tab: the sheet dissolves in 200 ms.
  static const sheetsOut = Duration(milliseconds: 200);

  /// a · the stack of scenes: five cards ride in from the right 80 ms apart, 350 ms each.
  static const stackCardIn = Duration(milliseconds: 350);
  static const stackCardStep = Duration(milliseconds: 80);

  /// a · at rest every 4 s the back card lifts to the top: 24 px up-right, scale 1.03, 600 ms ease-in-out.
  static const stackCycle = Duration(seconds: 4);
  static const stackLift = Duration(milliseconds: 600);
  static const stackLiftOffset = 24.0;
  static const stackLiftScale = 1.03;

  /// a · between lifts the cards breathe ±3 px, each on its own phase, a 5 s cycle.
  static const stackBreath = Duration(seconds: 5);
  static const stackBreathAmplitude = 3.0;

  /// b · the flag wall: a wave from the centre over 600 ms, each flag fading in 200 ms at a delay by its distance.
  static const flagsWave = Duration(milliseconds: 600);
  static const flagFade = Duration(milliseconds: 200);

  /// b · the lines type 40 ms a character with a brass caret; a line's flag lights up as the line starts:
  /// 1.0 → 1.12 → 1.0 and full colour in 300 ms, its 2 px brass ring drawn around in 300 ms.
  static const typePerChar = Duration(milliseconds: 40);
  static const flagLight = Duration(milliseconds: 300);
  static const flagLightScale = 1.12;
  static const lineGap = Duration(milliseconds: 250);

  /// b · at rest the unlit flags drift ±4 px on their own phases, a 6 s cycle, 25 → 35 → 25 %.
  static const flagDrift = Duration(seconds: 6);
  static const flagDriftAmplitude = 4.0;

  /// c · the route: the line draws left to right in 600 ms, nodes pop (0 → 1, 150 ms) as it passes, the event dot
  /// lands last with a spring and a tick; then the day-1 check (150 ms) and «сегодня»; the stage icons 40 ms apart.
  static const routeDraw = Duration(milliseconds: 600);
  static const routeNodePop = Duration(milliseconds: 150);
  static const routeEventLand = Duration(milliseconds: 450);
  static const routeCheck = Duration(milliseconds: 150);
  static const routeStageStep = Duration(milliseconds: 40);
  static const routeStageFade = Duration(milliseconds: 200);

  /// d · the role's bubble types, «прослушать» pulses twice, 600 ms later the own bubble types, the mic comes last.
  static const talkPulse = Duration(milliseconds: 600);
  static const talkOwnDelay = Duration(milliseconds: 600);
  static const talkMicFade = Duration(milliseconds: 300);

  /// e · three covers fan up from below 80 ms apart, 350 ms each; «Начать» after them.
  static const coverIn = Duration(milliseconds: 350);
  static const coverStep = Duration(milliseconds: 80);

  // ── Sheets over a screen (41-3, 42-2, 42-3, 42-4, 43-1) — the session's exit sheet 30-8 ──────────────────────

  /// «Удаляем…» holds the delete sheet: nothing closes it until the server answers.
  static const deletingMin = Duration(milliseconds: 300);
}
