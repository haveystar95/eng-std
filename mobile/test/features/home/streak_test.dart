import 'package:eng_std/features/home/streak.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('streakDots — always a week of exactly 7', () {
    test('length is exactly 7 for any streak', () {
      for (final s in [0, 1, 3, 5, 7, 12, 100]) {
        expect(streakDots(s).length, kStreakWeek, reason: 'streak=$s');
        expect(streakDots(s).length, 7, reason: 'streak=$s');
      }
    });

    test('new user (0): today outline first, rest empty', () {
      expect(streakDots(0), [
        StreakDot.today,
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.empty,
        StreakDot.empty,
      ]);
    });

    test('streak 5: five filled, today outline, one empty', () {
      expect(streakDots(5), [
        StreakDot.filled,
        StreakDot.filled,
        StreakDot.filled,
        StreakDot.filled,
        StreakDot.filled,
        StreakDot.today,
        StreakDot.empty,
      ]);
    });

    test('full/overflow week: all seven filled, no today, no overflow', () {
      expect(streakDots(7), List.filled(7, StreakDot.filled));
      expect(streakDots(20), List.filled(7, StreakDot.filled));
    });
  });
}
