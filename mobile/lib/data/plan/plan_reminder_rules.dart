/// ЧТО ТЕЛЕФОН ПЛАНА СТАВИТ СЕБЕ САМ (наряд PLAN-UI-3 §4 и его доработка).
///
/// Пока сервер не доставляет push (`push_enabled: false` в ответе регистрации токена — нет ключа
/// APNs или нет токена), то, что можно посчитать заранее по датам плана, телефон ставит ЛОКАЛЬНО и
/// пересчитывает при каждом открытии приложения и каждой смене маршрута:
///
/// - ежедневное напоминание — про день, который ждёт;
/// - в каждую следующую дату, пока день не пройден, — «день N ждёт со вчера» (он же и есть
///   напоминание этого дня: в сутки уходит одно, не два);
/// - в день события — «Сегодня приём» вместо напоминания.
///
/// ЧАС — НЕ ЗДЕСЬ. Час напоминаний считает сервер одним правилом для себя и для телефона (час
/// обычного захода, 19:00 без заходов, не раньше 08:00) и отдаёт его в плане (`reminder_hour`).
/// Телефон своих заходов не считает: два счётчика — это два разных часа и два письма.
///
/// Когда сервер доставляет push сам (`push_enabled: true`), локальных уведомлений нет вовсе.
library;

import 'plan_models.dart';

/// Вид локального уведомления.
enum PlanNoticeKind { reminder, skipped, eventToday }

/// Одно локальное уведомление: когда и про какой день.
class PlanNotice {
  const PlanNotice({required this.kind, required this.at, required this.dayNumber, this.dayTitle});

  final PlanNoticeKind kind;
  final DateTime at;
  final int dayNumber;
  final String? dayTitle;

  @override
  String toString() => '$kind $at day $dayNumber';
}

/// Уведомления плана на неделю вперёд — не больше одного в календарный день, в час сервера.
///
/// День, который ждёт, — первый не закрытый (`current_day`); его дата — дата слота сервера. В дату
/// слота — напоминание, в каждую следующую — «ждёт со вчера», в дату события — событие. Всё раньше
/// [now] не ставится. План, который не идёт (не начат, завершён, нет плана), и план, чьи письма
/// доставляет сервер ([pushEnabled]), — ничего.
List<PlanNotice> planLocalNotices(Plan? plan, DateTime now, {required bool pushEnabled, int days = 7}) {
  if (pushEnabled || plan == null || !plan.status.isLive) return const [];
  final day = plan.currentDay;
  final slot = _date(day?.slot.date);
  final event = _date(plan.eventDate);
  final today = DateTime(now.year, now.month, now.day);
  final out = <PlanNotice>[];

  for (var k = 0; k < days; k++) {
    final date = today.add(Duration(days: k));
    final when = DateTime(date.year, date.month, date.day, plan.reminderHour);
    if (!when.isAfter(now)) continue;

    if (event != null && date == event) {
      out.add(PlanNotice(kind: PlanNoticeKind.eventToday, at: when, dayNumber: day?.number ?? plan.daysTotal));
      continue;
    }
    if (day == null || slot == null || date.isBefore(slot)) continue;
    out.add(
      PlanNotice(
        kind: date == slot ? PlanNoticeKind.reminder : PlanNoticeKind.skipped,
        at: when,
        dayNumber: day.number,
        dayTitle: day.titleNative,
      ),
    );
  }

  return out;
}

DateTime? _date(String? wire) {
  final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(wire ?? '');
  if (m == null) return null;

  return DateTime(int.parse(m.group(1)!), int.parse(m.group(2)!), int.parse(m.group(3)!));
}
