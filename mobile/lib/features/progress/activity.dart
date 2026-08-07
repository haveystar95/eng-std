import 'package:eng_std/theme/ink_density.dart';

import '../../data/local/day_key.dart';
import '../home/streak.dart';

/// Pure derivations for the Progress screen's activity views (кадр 2.6), computed from the local
/// `daily_activity` map (day key `YYYY-MM-DD` → review count). No Flutter, no I/O — unit-tested.
///
/// The single rule these all obey (правило 21): a day "counts" iff it has ≥1 review, so the month
/// chart, the week calendar and the streak dots all agree — the streak is exactly the run of
/// review-days. Days beyond today are always empty (activity accrues forward, never backfilled).

/// One bar of the month activity chart: a height fraction (0..1 of the tallest bar) and the ink
/// density it paints with — [InkDensity.outline] for a zero day (a hairline), else a filled bar.
class ActivityBar {
  const ActivityBar(this.fraction, this.density);
  final double fraction;
  final InkDensity density;
}

/// A nonzero day never collapses below this fraction, so a light day still shows a stub.
const double _minVisibleFraction = 0.08;

/// Below this share of the month's peak a day is a halftone bar; at or above it, a full-ink bar.
const double _filledThreshold = 0.5;

/// The seven dots Пн→Вс of [now]'s local week: a past day with any review is filled, today is the
/// outline, future days (and past days with no review) are empty track.
List<StreakDot> weekDots(DateTime now, Map<String, int> activity) {
  final today = DateTime(now.year, now.month, now.day);
  final monday = DateTime(today.year, today.month, today.day - (today.weekday - 1));
  return [
    for (var i = 0; i < 7; i++) _dotFor(DateTime(monday.year, monday.month, monday.day + i), today, activity),
  ];
}

StreakDot _dotFor(DateTime day, DateTime today, Map<String, int> activity) {
  final cmp = day.compareTo(today);
  if (cmp > 0) return StreakDot.empty; // future — track
  if (cmp == 0) return StreakDot.today; // today — outline
  return (activity[localDayKey(day)] ?? 0) > 0 ? StreakDot.filled : StreakDot.empty;
}

/// Bars for every day of [now]'s calendar month (index 0 = the 1st), heights relative to the
/// busiest day. A day with no reviews yet — including days still in the future — is an outline.
List<ActivityBar> monthBars(DateTime now, Map<String, int> activity) {
  final daysInMonth = DateTime(now.year, now.month + 1, 0).day;
  final counts = [
    for (var d = 1; d <= daysInMonth; d++) activity[localDayKey(DateTime(now.year, now.month, d))] ?? 0,
  ];
  final max = counts.fold<int>(0, (m, c) => c > m ? c : m);
  return [for (final c in counts) _bar(c, max)];
}

ActivityBar _bar(int count, int max) {
  if (count <= 0) return const ActivityBar(0, InkDensity.outline);
  final ratio = max > 0 ? count / max : 1.0;
  final density = ratio < _filledThreshold ? InkDensity.halftone : InkDensity.filled;
  return ActivityBar(ratio < _minVisibleFraction ? _minVisibleFraction : ratio, density);
}

/// Reviews across [now]'s local week (Пн→Вс), counting today and past days only — future days of
/// this week contribute zero even if the map somehow carried one.
int weekReviewCount(DateTime now, Map<String, int> activity) {
  final today = DateTime(now.year, now.month, now.day);
  final monday = DateTime(today.year, today.month, today.day - (today.weekday - 1));
  var sum = 0;
  for (var i = 0; i < 7; i++) {
    final day = DateTime(monday.year, monday.month, monday.day + i);
    if (day.compareTo(today) > 0) continue; // future — not yet
    sum += activity[localDayKey(day)] ?? 0;
  }
  return sum;
}

/// Reviews recorded today.
int todayReviewCount(DateTime now, Map<String, int> activity) =>
    activity[localDayKey(DateTime(now.year, now.month, now.day))] ?? 0;
