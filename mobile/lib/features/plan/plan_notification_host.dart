import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/plan_models.dart';
import '../../data/plan_notifications.dart';
import '../../data/providers.dart';
import 'plan_feedback_screen.dart';
import 'plan_rehearsal_screen.dart';
import 'plan_screen.dart';

/// WHERE THE PLAN'S NOTIFICATIONS ARE WRITTEN AND WHERE A TAP ON ONE LANDS.
///
/// It draws nothing. It sits inside the tab shell for two reasons that both come down to context:
/// the notification TEXTS are UI copy and need `AppLocalizations`, and a tap has to push a screen,
/// which needs a Navigator. A service constructed at app start has neither.
///
/// Scheduling is a pure function of the active plan, re-run on every change of it. There is no
/// «did anything change» check: the alternative is a second copy of the plan's state kept on the
/// device purely to diff against, for three reminders whose content is derived from one date. It
/// also means a moved event is handled by doing nothing special — the plan comes back with a new
/// `event_date` and the reminders are re-laid.
class PlanNotificationHost extends ConsumerStatefulWidget {
  const PlanNotificationHost({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<PlanNotificationHost> createState() => _PlanNotificationHostState();
}

class _PlanNotificationHostState extends ConsumerState<PlanNotificationHost> {
  @override
  void initState() {
    super.initState();
    PlanNotifications.tapped.addListener(_openTapped);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      // A notification tapped from a COLD start delivers its payload while the app is still
      // building its first frame, so the value may already be set by the time this listener is
      // attached.
      _openTapped();
      // …and the FIRST scheduling pass. `ref.listen` only fires on a change, so a plan already in
      // the provider's cache (the common case — the home screen read it a frame ago) would never
      // reach `sync` at all.
      unawaited(
        ref.read(planNotificationsProvider).sync(_textsFor(ref.read(activePlanProvider).value)),
      );
    });
  }

  @override
  void dispose() {
    PlanNotifications.tapped.removeListener(_openTapped);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // `listen` and not `watch`: nothing here draws, and rebuilding the whole shell because a plan's
    // readiness moved by a percent would be a repaint for a side effect.
    ref.listen<AsyncValue<LearningPlan?>>(activePlanProvider, (_, next) {
      final plan = next.value;
      unawaited(ref.read(planNotificationsProvider).sync(_textsFor(plan)));
    });

    return widget.child;
  }

  /// The three texts, or null when there is nothing to remind anybody about.
  ///
  /// Null for: no plan, a plan that is not running, and an event that has already passed. The last
  /// one matters — the evening «как прошло?» is scheduled for the day of the event, so a plan the
  /// learner never closed does not keep asking.
  PlanNotificationTexts? _textsFor(LearningPlan? plan) {
    // A plan with NO date schedules nothing: both notifications are «утро события» and «вечер
    // события», and there is no such day yet. Setting a date later brings them back.
    final daysToEvent = plan?.daysToEvent;
    if (plan == null || !plan.status.isRunning || daysToEvent == null || daysToEvent < 0) {
      return null;
    }
    final l = AppLocalizations.of(context);
    final focus = plan.focusDay;
    final phrases = plan.computed?.days.fold<int>(0, (sum, d) => sum + d.phraseCount) ?? 0;

    return PlanNotificationTexts(
      planId: plan.id,
      eventDate: plan.eventDate!,
      channel: (name: l.planNotifyChannelName, description: l.planNotifyChannelBody),
      // «До события 1 день. День N ждёт» — the reason is the DATE, and the body names both the
      // readiness and what today teaches, so the notification is worth the interruption.
      dayBefore: focus == null
          ? null
          : (
              title: l.planNotifyBeforeTitle(1, focus.index),
              body: l.planNotifyBeforeBody(plan.readinessPercent, focus.title),
            ),
      morning: phrases > 0
          ? (title: l.planNotifyMorningTitle(phrases), body: l.planNotifyMorningBody)
          : null,
      evening: (title: l.planNotifyEveningTitle, body: l.planNotifyEveningBody),
    );
  }

  Future<void> _openTapped() async {
    final payload = PlanNotifications.tapped.value;
    if (payload == null || !mounted) return;
    // Cleared FIRST: pushing is async, and a listener that fired twice on one payload would open
    // the rehearsal twice.
    PlanNotifications.tapped.value = null;

    final separator = payload.indexOf(':');
    if (separator < 0) return;
    final kind = payload.substring(0, separator);
    final planId = payload.substring(separator + 1);
    if (planId.isEmpty) return;

    final navigator = Navigator.of(context);
    switch (kind) {
      case 'plan-rehearsal':
        final plan = await _plan(planId);
        if (plan == null || !mounted) return;
        await navigator.push(
          MaterialPageRoute(
            builder: (_) => PlanRehearsalScreen(planId: plan.id, targetLang: plan.targetLang),
          ),
        );
      case 'plan-feedback':
        final plan = await _plan(planId);
        if (plan == null || !mounted) return;
        await navigator.push(MaterialPageRoute(builder: (_) => PlanFeedbackScreen(plan: plan)));
      case 'plan':
        await navigator.push(MaterialPageRoute(builder: (_) => PlanScreen(planId: planId)));
    }
  }

  /// The plan the notification is about — read fresh, because it was scheduled days ago and the
  /// screens it opens are built from what is true now.
  Future<LearningPlan?> _plan(String planId) async {
    try {
      return await ref.read(planProvider(planId).future);
    } catch (_) {
      // Offline, or a plan that no longer exists. A notification that cannot open its screen is
      // silently a no-op: the tab is one tap away and has the honest version of the story.
      return null;
    }
  }
}
