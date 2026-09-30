import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/speech/speech_diagnostics.dart';
import 'package:eng_std/features/auth/login_screen.dart';
import 'package:eng_std/features/plan/notify_prompt.dart';
import 'package:eng_std/features/plan/session/mic_ask.dart';
import 'package:eng_std/features/start/intro/intro_screen.dart';
import 'package:eng_std/features/start/splash_choreography.dart';
import 'package:eng_std/features/start/start_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/plan_goldens.dart';
import '../../support/start_harness.dart';

/// THE START AND ITS SHEETS AS GOLDENS (work order CLIENT-START, «Golden»): the final frames — the splash (41-1c), the
/// sign-in (41-4 a / b / c), the five «why» sheets (41-2 a…e), the microphone's pre-permission (41-3) and the
/// reminders' (43-1). The animations themselves are not goldens: their numbers are `splash_choreography_test.dart`'s.
///
/// ```bash
/// flutter test --update-goldens test/features/start/start_golden_test.dart
/// ```
/// The PNGs — `test/goldens/start/`, named by the frame.
void main() {
  setUpAll(() async {
    await setUpPlanGoldens();
    SignInPanel.debugDevDoor = false;
  });
  tearDownAll(() => SignInPanel.debugDevDoor = true);

  /// The app's shell with the motion ON — the splash plays its own choreography, not the reduced one.
  Widget shell(Widget home, {ScriptedAuth? auth, Locale locale = const Locale('ru')}) => ProviderScope(
    overrides: accountOverrides(auth: () => auth ?? ScriptedAuth(signInUser: denUser())),
    child: MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      locale: locale,
      supportedLocales: const [Locale('ru'), Locale('en')],
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      home: home,
    ),
  );

  Widget splash({Duration? at, SplashMode mode = SplashMode.first}) => StartScreen(
    mode: mode,
    frozenAt: at,
    onSignedIn: (_) {},
    onTerms: () {},
    onPrivacy: () {},
  );

  /// The canvas's frame: 390 × 844 under a 52 status bar, over a 34 home indicator.
  Future<void> shoot(WidgetTester tester, Widget app, String name, {Future<void> Function(WidgetTester)? prime}) {
    tester.view.padding = const FakeViewPadding(top: 52 * kGoldenDpr, bottom: 34 * kGoldenDpr);
    addTearDown(tester.view.resetPadding);
    return expectPlanGolden(
      tester,
      app,
      'start/$name',
      prime: (tester) async {
        await loadImages(tester);
        await prime?.call(tester);
        await loadImages(tester);
      },
    );
  }

  // ── 41-1 · the splash ────────────────────────────────────────────────────────────────────────────────────────
  testWidgets('41-1c заставка — последний кадр: слово, точка, «Готов говорить.»', (tester) async {
    await shoot(tester, shell(splash(at: StartMotion.firstLaunch - const Duration(milliseconds: 1))), '41-1c-splash');
  });

  testWidgets('41-1b повторный запуск — слово с точкой, без слогана', (tester) async {
    await shoot(tester, shell(splash(at: StartMotion.repeatLaunch, mode: SplashMode.repeat)), '41-1b-splash-repeat');
  });

  // ── 41-4 · the sign-in ───────────────────────────────────────────────────────────────────────────────────────
  testWidgets('41-4a вход: две двери и строка правил', (tester) async {
    await shoot(
      tester,
      shell(splash(at: StartMotion.firstLaunch)),
      '41-4a-sign-in',
      prime: (tester) => tester.pump(const Duration(milliseconds: 600)),
    );
  });

  testWidgets('41-4b вход не удался — строка над дверями', (tester) async {
    await shoot(
      tester,
      shell(splash(at: StartMotion.firstLaunch), auth: ScriptedAuth(signInError: StateError('network'))),
      '41-4b-sign-in-failed',
      prime: (tester) async {
        await tester.pump(const Duration(milliseconds: 600));
        await tester.tap(find.byKey(const ValueKey('sign-in-google')));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 300));
      },
    );
  });

  testWidgets('41-4c ожидание — спиннер у Apple, Google на 40 %', (tester) async {
    final hold = Completer<void>();
    addTearDown(() {
      if (!hold.isCompleted) hold.complete();
    });
    await shoot(
      tester,
      shell(splash(at: StartMotion.firstLaunch), auth: ScriptedAuth(signInUser: denUser(), hold: hold)),
      '41-4c-sign-in-waiting',
      prime: (tester) async {
        await tester.pump(const Duration(milliseconds: 600));
        await tester.tap(find.byKey(const ValueKey('sign-in-apple')));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 300));
      },
    );
  });

  // ── 41-2 · the five sheets ───────────────────────────────────────────────────────────────────────────────────
  for (final (i, letter) in ['a', 'b', 'c', 'd', 'e'].indexed) {
    testWidgets('41-2$letter лист ${i + 1} из 5 — последний кадр', (tester) async {
      await shoot(
        tester,
        shell(IntroScreen(onDone: () {}, initialPage: i, still: true)),
        '41-2$letter-sheet',
        prime: (tester) => tester.pump(const Duration(milliseconds: 100)),
      );
    });
  }

  // ── 41-3 · 43-1 · the pre-permissions ────────────────────────────────────────────────────────────────────────
  Widget asker(void Function(BuildContext context, WidgetRef ref) ask) =>
      shell(Scaffold(backgroundColor: AppColors.ground, body: _AskOnStart(ask)));

  testWidgets('41-3 микрофон — «Ritora слушает, как ты говоришь»', (tester) async {
    await shoot(
      tester,
      asker(
        (context, ref) => unawaited(askMicOnce(
          context,
          probe: () async => const SpeechProbe(
            localeId: 'en_US',
            microphone: SpeechPermission.notDetermined,
            recognition: SpeechPermission.notDetermined,
          ),
          allow: () async => true,
        )),
      ),
      '41-3-mic',
      prime: (tester) => tester.pump(const Duration(milliseconds: 400)),
    );
  });

  testWidgets('43-1 напоминания — «Напомнить про день 2 завтра в 19:00?»', (tester) async {
    await shoot(
      tester,
      asker((context, ref) => unawaited(offerReminders(context, ref, closedDay: 1))),
      '43-1-reminders',
      prime: (tester) => tester.pump(const Duration(milliseconds: 400)),
    );
  });
}

/// Asks once its first frame is up — the sheet over a bare page, no trigger of the test's own in the frame.
class _AskOnStart extends ConsumerStatefulWidget {
  const _AskOnStart(this.ask);

  final void Function(BuildContext context, WidgetRef ref) ask;

  @override
  ConsumerState<_AskOnStart> createState() => _AskOnStartState();
}

class _AskOnStartState extends ConsumerState<_AskOnStart> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) widget.ask(context, ref);
    });
  }

  @override
  Widget build(BuildContext context) => const SizedBox.expand();
}
