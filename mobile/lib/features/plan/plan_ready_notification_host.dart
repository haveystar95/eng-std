import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/plan/plan_ready_notification.dart';
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
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _openTapped();
    });
  }

  @override
  void dispose() {
    PlanReadyNotification.tapped.removeListener(_openTapped);
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
          body: l.planEntryPushBody(after.titleNative ?? plan.displayTitle),
          channel: (name: l.planTitle, description: l.planEntryPushTitle),
        ),
      );
    });

    return widget.child;
  }

  void _openTapped() {
    if (PlanReadyNotification.tapped.value != PlanReadyNotification.payload || !mounted) return;
    PlanReadyNotification.tapped.value = null;
    widget.onOpenPlan();
  }
}
