import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/plan_ready_notification.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_ready_notification_host.dart';
import 'package:eng_std/features/plan/plan_sheets.dart';
import 'package:eng_std/features/profile/profile_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../support/plan_goldens.dart';

/// ДВА ПРАВИЛА, КОТОРЫЕ НЕ ВИДНО НА СНИМКЕ (наряд PLAN-UI, доработка).
///
/// Системное окно и одноразовый лист — это МОМЕНТЫ, а не состояния: снимок их не ловит, а живой
/// прогон ловит по одному разу за вечер. Поэтому они закреплены здесь.
void main() {
  setUpAll(setUpPlanGoldens);

  group('разрешение на уведомления просит только явное действие', () {
    // ПРАВИЛО: после «Начать» человек попадает на таб со своим планом — без системных окон
    // (кадры 22-5a/22-6 такого момента не рисуют).
    // ЛОВИТ: возврат старого поведения, когда разрешение просилось прямо в `_start` и алерт iOS
    // всплывал поверх только что собранного плана. Живьём это ловится один раз на устройство —
    // разрешение спрашивают единожды, и второй запуск уже ничего не показывает.
    testWidgets('«Начать» не поднимает системный запрос', (tester) async {
      final notifications = _CountingNotifications();
      final plan = planFrom('plan_ready_preview');
      await tester.pumpWidget(
        planGoldenApp(
          ProviderScope(
            overrides: [
              apiClientProvider.overrideWithValue(_EntryApi()),
              connectivityProvider.overrideWith((ref) => Stream.value(true)),
              planReadyNotificationProvider.overrideWithValue(notifications),
              planTabProvider.overrideWith(() => _StartingTab(plan)),
            ],
            child: const PlanEntryScreen(),
          ),
        ),
      );
      await tester.pump();
      // Цель историей → «Далее» ×3 → «Собрать план» → готовое превью → «Начать».
      await tester.tap(find.text('Звонок арендодателю про залог'));
      await tester.pump();
      for (var i = 0; i < 3; i++) {
        await tester.tap(find.text('Далее'));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 200));
      }
      await tester.tap(find.text('Собрать план'));
      await tester.pump();
      await tester.pump(const Duration(seconds: 1));
      await tester.tap(find.text('Начать'));
      await tester.pump();
      await tester.pump(const Duration(seconds: 1));

      expect(notifications.requests, 0);
    });

    // ПРАВИЛО: разрешение поднимает выключатель «Напоминания» в профиле — человек сам сказал, что
    // хочет их получать.
    // ЛОВИТ: тихое приложение, которое никогда не спросит разрешения и потому никогда не пришлёт
    // «План готов»: убрав запрос из `_start`, его легко не поставить никуда.
    testWidgets('включение «Напоминаний» в профиле — поднимает', (tester) async {
      final notifications = _CountingNotifications();
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            appDatabaseProvider.overrideWith((ref) {
              final db = AppDatabase.forTesting(NativeDatabase.memory());
              ref.onDispose(db.close);

              return db;
            }),
            authControllerProvider.overrideWith(_ProfileAuth.new),
            planReadyNotificationProvider.overrideWithValue(notifications),
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

      // Выключение ничего не спрашивает: отозвать разрешение можно только в настройках телефона.
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
    // ЛОВИТ: лист, который приходит после каждого «Начать» — и превращается в окно, которое
    // закрывают не читая.
    testWidgets('флаг не выставлен — лист показан', (tester) async {
      await tester.pumpWidget(
        host(const PlanHints(tabShown: false, closeShown: false, howShown: false)),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('после «Начать»'));
      await tester.pumpAndSettle();

      expect(find.text('Как устроен план'), findsOneWidget);
    });

    testWidgets('флаг выставлен — листа нет', (tester) async {
      await tester.pumpWidget(
        host(const PlanHints(tabShown: true, closeShown: true, howShown: true)),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('после «Начать»'));
      await tester.pumpAndSettle();

      expect(find.text('Как устроен план'), findsNothing);
    });
  });
}

/// Уведомления, которые ничего не делают и считают, сколько раз у них просили разрешение.
class _CountingNotifications implements PlanReadyNotification {
  int requests = 0;

  @override
  Future<void> requestPermission() async => requests++;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// Вход: сборка сразу готова, план — снятая фикстура.
class _EntryApi implements ApiClient {
  @override
  Future<PlanBuild> createPlan({
    required String goalText,
    required String targetLang,
    required PlanLevel level,
    required int daysTotal,
    String? eventDate,
  }) async => PlanBuild.fromJson({...planFixture('build_ready'), 'status': 'ready'});

  @override
  Future<Plan> plan(String planId) async => planFrom('plan_ready_preview');

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// Таб, который принимает запуск плана, не трогая ни сеть, ни базу.
class _StartingTab extends PlanTabController {
  _StartingTab(this._plan);

  final Plan _plan;

  @override
  Future<PlanTabState> build() async => PlanTabState(plan: null, finished: const []);

  @override
  Future<void> refresh({bool silent = true}) async {}

  @override
  Future<Plan> start(String planId) async => _plan;
}

class _ProfileAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
    id: 'u1',
    name: 'Денис',
    profile: Profile(
      nativeLanguage: 'ru',
      targetLanguage: 'en',
      cefrLevel: 'B1',
      dailyGoal: 20,
    ),
  );
}
