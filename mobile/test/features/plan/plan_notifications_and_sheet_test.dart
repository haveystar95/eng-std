import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/notify_permission.dart';
import 'package:eng_std/data/plan/plan_notifications.dart';
import 'package:eng_std/data/plan/push_registration.dart';
import 'package:eng_std/features/plan/notify_prompt.dart';
import 'package:eng_std/features/plan/plan_notifications_host.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_sheets.dart';
import 'package:eng_std/features/profile/profile_screen.dart';

import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';
import '../../support/start_harness.dart';

/// ПРАВИЛА, КОТОРЫЕ НЕ ВИДНО НА СНИМКЕ — моменты, а не состояния (наряды PLAN-UI, PLAN-UI-3).
void main() {
  setUpAll(setUpPlanGoldens);

  group('43-1 — напоминания: предразрешение после итога дня', () {
    // Ведущая кнопка за итогом дня: как «Дальше» зовёт лист, закрыв день [n].
    Widget host({required FakeNotifyProbe probe, required _CountingNotifications notifications, required _CountingPush push}) =>
        ProviderScope(
          overrides: [
            ...accountOverrides(auth: () => ScriptedAuth(restored: denUser()), probe: probe),
            planNotificationsProvider.overrideWithValue(notifications),
            pushRegistrationProvider.overrideWithValue(push),
          ],
          child: planGoldenShell(
            Scaffold(
              body: Consumer(
                builder: (context, ref, _) => Column(
                  children: [
                    for (final day in [1, 2, 3])
                      TextButton(
                        onPressed: () => offerReminders(context, ref, closedDay: day),
                        child: Text('после дня $day'),
                      ),
                  ],
                ),
              ),
            ),
          ),
        );

    Future<void> closeDay(WidgetTester tester, int day) async {
      await tester.tap(find.text('после дня $day'));
      await tester.pumpAndSettle();
    }

    // The answer runs through platform channels (the time zone for the push address) that answer in real time: a few
    // real waits, each followed by a frame that runs what they woke.
    Future<void> answer(WidgetTester tester, String label) async {
      await tester.tap(find.text(label));
      for (var i = 0; i < 4; i++) {
        await tester.pumpAndSettle();
        await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
      }
      await tester.pumpAndSettle();
    }

    // ПРАВИЛО (наряд CLIENT-START §4, кадр 43-1): лист — после итога дня 1; «Не сейчас» — ещё раз после дня 2, дальше
    // никогда; системный вопрос iOS не задаётся, пока человек не сказал «Напоминать».
    // ЛОВИТ: лист на каждом дне, лист, который после второго «Не сейчас» возвращается, и алерт iOS без согласия.
    testWidgets('«Не сейчас» — ещё раз после дня 2, и больше никогда', (tester) async {
      final notifications = _CountingNotifications();
      final push = _CountingPush();
      await tester.pumpWidget(host(probe: FakeNotifyProbe(), notifications: notifications, push: push));
      await tester.pumpAndSettle();

      await closeDay(tester, 1);
      expect(find.byKey(const ValueKey('notify-ask')), findsOneWidget);
      expect(find.text(nbTypo('Напомнить про день 2 завтра в 19:00?')), findsOneWidget);
      await answer(tester, nbTypo('Не сейчас'));
      expect(find.byKey(const ValueKey('notify-ask')), findsNothing);

      await closeDay(tester, 2);
      expect(find.byKey(const ValueKey('notify-ask')), findsOneWidget, reason: 'второй и последний раз — после дня 2');
      await answer(tester, nbTypo('Не сейчас'));

      await closeDay(tester, 3);
      expect(find.byKey(const ValueKey('notify-ask')), findsNothing, reason: 'после двух «Не сейчас» — никогда');
      expect(notifications.requests, 0, reason: 'системный вопрос — только после «Напоминать»');
      expect(push.registrations, 0);
    });

    // ПРАВИЛО: «Напоминать» открывает системный запрос, и, если разрешили, адрес push регистрируется существующей ручкой
    // (`PUT /devices/push-token`); лист больше не приходит.
    // ЛОВИТ: регистрацию токена без разрешения, повторный алерт iOS на следующем дне.
    testWidgets('«Напоминать» — один системный вопрос, один токен; лист больше не приходит', (tester) async {
      final notifications = _CountingNotifications();
      final push = _CountingPush();
      await tester.pumpWidget(host(probe: FakeNotifyProbe(), notifications: notifications, push: push));
      await tester.pumpAndSettle();

      await closeDay(tester, 1);
      await answer(tester, 'Напоминать');
      expect(notifications.requests, 1);
      expect(push.registrations, 1);

      await closeDay(tester, 2);
      expect(find.byKey(const ValueKey('notify-ask')), findsNothing);
      expect(notifications.requests, 1);
    });

    // ПРАВИЛО: iOS уже ответил (разрешил или запретил) — предразрешение не показывается: спрашивать не о чем.
    // ЛОВИТ: лист «Напомнить?» человеку, у которого уведомления уже включены, или запрещены в Настройках.
    for (final answered in [NotifyPermission.granted, NotifyPermission.denied]) {
      testWidgets('iOS уже ответил (${answered.name}) — листа нет', (tester) async {
        final notifications = _CountingNotifications();
        await tester.pumpWidget(host(probe: FakeNotifyProbe(answered), notifications: notifications, push: _CountingPush()));
        await tester.pumpAndSettle();

        await closeDay(tester, 1);
        expect(find.byKey(const ValueKey('notify-ask')), findsNothing);
        expect(notifications.requests, 0);
      });
    }
  });

  group('выключатель «Напоминать о дне» в профиле (42-1)', () {
    Widget profile(FakeNotifyProbe probe, _CountingNotifications notifications) => ProviderScope(
      overrides: [
        ...accountOverrides(auth: () => ScriptedAuth(restored: denUser()), probe: probe),
        planNotificationsProvider.overrideWithValue(notifications),
        pushRegistrationProvider.overrideWithValue(_CountingPush()),
      ],
      child: planGoldenShell(const ProfileScreen()),
    );

    Future<void> flip(WidgetTester tester) async {
      await tester.runAsync(() async {
        await tester.tap(find.byKey(const ValueKey('profile-reminders')));
        await Future<void>.delayed(const Duration(milliseconds: 200));
      });
      await tester.pumpAndSettle();
    }

    // ПРАВИЛО: выключатель — второе место, где человек сам просит уведомления: iOS не спрашивали — включение поднимает
    // системный вопрос; выключение ничего не спрашивает.
    // ЛОВИТ: выключатель, который включается «на словах», а iOS так и не спросили.
    testWidgets('iOS не спрашивали — включение поднимает системный вопрос, выключение — нет', (tester) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1400) * 2;
      addTearDown(tester.view.reset);
      final notifications = _CountingNotifications();
      final probe = FakeNotifyProbe();
      await tester.pumpWidget(profile(probe, notifications));
      await tester.pumpAndSettle();

      await flip(tester);
      expect(notifications.requests, 1);
      probe.value = NotifyPermission.granted;

      await flip(tester);
      expect(notifications.requests, 1);
    });

    // ПРАВИЛО: iOS запретил — выключатель сам ничего не включит; лист 42-4 говорит, где это меняется.
    // ЛОВИТ: «включённый» выключатель при запрете в Настройках — напоминаний не будет, а экран говорит «будут».
    testWidgets('iOS запретил — выключатель открывает лист, а не включает', (tester) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1400) * 2;
      addTearDown(tester.view.reset);
      final notifications = _CountingNotifications();
      await tester.pumpWidget(profile(FakeNotifyProbe(NotifyPermission.denied), notifications));
      await tester.pumpAndSettle();

      await flip(tester);
      expect(find.byKey(const ValueKey('reminders-sheet')), findsOneWidget);
      expect(notifications.requests, 0);
    });
  });

  group('«Как устроен план» — один раз (кадр 21-8)', () {
    Widget host(PlanHints hints) => planGoldenApp(
      Scaffold(
        body: Consumer(
          builder: (context, ref, _) => TextButton(
            onPressed: () => showPlanHowSheetOnce(context, ref, delay: Duration.zero),
            child: const Text('после «Начать»'),
          ),
        ),
      ),
      hints: hints,
    );

    // ПРАВИЛО: лист объясняет устройство плана ОДИН раз, за первым планом (спека 22-4b).
    // ЛОВИТ: лист, который приходит после каждого «Начать» и превращается в окно, которое
    // закрывают не читая.
    testWidgets('флаг не выставлен — лист показан', (tester) async {
      await tester.pumpWidget(host(const PlanHints(tabShown: false, closeShown: false, howShown: false)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('после «Начать»'));
      await tester.pumpAndSettle();

      expect(find.text('Как устроен план'), findsOneWidget);
    });

    testWidgets('флаг выставлен — листа нет', (tester) async {
      await tester.pumpWidget(host(const PlanHints(tabShown: true, closeShown: true, howShown: true)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('после «Начать»'));
      await tester.pumpAndSettle();

      expect(find.text('Как устроен план'), findsNothing);
    });
  });
}

/// Уведомления, которые ничего не делают и считают вопросы о разрешении; разрешение дают.
class _CountingNotifications implements PlanNotifications {
  int requests = 0;

  @override
  Future<bool> requestPermission() async {
    requests++;

    return true;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _CountingPush implements PushRegistration {
  int registrations = 0;

  @override
  Future<void> register({String? locale, String? timezone, required Future<void> Function(bool enabled) onPushEnabled}) async =>
      registrations++;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
