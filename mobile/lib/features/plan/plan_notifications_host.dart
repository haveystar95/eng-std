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
import '../../data/plan/push_registration.dart';
import '../../data/providers.dart';
import 'day/open_day.dart';
import 'plan_banner.dart';
import 'plan_providers.dart';

final planNotificationsProvider = Provider<PlanNotifications>(
  (ref) => PlanNotifications(FlutterLocalNotificationsPlugin()),
);

final pushRegistrationProvider = Provider<PushRegistration>((ref) => PushRegistration(ref.watch(apiClientProvider)));

/// Разрешение на уведомления — ОДИН раз, после «Начать» на превью (наряд PLAN-UI-3 §4), и сразу
/// следом регистрация push-токена. Второй вызов ничего не спрашивает.
Future<void> askPlanNotificationsOnce(WidgetRef ref) async {
  final store = ref.read(planStoreProvider);
  if (await store.notifyPermissionAsked()) return;
  await store.markNotifyPermissionAsked();
  final granted = await ref.read(planNotificationsProvider).requestPermission();
  debugPrint('[plan-notify] permission granted: $granted');
  if (!granted) return;
  await ref.read(pushRegistrationProvider).register(timezone: await deviceTimezone());
}

/// ГДЕ ЖИВУТ УВЕДОМЛЕНИЯ ПЛАНА (наряд PLAN-UI-3 §4, кадр 22-6 — эталон вида).
///
/// Ничего не рисует сам, кроме баннера поверх оболочки. Делает четыре вещи, потому что всё нужное
/// для них есть только в оболочке — локализации, переключатель табов и жизненный цикл приложения:
///
/// 1. на каждом возвращении в приложение — записывает заход (у себя и `POST /devices/visit`) и
///    пересчитывает локальное расписание по датам плана (`planLocalNotices`); то же при каждой
///    смене маршрута;
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

  /// Заход: у себя (для часа напоминания) и на сервере (для push после ключа).
  Future<void> _visited() async {
    final store = ref.read(planStoreProvider);
    final visits = await store.recordVisit(DateTime.now());
    final zone = await deviceTimezone();
    unawaited(ref.read(apiClientProvider).postVisit(timezone: zone).catchError((Object e) => debugPrint('[plan-notify] visit: $e')));
    if (!mounted) return;
    await _reschedule(ref.read(planTabProvider).value?.plan, usualVisitTime(visits), zone, force: true);
  }

  Future<void> _reschedule(Plan? plan, VisitTime at, String zone, {bool force = false}) async {
    final signature = [plan?.id, plan?.status, plan?.currentDay?.number, plan?.currentDay?.slot.date, plan?.eventDate, at].join('|');
    if (!force && signature == _scheduledFor) return;
    _scheduledFor = signature;
    if (!mounted) return;
    final l = AppLocalizations.of(context);
    final notices = planLocalNotices(plan, DateTime.now(), at);
    await ref.read(planNotificationsProvider).replaceScheduled(
      [
        for (final n in notices)
          (at: n.at, dayNumber: n.dayNumber, title: _title(l, plan!, n), body: _body(l, plan, n)),
      ],
      channel: l.planTitle,
      zone: zone,
    );
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
      final plan = next.value?.plan;
      unawaited(() async {
        final visits = await ref.read(planStoreProvider).visits();
        await _reschedule(plan, usualVisitTime(visits), await deviceTimezone());
      }());
      _bannerOnReturn(previous?.value?.plan, plan);
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
