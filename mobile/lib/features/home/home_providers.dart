import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/providers.dart';
import 'home_cta.dart';

/// How many new words went into the pool today (local calendar day) — see [newWordsToday].
/// Straight off the local mirror, so it is right in airplane mode and moves under the finger the
/// moment a swipe enrols a word.
final newWordsTodayProvider = StreamProvider<int>((ref) {
  return ref
      .watch(appDatabaseProvider)
      .watchEnrolledAt()
      .map((moments) => newWordsToday(moments, DateTime.now()));
});

/// THE daily-goal counter — new words taken into the pool today, against the day's target.
///
/// The HOME screen no longer shows it: «дневная цель» in the old sense died with кадры 17a–17d,
/// where the day's progress is ANSWERED CARDS («32 из 32») and comes from the server's plan. What
/// survives here is the session summary's own «Дневная цель» stat, which is about the same act the
/// counter always described — a word taken into study — and is session UI, out of that наряд's
/// scope. Two screens printing different numbers for the same day was QA-BUG-2; one screen reading
/// it is how that stays fixed.
final dailyGoalProvider = Provider<({int done, int goal})>((ref) {
  final done = ref.watch(newWordsTodayProvider).value ?? 0;
  final newGoal = ref.watch(statsProvider).value?.newGoal ?? 0;
  final profileGoal = ref.watch(authControllerProvider).value?.profile?.dailyGoal ?? 0;
  return (done: done, goal: dailyGoalTarget(newGoal: newGoal, profileGoal: profileGoal));
});
