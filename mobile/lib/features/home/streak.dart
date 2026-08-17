/// One dot in the streak week (кадр 2.1). Exactly [kStreakWeek] dots — a week.
enum StreakDot {
  /// A completed past day (filled ink).
  filled,

  /// Today, not yet done (outline).
  today,

  /// A future day (track).
  empty,
}

/// The streak row is always a fixed week of 7 dots.
const int kStreakWeek = 7;

/// The seven dots for a [streak] length: the first `min(streak, 7)` are filled,
/// the next one (if any) is today's outline, the rest are empty. Always returns
/// exactly [kStreakWeek] entries.
List<StreakDot> streakDots(int streak, {int week = kStreakWeek}) {
  final filled = streak.clamp(0, week);
  return [
    for (var i = 0; i < week; i++)
      if (i < filled)
        StreakDot.filled
      else if (i == filled)
        StreakDot.today
      else
        StreakDot.empty,
  ];
}
