import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/deep_links.dart';
import '../../data/device_timezone.dart';
import '../../data/plan/plan_models.dart';
import '../../data/plan/plan_notifications.dart';
import '../../data/plan/plan_reminder_rules.dart';
import '../../data/plan/plan_reminder_scheduler.dart';
import '../../data/plan/push_registration.dart';
import '../../data/providers.dart';
import 'day/open_day.dart';
import 'plan_banner.dart';
import 'plan_providers.dart';

final planNotificationsProvider = Provider<PlanNotifications>(
  (ref) => PlanNotifications(FlutterLocalNotificationsPlugin()),
);

final pushRegistrationProvider = Provider<PushRegistration>((ref) => PushRegistration(ref.watch(apiClientProvider)));

/// Доставляет ли сервер письма плана сам — последний ответ `PUT /devices/push-token` (доработка
/// PLAN-UI-3, п. 2). true — локальных напоминаний нет; false (и нет ответа) — телефон ставит их сам.
class PlanPushEnabled extends AsyncNotifier<bool> {
  @override
  Future<bool> build() => ref.read(planStoreProvider).pushEnabled();

  Future<void> set(bool enabled) async {
    await ref.read(planStoreProvider).setPushEnabled(enabled);
    state = AsyncData(enabled);
  }
}

final planPushEnabledProvider = AsyncNotifierProvider<PlanPushEnabled, bool>(PlanPushEnabled.new);

/// Разрешение на уведомления — ОДИН раз, после «Начать» на превью (наряд PLAN-UI-3 §4), и сразу
/// следом регистрация push-токена. Второй вызов ничего не спрашивает.
Future<void> askPlanNotificationsOnce(WidgetRef ref) async {
  final store = ref.read(planStoreProvider);
  if (await store.notifyPermissionAsked()) return;
  await store.markNotifyPermissionAsked();
  final granted = await ref.read(planNotificationsProvider).requestPermission();
  debugPrint('[plan-notify] permission granted: $granted');
  if (!granted) return;
  await ref.read(pushRegistrationProvider).register(
    timezone: await deviceTimezone(),
    onPushEnabled: (enabled) => ref.read(planPushEnabledProvider.notifier).set(enabled),
  );
}

/// ГДЕ ЖИВУТ УВЕДОМЛЕНИЯ ПЛАНА (наряд PLAN-UI-3 §4, кадр 22-6 — эталон вида).
///
/// Ничего не рисует сам, кроме баннера поверх оболочки. Делает четыре вещи, потому что всё нужное
/// для них есть только в оболочке — локализации, переключатель табов и жизненный цикл приложения:
///
/// 1. на каждом возвращении в приложение — сообщает заход (`POST /devices/visit`, по заходам сервер
///    считает час напоминаний и отдаёт его в плане) и пересчитывает локальное расписание
///    ([PlanReminderScheduler]); то же при каждой смене маршрута и смене `push_enabled` — при true
///    локальных напоминаний нет;
/// 2. «план готов» и «день N собран», случившиеся, пока приложение было свёрнуто, показывает
///    баннером при возврате — push на телефон сегодня не доходит;
/// 3. тап по уведомлению или баннеру — таб «План», прокрученный к нужному дню;
/// 4. ссылки `engstd://plan/day/{id}` — дверь в кабинет (наряд DAY-UI).
class PlanNotificationsHost extends ConsumerStatefulWidget {
  const PlanNotificationsHost({super.key, required this.child, required this.onOpenPlan});

  final Widget child;
  final VoidCallback onOpenPlan;

  @override
  ConsumerState<PlanNotificationsHost> createState() => _PlanNotificationsHostState();
}

class _PlanNotificationsHostState extends ConsumerState<PlanNotificationsHost> with WidgetsBindingObserver {
  /// Приложение вернулось — следующее обновление плана может принести то, что случилось без него.
  DateTime? _resumedAt;
  String? _scheduledFor;
  PlanBannerData? _banner;

  static const _returnWindow = Duration(seconds: 20);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    PlanNotifications.tapped.addListener(_openTapped);
    DeepLinks.pending.addListener(_openLink);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      await ref.read(planNotificationsProvider).init();
      _openTapped();
      unawaited(_openLink());
      unawaited(_visited());
    });
  }

  @override
  void dispose() {
    PlanNotifications.tapped.removeListener(_openTapped);
    DeepLinks.pending.removeListener(_openLink);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) return;
    _resumedAt = DateTime.now();
    unawaited(ref.read(planTabProvider.notifier).refresh());
    unawaited(_visited());
  }

  /// Заход — на сервер: там по семи последним заходам считается час напоминаний.
  Future<void> _visited() async {
    final zone = await deviceTimezone();
    unawaited(ref.read(apiClientProvider).postVisit(timezone: zone).catchError((Object e) => debugPrint('[plan-notify] visit: $e')));
    if (!mounted) return;
    await _reschedule(force: true);
  }

  Future<void> _reschedule({bool force = false}) async {
    final plan = ref.read(planTabProvider).value?.plan;
    final pushEnabled = await ref.read(planPushEnabledProvider.future);
    final signature = [plan?.id, plan?.status, plan?.currentDay?.number, plan?.currentDay?.slot.date, plan?.eventDate, plan?.reminderHour, pushEnabled].join('|');
    if (!force && signature == _scheduledFor) return;
    _scheduledFor = signature;
    if (!mounted) return;
    final l = AppLocalizations.of(context);
    final count = await PlanReminderScheduler(ref.read(planNotificationsProvider)).apply(
      plan: plan,
      pushEnabled: pushEnabled,
      now: DateTime.now(),
      zone: await deviceTimezone(),
      channel: l.planTitle,
      text: (plan, n) => (title: _title(l, plan, n), body: _body(l, plan, n)),
    );
    debugPrint('[plan-notify] push_enabled=$pushEnabled local=$count at ${plan?.reminderHour}:00');
  }

  static String _title(AppLocalizations l, Plan plan, PlanNotice n) => switch (n.kind) {
    PlanNoticeKind.reminder => l.planNotifyReminderTitle(n.dayNumber),
    PlanNoticeKind.skipped => l.planNotifySkippedTitle(n.dayNumber),
    PlanNoticeKind.eventToday => (plan.eventNative ?? '').trim().isEmpty
        ? l.planNotifyEventTodayTitleNoName
        : l.planNotifyEventTodayTitle(_lowerFirst(plan.eventNative!.trim())),
  };

  static String _body(AppLocalizations l, Plan plan, PlanNotice n) => switch (n.kind) {
    PlanNoticeKind.reminder => l.planNotifyReminderBody(n.dayTitle ?? plan.displayTitle),
    PlanNoticeKind.skipped => l.planNotifySkippedBody,
    PlanNoticeKind.eventToday => l.planNotifyEventTodayBody,
  };

  static String _lowerFirst(String s) => s.isEmpty ? s : s[0].toLowerCase() + s.substring(1);

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<PlanTabState>>(planTabProvider, (previous, next) {
      unawaited(_reschedule());
      _bannerOnReturn(previous?.value?.plan, next.value?.plan);
    });
    ref.listen<AsyncValue<bool>>(planPushEnabledProvider, (previous, next) {
      if (previous?.value != next.value) unawaited(_reschedule(force: true));
    });

    return Stack(
      children: [
        widget.child,
        if (_banner != null)
          PlanBanner(
            data: _banner!,
            onTap: () {
              final day = _banner!.dayNumber;
              setState(() => _banner = null);
              _openDayOnTab(day);
            },
            onGone: () => setState(() => _banner = null),
          ),
      ],
    );
  }

  /// «План готов» / «День N собран» — только если это случилось, пока приложения не было: человек,
  /// который смотрит на плиту, видит готовность на ней самой.
  void _bannerOnReturn(Plan? before, Plan? after) {
    final resumed = _resumedAt;
    if (before == null || after == null || before.id != after.id) return;
    if (resumed == null || DateTime.now().difference(resumed) > _returnWindow) return;
    final l = AppLocalizations.of(context);
    for (final d in after.days) {
      final was = before.days.where((b) => b.number == d.number).firstOrNull;
      if (was == null || !was.lessonBuilding || d.lessonBuilding || d.lessonFailed) continue;
      final title = d.titleNative ?? after.sceneOf(d)?.titleNative ?? after.displayTitle;
      setState(
        () => _banner = d.number == 1
            ? PlanBannerData(title: l.planEntryPushTitle, body: _readyBody(l, after, title), dayNumber: 1)
            : PlanBannerData(title: l.planNotifyDayReadyTitle(d.number), body: l.planNotifyDayReadyBody(title), dayNumber: d.number),
      );

      return;
    }
  }

  /// «7 дней до приёма 17 сентября. День 1 — «Запись к врачу»» (22-6): срок строкой сервера.
  static String _readyBody(AppLocalizations l, Plan plan, String dayTitle) {
    final until = (plan.untilPhrase ?? '').trim();

    return until.isEmpty
        ? l.planEntryPushBodyNoDate(l.planDaysCount(plan.daysTotal), dayTitle)
        : l.planEntryPushBody(until, dayTitle);
  }

  void _openTapped() {
    final day = PlanNotifications.tapped.value;
    if (day == null || !mounted) return;
    PlanNotifications.tapped.value = null;
    _openDayOnTab(day);
  }

  /// Таб «План», прокрученный к дню — не кабинет: уведомление зовёт к плану, а не за человека
  /// открывает день.
  void _openDayOnTab(int day) {
    widget.onOpenPlan();
    ref.read(planFocusDayProvider.notifier).focus(day);
  }

  /// ССЫЛКА `engstd://plan/day/{id}` — штатный вход в кабинет дня (наряд DAY-UI). Снимается ПЕРВЫМ
  /// действием: слушатель, сработавший дважды на одном значении, открыл бы два кабинета.
  Future<void> _openLink() async {
    final uri = DeepLinks.pending.value;
    if (uri == null || !mounted) return;
    DeepLinks.pending.value = null;
    await openDayLink(context, ref, uri);
  }
}
