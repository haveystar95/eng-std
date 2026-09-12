import 'package:flutter/foundation.dart' show ValueNotifier, debugPrint;
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/data/latest.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

/// УВЕДОМЛЕНИЯ ПЛАНА НА ТЕЛЕФОНЕ (наряд PLAN-UI-3 §4) — обёртка над плагином и больше ничего.
///
/// Что и когда ставить, решают чистые правила (`plan_reminder_rules.dart`), тексты даёт вызывающий
/// (этот файл языков не знает). Здесь: разрешение ОДИН раз — после «Начать» на превью, не при
/// старте; показ сейчас; замена всего расписания плана одним вызовом; тап — номер дня в
/// [tapped], по нему оболочка открывает таб «План» на этом дне.
///
/// Всё на авось: отказ в разрешении, симулятор без центра уведомлений, упавший плагин — не повод
/// ронять таб, поэтому каждый вход ловит и пишет в лог.
class PlanNotifications {
  PlanNotifications(this._plugin);

  final FlutterLocalNotificationsPlugin _plugin;

  /// Номера: «сейчас» — 9201, расписание — 9300 + порядковый номер (неделя вперёд влезает).
  static const int nowId = 9201;
  static const int scheduledBase = 9300;
  static const int scheduledSlots = 16;

  /// Полезная нагрузка — «plan-day:N».
  static String payloadFor(int dayNumber) => 'plan-day:$dayNumber';

  static int? dayOf(String? payload) {
    final m = RegExp(r'^plan-day:(\d+)$').firstMatch(payload ?? '');

    return m == null ? null : int.parse(m.group(1)!);
  }

  /// Номер дня из последнего тапа. ValueNotifier — значение переживает холодный старт из
  /// уведомления, когда оболочки ещё нет.
  static final ValueNotifier<int?> tapped = ValueNotifier<int?>(null);

  bool _ready = false;

  Future<void> init() async {
    if (_ready) return;
    try {
      tzdata.initializeTimeZones();
      await _plugin.initialize(
        settings: const InitializationSettings(
          iOS: DarwinInitializationSettings(
            requestAlertPermission: false,
            requestBadgePermission: false,
            requestSoundPermission: false,
          ),
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        ),
        onDidReceiveNotificationResponse: (r) => tapped.value = dayOf(r.payload),
      );
      final launch = await _plugin.getNotificationAppLaunchDetails();
      if (launch?.didNotificationLaunchApp ?? false) tapped.value = dayOf(launch?.notificationResponse?.payload);
      _ready = true;
    } catch (e) {
      debugPrint('[plan-notify] init: $e');
    }
  }

  /// Системный вопрос о разрешении. Возвращает, дал ли человек разрешение.
  Future<bool> requestPermission() async {
    await init();
    try {
      final ios = await _plugin
          .resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>()
          ?.requestPermissions(alert: true, sound: true, badge: false);
      final android = await _plugin
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();

      return ios ?? android ?? false;
    } catch (e) {
      debugPrint('[plan-notify] permission: $e');

      return false;
    }
  }

  /// Показать сейчас — дублёр сухого режима (симулятор, нет ключа APNs).
  Future<void> showNow({required String title, required String body, required int dayNumber, required String channel}) async {
    await init();
    if (!_ready) return;
    try {
      await _plugin.show(id: nowId, title: title, body: body, notificationDetails: _details(channel), payload: payloadFor(dayNumber));
    } catch (e) {
      debugPrint('[plan-notify] show: $e');
    }
  }

  /// ЗАМЕНИТЬ РАСПИСАНИЕ ПЛАНА: всё, что стояло, снимается, и ставится [items]. Пересчёт на
  /// каждом открытии приложения и каждой смене маршрута — поэтому замена, а не дописывание.
  Future<void> replaceScheduled(
    List<({DateTime at, String title, String body, int dayNumber})> items, {
    required String channel,
    required String zone,
  }) async {
    await init();
    if (!_ready) return;
    try {
      for (var i = 0; i < scheduledSlots; i++) {
        await _plugin.cancel(id: scheduledBase + i);
      }
      final location = _location(zone);
      for (var i = 0; i < items.length && i < scheduledSlots; i++) {
        final it = items[i];
        final when = tz.TZDateTime(location, it.at.year, it.at.month, it.at.day, it.at.hour, it.at.minute);
        await _plugin.zonedSchedule(
          id: scheduledBase + i,
          title: it.title,
          body: it.body,
          scheduledDate: when,
          notificationDetails: _details(channel),
          androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
          payload: payloadFor(it.dayNumber),
        );
      }
      debugPrint('[plan-notify] scheduled ${items.length}: ${items.map((e) => e.at).join(', ')}');
    } on PlatformException catch (e) {
      debugPrint('[plan-notify] schedule: $e');
    } catch (e) {
      debugPrint('[plan-notify] schedule: $e');
    }
  }

  static tz.Location _location(String zone) {
    try {
      return tz.getLocation(zone);
    } catch (_) {
      return tz.local;
    }
  }

  NotificationDetails _details(String channel) => NotificationDetails(
    iOS: const DarwinNotificationDetails(),
    android: AndroidNotificationDetails(
      'plan',
      channel,
      importance: Importance.defaultImportance,
      priority: Priority.defaultPriority,
    ),
  );
}
