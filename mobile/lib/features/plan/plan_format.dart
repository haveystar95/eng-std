import 'package:intl/intl.dart';

/// WHAT THE CLIENT FORMATS ITSELF — dates, weekdays, and the two rules the entry applies locally
/// (наряд PLAN-UI, §3–4). Pure functions, pinned by `test/features/plan/plan_format_test.dart`.
///
/// Everything that inflects the EVENT — «До приёма · 5 дней», «Приём был вчера», the day slots —
/// arrives ready from the server and is never composed here (`docs/plan-api.md`).
abstract final class PlanFormat {
  /// «15 сентября» — the day and the month in the app's locale, no year (кадры 21-2, 21-10, 22-3b).
  static String date(DateTime day, String locale) => DateFormat('d MMMM', locale).format(day);

  /// «вторник» — the weekday, lower case as the frames set it under the event.
  static String weekday(DateTime day, String locale) =>
      DateFormat('EEEE', locale).format(day).toLowerCase();

  /// `YYYY-MM-DD` for the wire — the learner's own calendar day, never UTC.
  static String wireDate(DateTime day) =>
      '${day.year.toString().padLeft(4, '0')}-${day.month.toString().padLeft(2, '0')}-${day.day.toString().padLeft(2, '0')}';

  /// A wire date back into a local calendar day, or null when it is not one.
  static DateTime? parseWireDate(String? s) {
    if (s == null || s.isEmpty) return null;
    final d = DateTime.tryParse(s);
    if (d == null) return null;

    return DateTime(d.year, d.month, d.day);
  }

  /// Calendar days from [today] to [event] — the event's own day counts as day zero.
  static int daysUntil(DateTime event, DateTime today) {
    final a = DateTime(today.year, today.month, today.day);
    final b = DateTime(event.year, event.month, event.day);

    return b.difference(a).inDays;
  }

  /// THE SHORTENING RULE OF КАДР 22-3b — «До события 3 дня — план сократится до 3».
  ///
  /// The plan cannot be longer than the calendar days left before the event; when the chosen
  /// number of days does not fit, the plan shortens to what does, and never below one day. Null
  /// when nothing changes — no date, or enough days.
  static int? shortenedDays({required int chosen, required int? daysLeft}) {
    if (daysLeft == null) return null;
    final fits = daysLeft < 1 ? 1 : daysLeft;

    return fits < chosen ? fits : null;
  }

  /// A goal SHORT enough to earn «Добавь, с кем и что важно» (кадр 22-1c): under eight words.
  static bool isShortGoal(String goal) {
    final words = goal.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).length;

    return words > 0 && words < 8;
  }
}
