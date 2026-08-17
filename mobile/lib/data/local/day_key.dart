/// Local calendar-day key `YYYY-MM-DD` — the primary key of the `daily_activity` table and the
/// lookup key the Progress screen uses for the week calendar and month chart. Local (not UTC) so a
/// day rolls over at the user's midnight, which is what "today" and a streak mean to them.
String localDayKey(DateTime dt) {
  final y = dt.year.toString().padLeft(4, '0');
  final m = dt.month.toString().padLeft(2, '0');
  final d = dt.day.toString().padLeft(2, '0');
  return '$y-$m-$d';
}
