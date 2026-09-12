import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/deep_links.dart';
import '../../data/plan/plan_models.dart';
import '../../data/plan/plan_ready_notification.dart';
import 'day/open_day.dart';
import 'plan_providers.dart';

final planReadyNotificationProvider = Provider<PlanReadyNotification>(
  (ref) => PlanReadyNotification(FlutterLocalNotificationsPlugin()),
);

/// WHERE «ПЛАН ГОТОВ» IS SENT AND WHERE ITS TAP LANDS (кадр 22-6).
///
/// It draws nothing. It sits in the tab shell because the two things it needs live there: the
/// localisations for the text, and the shell's own tab switch for the tap. The rule it enforces is
/// the frame's: the notification goes out ONLY if the app was put away while day one was being
/// written — a person looking at the shimmer sees it turn into the plate and needs no push.
class PlanReadyNotificationHost extends ConsumerStatefulWidget {
  const PlanReadyNotificationHost({super.key, required this.child, required this.onOpenPlan});

  final Widget child;
  final VoidCallback onOpenPlan;

  @override
  ConsumerState<PlanReadyNotificationHost> createState() => _PlanReadyNotificationHostState();
}

class _PlanReadyNotificationHostState extends ConsumerState<PlanReadyNotificationHost>
    with WidgetsBindingObserver {
  bool _inForeground = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    PlanReadyNotification.tapped.addListener(_openTapped);
    DeepLinks.pending.addListener(_openLink);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _openTapped();
      // Ссылка холодного старта уже лежит в [DeepLinks.pending] — её некому было забрать, пока
      // оболочка строилась.
      unawaited(_openLink());
    });
  }

  @override
  void dispose() {
    PlanReadyNotification.tapped.removeListener(_openTapped);
    DeepLinks.pending.removeListener(_openLink);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _inForeground = state == AppLifecycleState.resumed;
    // Back from the background: the poll may have stopped with the app; ask again now.
    if (_inForeground) unawaited(ref.read(planTabProvider.notifier).refresh());
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<PlanTabState>>(planTabProvider, (previous, next) {
      final before = previous?.value?.focusDay;
      final after = next.value?.focusDay;
      final plan = next.value?.plan;
      if (plan == null || before == null || after == null) return;
      final becameReady =
          before.number == 1 && before.lessonBuilding && after.number == 1 && !after.lessonBuilding && !after.lessonFailed;
      if (!becameReady || _inForeground) return;
      final l = AppLocalizations.of(context);
      unawaited(
        ref.read(planReadyNotificationProvider).show(
          title: l.planEntryPushTitle,
          // «7 дней до приёма 17 сентября. День 1 — «Запись к врачу»» (кадр 22-6): срок до
          // события берётся ГОТОВОЙ строкой сервера (`until_phrase`) — ни числа, ни склонения
          // события клиент здесь не выводит. «≈ 20 минут» из кадра нет: оценки минут у дня
          // контракт не отдаёт.
          body: _pushBody(l, plan, after.titleNative ?? plan.displayTitle),
          channel: (name: l.planTitle, description: l.planEntryPushTitle),
        ),
      );
    });

    return widget.child;
  }

  /// Тело уведомления: срок до события строкой сервера, иначе — длина плана.
  String _pushBody(AppLocalizations l, Plan plan, String dayTitle) {
    final until = (plan.untilPhrase ?? '').trim();

    return until.isEmpty
        ? l.planEntryPushBodyNoDate(l.planDaysCount(plan.daysTotal), dayTitle)
        : l.planEntryPushBody(until, dayTitle);
  }

  void _openTapped() {
    if (PlanReadyNotification.tapped.value != PlanReadyNotification.payload || !mounted) return;
    PlanReadyNotification.tapped.value = null;
    widget.onOpenPlan();
  }

  /// ССЫЛКА `engstd://plan/day/{id}` — штатный вход в кабинет дня (наряд DAY-UI): её шлёт
  /// `SceneDelegate`, ею же ходит QA-прогон (`xcrun simctl openurl`). Слушает этот хост, потому
  /// что он и так живёт в оболочке таба и уже держит навигатор.
  ///
  /// Ссылка снимается ПЕРВЫМ действием: слушатель, сработавший дважды на одном значении, открыл
  /// бы два кабинета.
  Future<void> _openLink() async {
    final uri = DeepLinks.pending.value;
    if (uri == null || !mounted) return;
    DeepLinks.pending.value = null;
    await openDayLink(context, ref, uri);
  }
}
