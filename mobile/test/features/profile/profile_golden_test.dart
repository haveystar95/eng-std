import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/notify_permission.dart';
import 'package:eng_std/data/start/account_device_store.dart';
import 'package:eng_std/features/profile/profile_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/plan_goldens.dart';
import '../../support/start_harness.dart';

/// THE PROFILE AND ITS SHEETS AS GOLDENS (work order CLIENT-START, «Golden»): 42-1a free (top and scrolled), 42-1b
/// Premium, 42-1 in English, the name sheet 42-2 (a name · empty), the deletion 42-3 (asked · deleting), the reminders
/// sheet 42-4. The
/// frames stand under the canvas's 52 status bar; the reminders are on, as the canvas draws them (iOS allowed them).
/// The canvas's «Подписка ›» row under «Бесплатно» is not drawn until PAY-1 brings the paywall it opens.
///
/// ```bash
/// flutter test --update-goldens test/features/profile/profile_golden_test.dart
/// ```
/// The PNGs — `test/goldens/profile/`, named by the frame.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget profile({
    AppUser? user,
    SignInDoor door = SignInDoor.apple,
    Locale locale = const Locale('ru'),
    ScriptedAuth? auth,
  }) {
    final account = user ?? denUser();
    return ProviderScope(
      overrides: accountOverrides(
        auth: () => auth ?? ScriptedAuth(restored: account),
        keychain: MemoryKeyValue({'door:${account.id}': door.name}),
        probe: FakeNotifyProbe(NotifyPermission.granted),
      ),
      // The app's shell with the view's own insets (the golden shell's bare MediaQuery would drop the status bar).
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: buildAppTheme(),
        locale: locale,
        supportedLocales: const [Locale('ru'), Locale('en')],
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
        home: const ProfileScreen(pushed: true),
      ),
    );
  }

  Future<void> shoot(WidgetTester tester, Widget app, String name, {Future<void> Function(WidgetTester)? prime}) {
    tester.view.padding = const FakeViewPadding(top: 52 * kGoldenDpr, bottom: 34 * kGoldenDpr);
    addTearDown(tester.view.resetPadding);
    return expectPlanGolden(
      tester,
      app,
      'profile/$name',
      prime: (tester) async {
        await tester.pumpAndSettle();
        await prime?.call(tester);
      },
    );
  }

  Future<void> sheet(WidgetTester tester, String row) async {
    await tester.tap(find.byKey(ValueKey(row)));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
  }

  testWidgets('42-1a бесплатный — верх', (tester) async {
    await shoot(tester, profile(), '42-1a');
  });

  testWidgets('42-1a бесплатный — прокручен до версии', (tester) async {
    await shoot(
      tester,
      profile(),
      '42-1a-scrolled',
      prime: (tester) async {
        await tester.drag(find.byKey(const ValueKey('profile-list')), const Offset(0, -1200));
        await tester.pumpAndSettle();
      },
    );
  });

  testWidgets('42-1b Premium — «продлится 25 октября», Google', (tester) async {
    await shoot(tester, profile(user: denUser(premium: true, until: DateTime(2026, 10, 25)), door: SignInDoor.google), '42-1b');
  });

  testWidgets('42-1 en — the profile in English', (tester) async {
    final den = AppUser(
      id: denUser().id,
      name: 'Den',
      profile: denUser().profile,
      access: denUser().access,
    );
    await shoot(tester, profile(user: den, locale: const Locale('en')), '42-1-en');
  });

  // On iOS, as the phone draws it: a bare caret in the focused field, no Android selection handle under it.
  testWidgets('42-2 имя — поле и «Готово»', (tester) async {
    await shoot(tester, profile(), '42-2-name', prime: (tester) => sheet(tester, 'profile-header'));
  }, variant: TargetPlatformVariant.only(TargetPlatform.iOS));

  // The field emptied: no label over it — the sheet's title is «Имя» — and the placeholder says what goes there
  // (доработка CLIENT-START п. 3).
  testWidgets('42-2 имя — пустое поле: плейсхолдер «Как тебя зовут»', (tester) async {
    await shoot(
      tester,
      profile(),
      '42-2-name-empty',
      prime: (tester) async {
        await sheet(tester, 'profile-header');
        await tester.enterText(find.byKey(const ValueKey('name-field')), '');
        await tester.pump();
      },
    );
  }, variant: TargetPlatformVariant.only(TargetPlatform.iOS));

  testWidgets('42-3a удалить аккаунт — вопрос', (tester) async {
    await shoot(
      tester,
      profile(),
      '42-3a-delete',
      prime: (tester) async {
        await tester.drag(find.byKey(const ValueKey('profile-list')), const Offset(0, -1200));
        await tester.pumpAndSettle();
        await sheet(tester, 'profile-delete');
      },
    );
  });

  testWidgets('42-3b «Удаляем…» — обе двери притихли', (tester) async {
    final hold = Completer<void>();
    addTearDown(() {
      if (!hold.isCompleted) hold.complete();
    });
    await shoot(
      tester,
      profile(auth: ScriptedAuth(restored: denUser(), deleteHold: hold)),
      '42-3b-deleting',
      prime: (tester) async {
        await tester.drag(find.byKey(const ValueKey('profile-list')), const Offset(0, -1200));
        await tester.pumpAndSettle();
        await sheet(tester, 'profile-delete');
        await tester.tap(find.descendant(of: find.byKey(const ValueKey('delete-sheet')), matching: find.text('Удалить аккаунт')));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 300));
      },
    );
  });

  testWidgets('42-4 напоминания — выключатель, колесо, «Готово»', (tester) async {
    await shoot(tester, profile(), '42-4-reminders', prime: (tester) => sheet(tester, 'profile-time'));
  });
}
