import 'plan_models.dart';
import 'plan_notifications.dart';
import 'plan_reminder_rules.dart';

/// Текст уведомления — даёт вызывающий (здесь языков нет).
typedef PlanNoticeText = ({String title, String body}) Function(Plan plan, PlanNotice notice);

/// ОДНА ДВЕРЬ, КОТОРОЙ ТЕЛЕФОН СТАВИТ СВОИ НАПОМИНАНИЯ (доработка PLAN-UI-3, п. 2).
///
/// Каждый вызов ЗАМЕНЯЕТ всё расписание плана: снимает всё, что стояло, и ставит заново — поэтому
/// дублей не бывает ни при повторном открытии приложения, ни при смене маршрута. Что ставить, решает
/// правило [planLocalNotices]: при `push_enabled: true` оно пустое, и замена снимает всё.
class PlanReminderScheduler {
  PlanReminderScheduler(this._notifications);

  final PlanNotifications _notifications;

  /// [enabled] — «Напоминать о дне» (off — nothing stands); [at] — the learner's time instead of the server's hour.
  Future<int> apply({
    required Plan? plan,
    required bool pushEnabled,
    required DateTime now,
    required String zone,
    required String channel,
    required PlanNoticeText text,
    bool enabled = true,
    ({int hour, int minute})? at,
  }) async {
    final notices = enabled ? planLocalNotices(plan, now, pushEnabled: pushEnabled, at: at) : const <PlanNotice>[];
    await _notifications.replaceScheduled(
      [
        for (final n in notices)
          () {
            final t = text(plan!, n);

            return (at: n.at, dayNumber: n.dayNumber, title: t.title, body: t.body);
          }(),
      ],
      channel: channel,
      zone: zone,
    );

    return notices.length;
  }
}
