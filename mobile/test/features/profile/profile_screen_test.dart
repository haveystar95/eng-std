import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_settings.dart';
import 'package:eng_std/data/config.dart';
import 'package:eng_std/data/locale_controller.dart';
import 'package:eng_std/data/plan/notify_permission.dart';
import 'package:eng_std/data/start/account_device_store.dart';
import 'package:eng_std/features/profile/account_providers.dart';
import 'package:eng_std/features/profile/profile_screen.dart';

import '../../support/plan_goldens.dart';
import '../../support/start_harness.dart';

/// THE PROFILE ON THE CANON (frames 42-1a, 42-1b, 42-2, 42-3, 42-4; work order CLIENT-START §5): what each row says and
/// where each row leads. The voice row is held by `voice_gender_test.dart`, the native language by
/// `plan_languages_test.dart`, the reminders switch by `plan_notifications_and_sheet_test.dart`, the deletion's wire
/// by `start_flow_test.dart`.
void main() {
  setUpAll(setUpPlanGoldens);

  Future<({ScriptedAuth auth, RecordingLinks links, MemoryKeyValue keychain, ProviderContainer container})> open(
    WidgetTester tester, {
    bool premium = false,
    DateTime? until,
    Map<String, String>? keychain,
    FakeNotifyProbe? probe,
  }) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = const Size(390, 1400) * 2;
    addTearDown(tester.view.reset);
    final links = RecordingLinks();
    final kv = MemoryKeyValue(keychain ?? {'door:${denUser().id}': 'apple'});
    late ScriptedAuth auth;
    await tester.pumpWidget(
      ProviderScope(
        overrides: accountOverrides(
          auth: () => auth = ScriptedAuth(restored: denUser(premium: premium, until: until)),
          keychain: kv,
          links: links,
          probe: probe,
        ),
        child: planGoldenShell(const ProfileScreen(pushed: true)),
      ),
    );
    await tester.pumpAndSettle();
    final container = ProviderScope.containerOf(tester.element(find.byType(ProfileScreen)));
    return (auth: auth, links: links, keychain: kv, container: container);
  }

  Finder inRow(String key, String text) => find.descendant(of: find.byKey(ValueKey(key)), matching: find.text(text));

  // ЛОВИТ: профиль старого вида — «кто ты», статистика, строка сборки вместо версии; группы не в том порядке.
  testWidgets('42-1a бесплатный: буква и имя, «Вход через Apple», четыре группы, «Выйти», «Удалить», версия', (tester) async {
    await open(tester);

    expect(find.text('Д'), findsOneWidget, reason: 'the avatar is the name\'s first letter');
    expect(find.text('Ден'), findsOneWidget);
    expect(find.text('Вход через Apple'), findsOneWidget);
    expect(inRow('profile-plan', 'Бесплатно'), findsOneWidget);
    expect(inRow('profile-plan', 'один план, день 1'), findsOneWidget);
    expect(find.byKey(const ValueKey('profile-manage')), findsNothing, reason: 'nothing to manage on the free plan');

    final groups = ['ПОДПИСКА', 'ОБУЧЕНИЕ', 'НАПОМИНАНИЯ', 'ПРИЛОЖЕНИЕ'];
    final ys = [for (final g in groups) tester.getTopLeft(find.text(g)).dy];
    expect(ys, orderedEquals([...ys]..sort()), reason: 'the groups stand in the canvas\'s order');
    for (final (key, label) in [
      ('profile-voice', 'Голос ученика'),
      ('profile-sounds', 'Звуки в сессии'),
      ('profile-ui-language', 'Язык интерфейса'),
      ('profile-native', 'Родной язык'),
      ('profile-reminders', 'Напоминать о дне'),
      ('profile-time', 'Время'),
      ('profile-terms', 'Правила'),
      ('profile-privacy', 'Конфиденциальность'),
      ('profile-support', 'Поддержка'),
      ('profile-rate', 'Оценить Ritora'),
    ]) {
      expect(inRow(key, label), findsOneWidget, reason: key);
    }
    expect(inRow('profile-time', '19:00'), findsOneWidget, reason: 'no plan, no choice — 19:00');
    // Both languages in one case — the server's endonyms («Русский», «English»).
    expect(inRow('profile-ui-language', 'Русский'), findsOneWidget);
    expect(inRow('profile-native', 'Русский'), findsOneWidget);
    expect(find.text('Выйти'), findsOneWidget);
    expect(find.text('Удалить аккаунт'), findsOneWidget);
    expect(find.text('1.0.0 (22)'), findsOneWidget);
    expect(find.textContaining('Кто ты'), findsNothing);
    expect(find.textContaining('клиент'), findsNothing, reason: 'the build line lives behind the dev door only');
  });

  // ЛОВИТ: Premium без даты, «Управлять подпиской» не в App Store, «Восстановить покупки», которое ничего не спрашивает.
  testWidgets('42-1b Premium: «продлится 12 октября», App Store, «Восстановить покупки» перечитывает права', (tester) async {
    final s = await open(tester, premium: true, until: DateTime(2026, 10, 12));

    expect(inRow('profile-plan', 'Premium'), findsOneWidget);
    expect(inRow('profile-plan', 'продлится 12 октября'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('profile-manage')));
    await tester.pump();
    expect(s.links.opened, [kAppStoreSubscriptions]);

    await tester.tap(find.byKey(const ValueKey('profile-restore')));
    await tester.pumpAndSettle();
    expect(s.auth.refreshCalls, 1);
  });

  testWidgets('Premium без даты — «бессрочно»', (tester) async {
    await open(tester, premium: true);
    expect(inRow('profile-plan', 'бессрочно'), findsOneWidget);
  });

  // ЛОВИТ: строки, которые никуда не ведут, или ведут не туда.
  testWidgets('«Правила», «Конфиденциальность», «Поддержка», «Оценить» — наружу, каждая своим путём', (tester) async {
    final s = await open(tester);

    await tester.tap(find.byKey(const ValueKey('profile-terms')));
    await tester.tap(find.byKey(const ValueKey('profile-privacy')));
    await tester.tap(find.byKey(const ValueKey('profile-support')));
    await tester.tap(find.byKey(const ValueKey('profile-rate')));
    await tester.pump();

    expect(s.links.opened, [AppConfig.termsUrl, AppConfig.privacyUrl]);
    expect(s.links.letters, 1);
    expect(s.links.ratings, 1);
  });

  // ЛОВИТ: имя, которое не сохраняется, или сохраняется поверх другого аккаунта на телефоне.
  testWidgets('42-2 имя: тап по имени — лист, «Готово» — новое имя и буква', (tester) async {
    final s = await open(tester);

    await tester.tap(find.byKey(const ValueKey('profile-header')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('name-sheet')), findsOneWidget);
    expect(find.text('Имя'), findsOneWidget, reason: 'the sheet\'s title, and no label over the field');

    // Empty — the placeholder says what goes there.
    await tester.enterText(find.byKey(const ValueKey('name-field')), '');
    await tester.pump();
    expect(find.text('Как тебя зовут'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('name-field')), 'Мила');
    await tester.tap(find.byKey(const ValueKey('name-done')));
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('name-sheet')), findsNothing);
    expect(find.text('Мила'), findsOneWidget);
    expect(find.text('М'), findsOneWidget);
    expect(s.keychain.values['display_name:${denUser().id}'], 'Мила');
  });

  // ЛОВИТ: выключатель звуков, который ничего не меняет.
  testWidgets('«Звуки в сессии» — выключатель', (tester) async {
    final s = await open(tester);
    expect((await s.container.read(appSettingsProvider.future)).sessionSoundsEnabled, isTrue);

    await tester.tap(find.byKey(const ValueKey('profile-sounds')));
    await tester.pumpAndSettle();
    expect(s.container.read(appSettingsProvider).value?.sessionSoundsEnabled, isFalse);
  });

  // ЛОВИТ: «Язык интерфейса» с третьим вариантом «Системный», языки в разном регистре («русский» рядом с «English» и
  // эндонимом «Русский» родного языка) и выбор, который не сохраняется.
  testWidgets('«Язык интерфейса» — лист из двух, выбор сохраняется', (tester) async {
    final s = await open(tester);

    await tester.tap(find.byKey(const ValueKey('profile-ui-language')));
    await tester.pumpAndSettle();
    final sheet = find.byKey(const ValueKey('ui-language-sheet'));
    expect(sheet, findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.text('Русский')), findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.text('English')), findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.textContaining('Системный')), findsNothing);

    await tester.tap(find.descendant(of: sheet, matching: find.text('English')));
    await tester.pumpAndSettle();
    expect(s.container.read(localeControllerProvider).value, UiLanguageOption.english);
  });

  // ЛОВИТ: колесо, которое не сохраняет время, и лист без выключателя.
  testWidgets('42-4 «Время» — лист с выключателем и колесом; «Готово» сохраняет время', (tester) async {
    final s = await open(tester, probe: FakeNotifyProbe(NotifyPermission.granted));
    expect(find.byKey(const ValueKey('profile-reminders')), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('profile-time')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('reminders-sheet')), findsOneWidget);
    expect(find.byKey(const ValueKey('reminders-switch')), findsOneWidget);
    expect(find.byKey(const ValueKey('reminders-wheel')), findsOneWidget);
    expect(find.text('Мы напоминаем раз в день, когда ждёт следующий день плана'), findsOneWidget);

    // One hour up on the hours wheel (the first of the two): 19 → 20 — the wheel turned the way a finger turns it,
    // by its controller (a drag's first 20 px are the gesture's slop, not the wheel's).
    final hours = find.descendant(of: find.byKey(const ValueKey('reminders-wheel')), matching: find.byType(ListWheelScrollView)).first;
    (tester.widget<ListWheelScrollView>(hours).controller! as FixedExtentScrollController).jumpToItem(20);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('reminders-done')));
    await tester.pumpAndSettle();

    expect(s.container.read(appSettingsProvider).value?.reminderTime, '20:00');
    expect(inRow('profile-time', '20:00'), findsOneWidget);
  });

  // ЛОВИТ: колесо, которое крутится при выключенных напоминаниях (42-4: «серое и не крутится»).
  testWidgets('42-4 напоминания выключены — колесо не крутится, время не меняется', (tester) async {
    final s = await open(tester, probe: FakeNotifyProbe(NotifyPermission.granted));
    await s.container.read(appSettingsProvider.notifier).setReminders(false);
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('profile-time')));
    await tester.pumpAndSettle();
    final hours = find.descendant(of: find.byKey(const ValueKey('reminders-wheel')), matching: find.byType(ListWheelScrollView)).first;
    // A long drag — one that turns a live wheel five hours on — must not move this one.
    await tester.drag(hours, const Offset(0, -200), warnIfMissed: false);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('reminders-done')));
    await tester.pumpAndSettle();

    expect(s.container.read(appSettingsProvider).value?.reminderTime, isNull);
  });

  // ЛОВИТ: «Выйти», которое не выходит.
  testWidgets('«Выйти» — выход', (tester) async {
    final s = await open(tester);
    await tester.tap(find.byKey(const ValueKey('profile-sign-out')));
    await tester.pump();
    expect(s.auth.signOutCalls, 1);
    await tester.pumpAndSettle();
  });

  // ЛОВИТ: удаление без подтверждения, «Отмена», которая удаляет, лист, который закрывается посреди удаления.
  testWidgets('42-3: «Отмена» ничего не делает; «Удалить аккаунт» — удаляет', (tester) async {
    final s = await open(tester);

    await tester.tap(find.byKey(const ValueKey('profile-delete')));
    await tester.pumpAndSettle();
    final sheet = find.byKey(const ValueKey('delete-sheet'));
    expect(sheet, findsOneWidget);
    expect(find.text('Удалить аккаунт?'), findsOneWidget);
    expect(find.text('Исчезнут пройденные дни и настройки. Восстановить их будет нельзя.'), findsOneWidget);
    expect(find.text('Подписку отмени в App Store'), findsOneWidget);

    await tester.tap(find.descendant(of: sheet, matching: find.text('Отмена')));
    await tester.pumpAndSettle();
    expect(sheet, findsNothing);
    expect(s.auth.deleteCalls, 0);

    await tester.tap(find.byKey(const ValueKey('profile-delete')));
    await tester.pumpAndSettle();
    await tester.tap(find.descendant(of: find.byKey(const ValueKey('delete-sheet')), matching: find.text('Удалить аккаунт')));
    await tester.pumpAndSettle();
    expect(s.auth.deleteCalls, 1);
  });

  // ЛОВИТ: «Вход через …» у аккаунта, про дверь которого телефон ничего не знает (восстановлен сборкой без двери).
  testWidgets('дверь неизвестна — строки «Вход через …» нет', (tester) async {
    await open(tester, keychain: {});
    expect(find.textContaining('Вход через'), findsNothing);
  });
}
