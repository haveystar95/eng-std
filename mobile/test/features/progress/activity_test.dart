import 'package:eng_std/features/home/streak.dart';
import 'package:eng_std/features/progress/activity.dart';
import 'package:eng_std/theme/ink_density.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  // Thursday 2026-08-06 10:30 local. Its week (Пн→Вс) is Aug 3..9; Mon=Aug 3, today=Thu Aug 6.
  final now = DateTime(2026, 8, 6, 10, 30);

  group('weekDots — Пн→Вс, converges with the streak rule', () {
    test('past-with-activity filled, past-without empty, today outline, future track', () {
      final activity = {
        '2026-08-03': 5, // Mon, past → filled
        '2026-08-04': 0, // Tue, past, no reviews → empty
        '2026-08-05': 2, // Wed, past → filled
        '2026-08-06': 3, // Thu, today → outline regardless
        '2026-08-08': 9, // Sat, future → must stay empty despite a (spurious) count
      };
      expect(weekDots(now, activity), const [
        StreakDot.filled,
        StreakDot.empty,
        StreakDot.filled,
        StreakDot.today,
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.empty,
      ]);
    });

    test('empty activity → today outline, every other day track', () {
      expect(weekDots(now, const {}), const [
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.today,
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.empty,
      ]);
    });
  });

  group('weekReviewCount — today + past only', () {
    test('sums past + today, skips future days of the week', () {
      final activity = {
        '2026-08-03': 5,
        '2026-08-05': 2,
        '2026-08-06': 3,
        '2026-08-08': 9, // future — excluded
      };
      expect(weekReviewCount(now, activity), 10);
    });
  });

  group('todayReviewCount', () {
    test('reads today', () => expect(todayReviewCount(now, {'2026-08-06': 7}), 7));
    test('absent today → 0', () => expect(todayReviewCount(now, const {}), 0));
  });

  group('monthBars — one bar per day, density by share of the busiest day', () {
    test('length equals days in the month', () {
      expect(monthBars(now, const {}).length, 31); // August
      expect(monthBars(DateTime(2026, 2, 15), const {}).length, 28); // February 2026
    });

    test('zero days are outlines with no height', () {
      final bars = monthBars(now, const {});
      expect(bars.every((b) => b.density == InkDensity.outline && b.fraction == 0), isTrue);
    });

    test('peak → filled full height; half → filled; a fifth → halftone', () {
      final bars = monthBars(now, {
        '2026-08-01': 10, // peak
        '2026-08-02': 5, // 0.5 → filled (>= threshold)
        '2026-08-03': 2, // 0.2 → halftone
        '2026-08-04': 0, // outline
      });
      expect(bars[0].density, InkDensity.filled);
      expect(bars[0].fraction, 1.0);
      expect(bars[1].density, InkDensity.filled);
      expect(bars[1].fraction, closeTo(0.5, 1e-9));
      expect(bars[2].density, InkDensity.halftone);
      expect(bars[2].fraction, closeTo(0.2, 1e-9));
      expect(bars[3].density, InkDensity.outline);
    });

    test('a small nonzero day keeps a minimum visible stub', () {
      // 1 of 100 → ratio .01, floored to the min visible fraction.
      final bars = monthBars(now, {'2026-08-01': 100, '2026-08-02': 1});
      expect(bars[1].density, InkDensity.halftone);
      expect(bars[1].fraction, greaterThanOrEqualTo(0.08));
    });
  });
}
