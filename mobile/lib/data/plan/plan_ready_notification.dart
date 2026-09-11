import 'package:flutter/foundation.dart' show ValueNotifier, debugPrint;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// «ПЛАН ГОТОВ» — the ONE notification the plan sends (кадр 22-6, токен-лист 4з).
///
/// Event-driven and outside the daily limit: it fires only if the app was put away while day one
/// was being written, and it fires once. Its tap opens the «План» tab. Nothing is scheduled ahead
/// of time — the plan's calendar has no reminders any more (the old three died with the old plan).
///
/// Best-effort throughout: a refused permission, a simulator without a notification centre, a
/// plugin that throws — none of them is a reason to fail the tab, so every entry point swallows
/// and logs.
class PlanReadyNotification {
  PlanReadyNotification(this._plugin);

  final FlutterLocalNotificationsPlugin _plugin;

  static const int _id = 9201;
  static const String payload = 'plan-ready';

  /// What the learner tapped — read by the shell to switch to the tab. A ValueNotifier because the
  /// value has to survive being set BEFORE the shell mounts (a cold start from the notification).
  static final ValueNotifier<String?> tapped = ValueNotifier<String?>(null);

  bool _ready = false;

  Future<void> init() async {
    if (_ready) return;
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
      debugPrint('[plan-ready] init: $e');
    }
  }

  /// Asked when a plan is STARTED — the moment the learner has just said there is a day they care
  /// about — never on app launch.
  Future<void> requestPermission() async {
    await init();
    try {
      await _plugin
          .resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>()
          ?.requestPermissions(alert: true, sound: true, badge: false);
      await _plugin
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    } catch (e) {
      debugPrint('[plan-ready] permission: $e');
    }
  }

  /// Show it now. [channel] — the Android channel's localised name and description (UI copy, so
  /// the caller supplies it; this file knows no languages).
  Future<void> show({
    required String title,
    required String body,
    required ({String name, String description}) channel,
  }) async {
    await init();
    if (!_ready) return;
    try {
      await _plugin.show(
        id: _id,
        title: title,
        body: body,
        notificationDetails: NotificationDetails(
          iOS: const DarwinNotificationDetails(),
          android: AndroidNotificationDetails(
            'plan',
            channel.name,
            channelDescription: channel.description,
            importance: Importance.defaultImportance,
            priority: Priority.defaultPriority,
          ),
        ),
        payload: payload,
      );
    } catch (e) {
      debugPrint('[plan-ready] show: $e');
    }
  }
}
