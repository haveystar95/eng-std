import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/plan_notifications.dart';
import 'package:eng_std/data/plan/push_registration.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_notifications_host.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_sheets.dart';
import 'package:eng_std/features/profile/profile_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../support/plan_goldens.dart';

/// ПРАВИЛА, КОТОРЫЕ НЕ ВИДНО НА СНИМКЕ — моменты, а не состояния (наряды PLAN-UI, PLAN-UI-3).
void main() {
  setUpAll(setUpPlanGoldens);

  AppDatabase memoryDb(Ref ref) {
    final db = AppDatabase.forTesting(NativeDatabase.memory());
    ref.onDispose(db.close);

    return db;
  }

  group('разрешение на уведомления — один раз, после «Начать»', () {
    // ПРАВИЛО (наряд PLAN-UI-3 §4): системный вопрос задаётся ОДИН раз — после «Начать» на
    // превью, — и следом регистрируется push-токен.
    // ЛОВИТ: вопрос на каждом новом плане (второй «Начать» снова поднимает алерт iOS) и
    // регистрацию токена без разрешения или без вопроса вовсе.
    testWidgets('второй «Начать» не спрашивает снова, токен регистрируется один раз', (tester) async {
      final notifications = _CountingNotifications();
      final push = _CountingPush();
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            appDatabaseProvider.overrideWith(memoryDb),
            planNotificationsProvider.overrideWithValue(notifications),
            pushRegistrationProvider.overrideWithValue(push),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: Consumer(
                builder: (context, ref, _) =>
                    TextButton(onPressed: () => askPlanNotificationsOnce(ref), child: const Text('после «Начать»')),
              ),
            ),
          ),
        ),
      );
      for (var i = 0; i < 2; i++) {
        await tester.runAsync(() async {
          await tester.tap(find.text('после «Начать»'));
          await Future<void>.delayed(const Duration(milliseconds: 300));
        });
        await tester.pump();
      }

      expect(notifications.requests, 1, reason: 'системный вопрос — один раз на телефон');
      expect(push.registrations, 1, reason: 'токен — сразу после разрешения, один раз');
    });

    // ПРАВИЛО: выключатель «Напоминания» в профиле — второе место, где человек сам просит
    // уведомления; выключение ничего не спрашивает.
    // ЛОВИТ: выключатель, который перестал поднимать разрешение после переезда на новый хост.
    testWidgets('включение «Напоминаний» в профиле — поднимает', (tester) async {
      final notifications = _CountingNotifications();
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            appDatabaseProvider.overrideWith(memoryDb),
            authControllerProvider.overrideWith(_ProfileAuth.new),
            planNotificationsProvider.overrideWithValue(notifications),
          ],
          child: const MaterialApp(
            locale: Locale('ru'),
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: [Locale('ru')],
            home: ProfileScreen(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byType(Switch).first);
      await tester.pumpAndSettle();
      expect(notifications.requests, 1);

      await tester.tap(find.byType(Switch).first);
      await tester.pumpAndSettle();
      expect(notifications.requests, 1);
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

class _ProfileAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
    id: 'u1',
    name: 'Денис',
    profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
  );
}
