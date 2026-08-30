import 'dart:async';

import 'package:flutter/foundation.dart' show ValueNotifier, debugPrint;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:timezone/data/latest_all.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

/// THE PLAN'S THREE NOTIFICATIONS — кадры 1c · 12, 13 and 14.
///
/// All three hang off ONE fact: the event date. Not off a streak, not off a study schedule, not off
/// «you haven't opened the app in two days». That is the rule the frames state — «причина — дата
/// события, не серия дней» — and it is why this product has no engagement notifications and this
/// class has no timer of its own.
///
///   D−1, 09:00   «До события 1 день. День N ждёт»    → the plan tab
///   D,   08:10   «Сегодня событие. K фраз за 3 минуты» → the rehearsal (кадр 15)
///   D,   19:30   «Как прошло? Отметь, что сказал»      → the feedback screen
///
/// LOCAL and not push, deliberately: every value in those texts is something the device already
/// knows, so routing them through a server would only add a way for them to disagree with the
/// screen. The cost is that they are rescheduled whenever the plan is read — which is cheap, and
/// which is also what makes «перенос события» work with no server involvement at all: a changed
/// `event_date` simply reschedules.
///
/// Everything here is best-effort. A refused permission, a simulator without a notification centre,
/// a plugin that throws on a platform we do not ship to — none of them is a reason to fail a screen,
/// so every entry point swallows and logs.
class PlanNotifications {
  PlanNotifications(this._plugin);

  final FlutterLocalNotificationsPlugin _plugin;

  /// The ids the plan owns. Fixed, so rescheduling REPLACES rather than accumulates: a plan read
  /// ten times in a morning must not leave ten copies of the same reminder in the queue.
  static const int idDayBefore = 9101;
  static const int idMorning = 9102;
  static const int idEvening = 9103;

  /// The Android channel, built from LOCALISED strings the caller supplies.
  ///
  /// A channel's name and description are shown to the person in the system settings, so they are
  /// UI copy — and UI copy does not live in a Dart file (`no_cyrillic_outside_l10n_test`, and it is
  /// right). The app ships iOS-only today, which is exactly the reason to keep this honest rather
  /// than to hardcode a language nobody would notice was wrong.
  static AndroidNotificationDetails _channel(({String name, String description}) channel) =>
      AndroidNotificationDetails(
        'plan',
        channel.name,
        channelDescription: channel.description,
        importance: Importance.defaultImportance,
        priority: Priority.defaultPriority,
      );

  /// What the learner tapped, as a payload — read by the shell to open the right screen.
  ///
  /// A ValueNotifier rather than a stream because there is exactly one consumer and the value has
  /// to survive being set BEFORE that consumer mounts: a notification tapped from a cold start
  /// delivers its payload while the app is still building its first frame.
  static final ValueNotifier<String?> tapped = ValueNotifier<String?>(null);

  static String rehearsalPayload(String planId) => 'plan-rehearsal:$planId';
  static String feedbackPayload(String planId) => 'plan-feedback:$planId';
  static String planPayload(String planId) => 'plan:$planId';

  bool _ready = false;

  /// Initialise the plugin and the timezone database, once.
  ///
  /// It does NOT ask for permission — `requestAlertPermission: false`. The prompt belongs to the
  /// moment the learner starts a plan, not to app launch: a permission dialog on the first screen
  /// of a product is a dialog answered «нет» by a person who has not yet been told what it is for.
  Future<void> init() async {
    if (_ready) return;
    try {
      tzdata.initializeTimeZones();
      tz.setLocalLocation(tz.getLocation((await FlutterTimezone.getLocalTimezone()).identifier));
    } catch (e) {
      // An unknown zone name leaves `tz.local` as UTC. The reminders are then an hour or three off
      // rather than absent, which is the better failure of the two.
      debugPrint('[plan-notifications] timezone: $e');
    }

    try {
      await _plugin.initialize(
        settings: const InitializationSettings(
          iOS: DarwinInitializationSettings(
            requestAlertPermission: false,
            requestBadgePermission: false,
            requestSoundPermission: false,
          ),
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        ),
        onDidReceiveNotificationResponse: (response) => tapped.value = response.payload,
      );
      _ready = true;
    } catch (e) {
      debugPrint('[plan-notifications] init: $e');
    }
  }

  /// Ask for permission — called when a plan is STARTED, which is the moment the learner has just
  /// told the app there is a date they care about.
  Future<void> requestPermission() async {
    await init();
    try {
      await _plugin
          .resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>()
          ?.requestPermissions(alert: true, sound: true, badge: true);
      await _plugin
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    } catch (e) {
      debugPrint('[plan-notifications] permission: $e');
    }
  }

  /// Re-lay the plan's three reminders from scratch.
  ///
  /// Cancel-then-schedule, always, and with no attempt to work out whether anything changed. The
  /// alternative would be a second copy of the plan's state kept on the device purely to diff
  /// against — for three notifications whose whole content is derived from one date.
  ///
  /// A null plan, a finished plan or a past event leaves the queue EMPTY. That is what makes «перенос
  /// события» free: the plan comes back with a new `event_date` and the next read re-lays them.
  Future<void> sync(PlanNotificationTexts? texts) async {
    await init();
    if (!_ready) return;

    for (final id in const [idDayBefore, idMorning, idEvening]) {
      try {
        await _plugin.cancel(id: id);
      } catch (e) {
        debugPrint('[plan-notifications] cancel $id: $e');
      }
    }
    if (texts == null) return;

    final event = DateTime.tryParse(texts.eventDate);
    if (event == null) return;

    await _schedule(idDayBefore, texts.dayBefore, _at(event, days: -1, hour: 9, minute: 0),
        planPayload(texts.planId), texts.channel);
    await _schedule(idMorning, texts.morning, _at(event, hour: 8, minute: 10),
        rehearsalPayload(texts.planId), texts.channel);
    await _schedule(idEvening, texts.evening, _at(event, hour: 19, minute: 30),
        feedbackPayload(texts.planId), texts.channel);
  }

  Future<void> cancelAll() => sync(null);

  static tz.TZDateTime _at(DateTime date, {int days = 0, required int hour, required int minute}) {
    final day = DateTime(date.year, date.month, date.day).add(Duration(days: days));

    return tz.TZDateTime(tz.local, day.year, day.month, day.day, hour, minute);
  }

  Future<void> _schedule(
    int id,
    ({String title, String body})? text,
    tz.TZDateTime when,
    String payload,
    ({String name, String description}) channel,
  ) async {
    // A moment that has already passed is not scheduled at all. `zonedSchedule` would throw, and
    // more to the point: a reminder about this morning, delivered this afternoon, is noise.
    if (text == null || !when.isAfter(tz.TZDateTime.now(tz.local))) return;

    try {
      await _plugin.zonedSchedule(
        id: id,
        title: text.title,
        body: text.body,
        scheduledDate: when,
        notificationDetails: NotificationDetails(
          iOS: const DarwinNotificationDetails(),
          android: _channel(channel),
        ),
        androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
        payload: payload,
      );
    } catch (e) {
      debugPrint('[plan-notifications] schedule $id: $e');
    }
  }
}

/// The three texts, already localised, plus the date they hang off.
///
/// A DTO and not a plan, because the strings are the caller's business: this file has no
/// `AppLocalizations` and must not — a service that formatted its own Russian would be UI copy
/// living outside `l10n/`, which is exactly what the cyrillic guard exists to prevent.
class PlanNotificationTexts {
  const PlanNotificationTexts({
    required this.planId,
    required this.eventDate,
    required this.channel,
    required this.dayBefore,
    required this.morning,
    required this.evening,
  });

  /// The Android channel's own name and description, localised — see [PlanNotifications._channel].
  final ({String name, String description}) channel;

  final String planId;

  /// `Y-m-d`, straight off the plan.
  final String eventDate;

  final ({String title, String body})? dayBefore, morning, evening;
}
