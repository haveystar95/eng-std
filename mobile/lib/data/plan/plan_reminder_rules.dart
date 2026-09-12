/// ЧТО ТЕЛЕФОН ПЛАНА СТАВИТ СЕБЕ САМ (наряд PLAN-UI-3 §4, решение владельца 12.09).
///
/// Push на телефоне сегодня не доходит: бесплатная Personal Team не даёт APNs-токена, ключа .p8
/// нет. Поэтому то, что можно посчитать заранее по датам плана, телефон ставит ЛОКАЛЬНО и
/// пересчитывает при каждом открытии приложения и каждой смене маршрута:
///
/// - ежедневное напоминание в час обычного захода — про день, который ждёт;
/// - наутро после даты дня, который так и не пройден, — «день N ждёт со вчера» (он же и есть
///   напоминание этого дня: в сутки уходит одно, не два);
/// - в день события — «Сегодня приём» вместо напоминания.
///
/// «План готов» и «день собран» не считаются заранее — они видны внутри приложения при возврате.
/// Всё здесь — чистые функции от ответа сервера и часов: ни одно правило не ждёт плагина.
library;

import 'plan_models.dart';

/// Час и минута обычного захода.
class VisitTime {
  const VisitTime(this.hour, this.minute);

  final int hour;
  final int minute;

  /// Дефолт, пока заходов нет, — 19:00 в зоне телефона.
  static const evening = VisitTime(19, 0);

  @override
  bool operator ==(Object other) => other is VisitTime && other.hour == hour && other.minute == minute;

  @override
  int get hashCode => Object.hash(hour, minute);

  @override
  String toString() => '$hour:${minute.toString().padLeft(2, '0')}';
}

/// ЧАС ОБЫЧНОГО ЗАХОДА — медиана времени суток последних семи заходов, вниз до четверти часа.
///
/// Медиана, а не среднее: один ночной заход не должен утаскивать напоминание на полночь. Пустая
/// история — 19:00.
VisitTime usualVisitTime(List<DateTime> visits) {
  if (visits.isEmpty) return VisitTime.evening;
  final recent = [...visits]..sort();
  final last = recent.sublist(recent.length > 7 ? recent.length - 7 : 0);
  final minutes = [for (final v in last) v.hour * 60 + v.minute]..sort();
  final mid = minutes.length ~/ 2;
  final median = minutes.length.isOdd ? minutes[mid] : (minutes[mid - 1] + minutes[mid]) ~/ 2;
  final rounded = median - median % 15;

  return VisitTime(rounded ~/ 60, rounded % 60);
}

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

/// Уведомления плана на неделю вперёд — не больше одного в календарный день.
///
/// День, который ждёт, — первый не закрытый (`current_day`); его дата — дата слота сервера. В дату
/// слота — напоминание, в каждую следующую — «ждёт со вчера», в дату события — событие. Всё раньше
/// [now] не ставится. План, который не идёт (не начат, завершён, нет плана), — ничего.
List<PlanNotice> planLocalNotices(Plan? plan, DateTime now, VisitTime at, {int days = 7}) {
  if (plan == null || !plan.status.isLive) return const [];
  final day = plan.currentDay;
  final slot = _date(day?.slot.date);
  final event = _date(plan.eventDate);
  final today = DateTime(now.year, now.month, now.day);
  final out = <PlanNotice>[];

  for (var k = 0; k < days; k++) {
    final date = today.add(Duration(days: k));
    final when = DateTime(date.year, date.month, date.day, at.hour, at.minute);
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
