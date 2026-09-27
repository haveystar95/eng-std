import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/auth_repository.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart' show AuthController;
import 'package:eng_std/data/start/account_device_store.dart';
import 'package:eng_std/features/auth/login_screen.dart';
import 'package:eng_std/features/home/home_screen.dart' show kPlanTabIndex;
import 'package:eng_std/features/profile/profile_screen.dart';
import 'package:eng_std/features/start/intro/intro_screen.dart';
import 'package:eng_std/features/start/start_gate.dart';
import 'package:eng_std/features/start/start_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../support/start_harness.dart';

/// THE START ON THE CANON (work order CLIENT-START §§1–3, frames 41-1, 41-4, 41-2): a first launch from zero goes
/// splash → sign-in → the five sheets → the Plan tab; a repeat launch goes straight to the app; the sheets come once
/// per account on this phone; the account leaving (sign-out, deletion) brings the sign-in back.
void main() {
  setUpAll(setUpPlanGoldens);

  final den = denUser();
  final other = AppUser(id: '01TESTUSER000000000000000B', name: 'Марина');

  Future<void> launch(
    WidgetTester tester, {
    required AuthController Function() auth,
    MemoryKeyValue? keychain,
    Widget Function(AppUser user, int tab)? app,
  }) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = const Size(390, 844) * 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      ProviderScope(
        overrides: accountOverrides(auth: auth, keychain: keychain),
        child: _shell(StartGate(app: app ?? _stubApp)),
      ),
    );
  }

  group('первый запуск с нуля', () {
    // ЛОВИТ: вход, который встаёт посреди заставки; листы мимо; приложение не на вкладке «План».
    testWidgets('заставка 1,6 с → вход → «Войти с Apple» → пять листов → «Начать» → вкладка «План»', (tester) async {
      final keychain = MemoryKeyValue();
      await launch(tester, auth: () => ScriptedAuth(signInUser: den), keychain: keychain);
      await _begin(tester);

      expect(find.byType(StartScreen), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 1000));
      expect(find.byKey(const ValueKey('start-slogan')), findsNothing, reason: 'the slogan joins at 1100 ms');
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.byKey(const ValueKey('start-slogan')), findsOneWidget);
      expect(find.text('Готов говорить.'), findsOneWidget);
      expect(find.byKey(const ValueKey('sign-in-apple')), findsNothing, reason: 'the splash is still playing');

      await tester.pump(const Duration(milliseconds: 400));
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.byKey(const ValueKey('sign-in-apple')), findsOneWidget);
      expect(find.byKey(const ValueKey('sign-in-google')), findsOneWidget);
      expect(find.text(nbTypo('Войти с Apple')), findsOneWidget);
      expect(find.text(nbTypo('Войти с Google')), findsOneWidget);
      expect(find.byKey(const ValueKey('sign-in-legal')), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('sign-in-apple')));
      await _frames(tester);
      expect(find.byKey(const ValueKey('sign-in-check')), findsOneWidget, reason: 'the spinner turned into the check');
      await _toSheets(tester);

      expect(find.byType(IntroScreen), findsOneWidget);
      expect(find.byType(StartScreen), findsNothing);
      final titles = [for (final t in ['Скоро важный', 'Язык —', 'Двадцать минут', 'Живой разговор с ИИ', 'Слова остаются']) nbTypo(t)];
      for (final (i, title) in titles.indexed) {
        expect(find.textContaining(title, findRichText: true), findsWidgets, reason: 'sheet ${i + 1}');
        if (i < titles.length - 1) {
          expect(find.byKey(const ValueKey('intro-skip')), findsOneWidget);
          await tester.drag(find.byType(PageView), const Offset(-320, 0));
          await _run(tester, const Duration(milliseconds: 700));
        }
      }
      expect(find.byKey(const ValueKey('intro-skip')), findsNothing, reason: 'the last sheet has «Начать», not «Пропустить»');
      // «Начать» comes up last, after the covers — and cannot be tapped before.
      await _run(tester, const Duration(milliseconds: 1500));

      await tester.tap(find.byKey(const ValueKey('intro-start')));
      await _frames(tester);
      await tester.pump(StartMotion.sheetsOut + _frame);
      await _frames(tester);

      expect(find.byType(IntroScreen), findsNothing);
      expect(find.text('app ${den.id} tab $kPlanTabIndex'), findsOneWidget, reason: 'the sheets open the Plan tab');
      expect(keychain.values['intro_seen:${den.id}'], '1');
      await _drain(tester);
    });

    // ЛОВИТ: «Пропустить», которое ведёт не туда или не запоминает, что листы показаны.
    testWidgets('«Пропустить» на первом листе → вкладка «План», листы отмечены', (tester) async {
      final keychain = MemoryKeyValue();
      await launch(tester, auth: () => ScriptedAuth(signInUser: den), keychain: keychain);
      await _toSignIn(tester);
      await tester.tap(find.byKey(const ValueKey('sign-in-google')));
      await _toSheets(tester);

      await tester.tap(find.byKey(const ValueKey('intro-skip')));
      await _frames(tester);
      await tester.pump(StartMotion.sheetsOut + _frame);
      await _frames(tester);

      expect(find.text('app ${den.id} tab $kPlanTabIndex'), findsOneWidget);
      expect(keychain.values['intro_seen:${den.id}'], '1');
      await _drain(tester);
    });
  });

  group('листы — один раз на аккаунт на этом телефоне', () {
    // ЛОВИТ: листы при каждом входе того же человека (после «Выйти»).
    testWidgets('аккаунт уже видел листы — после входа сразу приложение', (tester) async {
      final keychain = MemoryKeyValue({'intro_seen:${den.id}': '1'});
      await launch(tester, auth: () => ScriptedAuth(signInUser: den), keychain: keychain);
      await _toSignIn(tester);
      await tester.tap(find.byKey(const ValueKey('sign-in-apple')));
      await _toSheets(tester);

      expect(find.byType(IntroScreen), findsNothing);
      expect(find.text('app ${den.id} tab 0'), findsOneWidget);
      await _drain(tester);
    });

    // ЛОВИТ: флаг «листы показаны» на телефон, а не на аккаунт — второй человек на том же телефоне их не увидит.
    testWidgets('другой аккаунт на том же телефоне — листы показываются ему', (tester) async {
      final keychain = MemoryKeyValue({'intro_seen:${den.id}': '1'});
      await launch(tester, auth: () => ScriptedAuth(signInUser: other), keychain: keychain);
      await _toSignIn(tester);
      await tester.tap(find.byKey(const ValueKey('sign-in-apple')));
      await _toSheets(tester);

      expect(find.byType(IntroScreen), findsOneWidget);
      await _drain(tester);
    });
  });

  group('вход — состояния 41-4', () {
    // ЛОВИТ: ошибку без строки, строку не того текста, двери, которые остаются мёртвыми.
    testWidgets('не удалось — строка «Не удалось войти. Попробуй ещё раз», обе двери живы', (tester) async {
      await launch(tester, auth: () => ScriptedAuth(signInError: StateError('network')));
      await _toSignIn(tester);
      await tester.tap(find.byKey(const ValueKey('sign-in-google')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));

      expect(find.byKey(const ValueKey('sign-in-failed')), findsOneWidget);
      expect(find.text(nbTypo('Не удалось войти. Попробуй ещё раз')), findsOneWidget);
      expect(_button(tester, 'sign-in-apple').state, SignInButtonState.ready);
      expect(_button(tester, 'sign-in-google').state, SignInButtonState.ready);
      expect(find.byType(IntroScreen), findsNothing);
      await _drain(tester);
    });

    // ЛОВИТ: закрытый лист Apple, показанный как ошибка.
    testWidgets('лист Apple закрыт — ни строки ошибки, ни перехода', (tester) async {
      await launch(tester, auth: () => ScriptedAuth(signInError: AuthException(AuthError.cancelled)));
      await _toSignIn(tester);
      await tester.tap(find.byKey(const ValueKey('sign-in-apple')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));

      expect(find.byKey(const ValueKey('sign-in-failed')), findsNothing);
      expect(_button(tester, 'sign-in-apple').state, SignInButtonState.ready);
      await _drain(tester);
    });

    // ЛОВИТ: второй тап по другой двери посреди входа, и ожидание без спиннера.
    testWidgets('ожидание — у нажатой спиннер, другая на 40 % и не нажимается', (tester) async {
      final hold = Completer<void>();
      late ScriptedAuth auth;
      await launch(tester, auth: () => auth = ScriptedAuth(signInUser: den, hold: hold));
      await _toSignIn(tester);
      await tester.tap(find.byKey(const ValueKey('sign-in-apple')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));

      expect(_button(tester, 'sign-in-apple').state, SignInButtonState.busy);
      expect(find.byKey(const ValueKey('sign-in-spinner')), findsOneWidget);
      expect(_button(tester, 'sign-in-google').state, SignInButtonState.dimmed);
      await tester.tap(find.byKey(const ValueKey('sign-in-google')), warnIfMissed: false);
      await tester.pump();
      expect(auth.googleCalls, 0);

      hold.complete();
      await tester.pump();
      await tester.pump();
      expect(find.byKey(const ValueKey('sign-in-check')), findsOneWidget);
      await _toSheets(tester);
      await _drain(tester);
    });
  });

  group('повторный запуск', () {
    // ЛОВИТ: вход или листы у того, кто уже внутри; заставку, которая ждёт полную хореографию первого запуска.
    testWidgets('сессия есть — a → b за 800 мс и растворение в приложение; ни входа, ни листов', (tester) async {
      await launch(tester, auth: () => ScriptedAuth(restored: den), keychain: MemoryKeyValue({'intro_seen:${den.id}': '1'}));
      await _begin(tester);
      expect(find.byType(StartScreen), findsOneWidget);
      await tester.pump(StartMotion.repeatLaunch + _frame);
      await _frames(tester);
      await tester.pump(StartMotion.dissolve + _frame);
      await _frames(tester);

      expect(find.byType(StartScreen), findsNothing);
      expect(find.byKey(const ValueKey('sign-in-apple')), findsNothing);
      expect(find.byType(IntroScreen), findsNothing);
      expect(find.text('app ${den.id} tab 0'), findsOneWidget);
      expect(find.byKey(const ValueKey('start-slogan')), findsNothing, reason: 'a repeat launch has no slogan');
      await _drain(tester);
    });

    // ЛОВИТ: вечную заставку при мёртвом сервере — и заставку, которая уходит, не дождавшись ответа.
    testWidgets('сервер молчит — заставка ждёт до 4 с, без индикатора, и открывает приложение', (tester) async {
      await launch(tester, auth: () => _SilentServerAuth(den));
      await _begin(tester);
      await tester.pump(StartMotion.repeatLaunch + _frame);
      await _frames(tester);
      await tester.pump(const Duration(milliseconds: 2600));
      expect(find.byType(StartScreen), findsOneWidget, reason: 'at 3.5 s still waiting for the first answer');
      expect(find.byType(CircularProgressIndicator), findsNothing, reason: 'no loader, ever');

      await tester.pump(const Duration(milliseconds: 600));
      await _frames(tester);
      await tester.pump(StartMotion.dissolve + _frame);
      await _frames(tester);
      expect(find.byType(StartScreen), findsNothing);
      expect(find.text('app ${den.id} tab 0'), findsOneWidget);
      await _drain(tester);
    });
  });

  group('аккаунт уходит', () {
    // ЛОВИТ: «Выйти», после которого остаётся приложение или проигрывается вся заставка заново.
    testWidgets('«Выйти» — вход на заставке сразу', (tester) async {
      late ScriptedAuth auth;
      await launch(
        tester,
        auth: () => auth = ScriptedAuth(restored: den),
        app: (user, tab) => const ProfileScreen(),
      );
      await _toApp(tester);
      await tester.scrollUntilVisible(find.byKey(const ValueKey('profile-sign-out')), 300);
      await tester.tap(find.byKey(const ValueKey('profile-sign-out')));
      await _frames(tester);
      await tester.pump(const Duration(milliseconds: 600));
      await _frames(tester);

      expect(auth.signOutCalls, 1);
      expect(find.byType(ProfileScreen), findsNothing);
      expect(find.byKey(const ValueKey('sign-in-apple')), findsOneWidget);
      await _drain(tester);
    });

    // ЛОВИТ: удаление, которое не дошло до сервера, и приложение, оставшееся на экране после удаления.
    testWidgets('«Удалить аккаунт» → 42-3 → DELETE /auth/me → заставка со входом', (tester) async {
      final wire = AuthWire(den.toJson());
      FlutterSecureStorage.setMockInitialValues({'api_token': 'token-den', 'api_user': jsonEncode(den.toJson())});
      // Tall enough for the whole profile: the test is about the deletion, not the scrolling.
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1400) * 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(
        ProviderScope(
          overrides: accountOverrides(repository: wiredRepository(wire), keychain: MemoryKeyValue({'intro_seen:${den.id}': '1'})),
          child: _shell(StartGate(app: (user, tab) => const ProfileScreen())),
        ),
      );
      // The stored session is restored from the keychain and `/auth/me` asked in the background: real I/O, then the
      // splash's time (it waits for that answer up to the 4 s cap).
      for (var i = 0; i < 5; i++) {
        await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
        await tester.pump();
      }
      await _run(tester, StartMotion.serverCap + const Duration(milliseconds: 400));
      expect(find.byType(StartScreen), findsNothing);
      expect(find.byType(ProfileScreen), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('profile-delete')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('delete-sheet')), findsOneWidget);
      expect(find.text('Удалить аккаунт?'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byKey(const ValueKey('delete-sheet')), matching: find.text('Удалить аккаунт')));
      for (var i = 0; i < 4; i++) {
        await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
        await tester.pump(const Duration(milliseconds: 100));
      }
      await tester.pump(const Duration(milliseconds: 600));

      expect(wire.calls, contains('DELETE /api/v1/auth/me'));
      expect(find.byType(ProfileScreen), findsNothing);
      expect(find.byKey(const ValueKey('sign-in-apple')), findsOneWidget, reason: 'the splash with the sign-in (41-4a)');
      await _drain(tester);
    });
  });
}

Widget _shell(Widget home) => MaterialApp(
  debugShowCheckedModeBanner: false,
  theme: buildAppTheme(),
  locale: const Locale('ru'),
  supportedLocales: const [Locale('ru'), Locale('en')],
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  home: home,
);

Widget _stubApp(AppUser user, int tab) => Scaffold(body: Center(child: Text('app ${user.id} tab $tab')));

SignInButton _button(WidgetTester tester, String key) => tester.widget<SignInButton>(find.byKey(ValueKey(key)));

/// The stored session is read and the splash's clock has had its first tick: from here on, time is the splash's.
Future<void> _begin(WidgetTester tester) async {
  await tester.pump();
  await tester.pump();
}

/// The first launch's splash, played out: the doors are up.
Future<void> _toSignIn(WidgetTester tester) async {
  await _begin(tester);
  await tester.pump(StartMotion.firstLaunch + _frame);
  await _frames(tester);
  await tester.pump(const Duration(milliseconds: 500));
}

/// The check, then the dissolve into what comes next.
Future<void> _toSheets(WidgetTester tester) async {
  await _frames(tester);
  await tester.pump(StartMotion.signedInCheck + _frame);
  await _frames(tester);
  await tester.pump(StartMotion.dissolve + _frame);
  await _frames(tester);
  await tester.pump(const Duration(milliseconds: 600));
}

/// A repeat launch played out into the app.
Future<void> _toApp(WidgetTester tester) async {
  await _begin(tester);
  await tester.pump(StartMotion.repeatLaunch + _frame);
  await _frames(tester);
  await tester.pump(StartMotion.dissolve + _frame);
  await _frames(tester);
}

/// An animation is done on the first frame AFTER its duration — one frame's worth past every mark.
const _frame = Duration(milliseconds: 17);

/// Time passing the way it does on a phone — a frame every 50 ms — so an animation a timer starts midway gets frames.
Future<void> _run(WidgetTester tester, Duration total) async {
  for (var t = Duration.zero; t < total; t += const Duration(milliseconds: 50)) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

/// A few frames at the same moment: a chain of futures that each wake on the frame after the last (the clock's end →
/// the gate → the dissolve) runs out.
Future<void> _frames(WidgetTester tester, [int count = 3]) async {
  for (var i = 0; i < count; i++) {
    await tester.pump();
  }
}

/// The sheets' little loops and the sign-in's timers — let them end with the tree.
Future<void> _drain(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(seconds: 10));
}

/// A stored session whose server never answers.
class _SilentServerAuth extends ScriptedAuth {
  _SilentServerAuth(AppUser user) : super(restored: user);

  @override
  Future<void> get firstAnswer => Completer<void>().future;
}
