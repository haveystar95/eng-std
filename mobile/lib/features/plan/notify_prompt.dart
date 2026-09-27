import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/app_settings.dart';
import '../../data/device_timezone.dart';
import '../../data/plan/notify_permission.dart';
import 'plan_notifications_host.dart';
import 'plan_providers.dart';

/// The time the reminders come at — the learner's own (42-4), else the server's hour, else 19:00.
({int hour, int minute}) reminderTimeOf(AppSettings settings, int? serverHour) =>
    parseReminderTime(settings.reminderTime) ?? (hour: serverHour ?? 19, minute: 0);

/// «19:00» / «7:00 PM» — the canvas's two locales (42-1, 42-1 en).
String formatReminderTime(({int hour, int minute}) time, Locale locale) =>
    DateFormat.jm(locale.toString()).format(DateTime(2000, 1, 1, time.hour, time.minute));

/// Asks iOS for notifications and, allowed, registers this phone's push address on the server with the existing
/// handle (`PUT /devices/push-token`) — the one road for 43-1 «Напоминать» and the profile's switch. True — allowed.
Future<bool> askNotificationsAndRegister(WidgetRef ref) async {
  final granted = await ref.read(planNotificationsProvider).requestPermission();
  debugPrint('[plan-notify] permission granted: $granted');
  if (granted) {
    unawaited(ref.read(pushRegistrationProvider).register(
      timezone: await deviceTimezone(),
      onPushEnabled: (enabled) => ref.read(planPushEnabledProvider.notifier).set(enabled),
    ));
  }
  return granted;
}

/// THE PRE-PERMISSION FOR REMINDERS (frame 43-1) — after the day summary's button, before the plan.
///
/// Shown only while iOS has not been asked (on the first launch it never shows: a day must be closed first): after day
/// 1; «Не сейчас» — once more after day 2, and never again. «Напоминать» opens the system's dialog, and allowed, the
/// push address is registered; «Не сейчас» leaves the profile's switch off. The texts of the letters themselves are
/// the server's (43-2) — the phone writes none.
Future<void> offerReminders(BuildContext context, WidgetRef ref, {required int closedDay}) async {
  final permission = await ref.read(notifyPermissionProbeProvider).status();
  if (permission != NotifyPermission.notDetermined) return;
  final store = ref.read(planStoreProvider);
  final ask = await store.notifyAsk();
  if (!ask.offerAfter(closedDay) || !context.mounted) return;

  final settings = ref.read(appSettingsProvider).value ?? AppSettings.defaults;
  final time = reminderTimeOf(settings, heldPlan(ref)?.reminderHour);
  final l = AppLocalizations.of(context);
  final locale = Localizations.localeOf(context);
  final remind = await showPaperSheet<bool>(
    context: context,
    builder: (context) => PaperSheetBody(
      key: const ValueKey('notify-ask'),
      title: l.notifyAskTitle(closedDay + 1, formatReminderTime(time, locale)),
      body: l.notifyAskBody,
      stayLabel: l.notifyAskLater,
      onStay: () => Navigator.of(context).pop(false),
      actionLabel: l.notifyAskYes,
      onAction: () => Navigator.of(context).pop(true),
    ),
  );

  if (remind == true) {
    final allowed = await askNotificationsAndRegister(ref);
    await ref.read(appSettingsProvider.notifier).setReminders(allowed);
    await store.setNotifyAsk(NotifyAsk.done);
  } else {
    await ref.read(appSettingsProvider.notifier).setReminders(false);
    await store.setNotifyAsk(ask.afterLater(closedDay));
  }
  ref.invalidate(notifyPermissionProvider);
}
