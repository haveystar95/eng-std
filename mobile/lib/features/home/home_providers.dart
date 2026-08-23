import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/models.dart';
import '../../data/providers.dart';
import 'home_cta.dart';
import 'word_of_day.dart';

/// The state-dependent home primary action (кадр 2.1), resolved entirely from
/// local counts (`statsProvider` + eligible triage) — works in airplane mode.
final homeCtaProvider = Provider<HomeCta>((ref) {
  final stats = ref.watch(statsProvider).value;
  final untriaged = ref.watch(untriagedByCollectionProvider).value ?? const <String, int>{};
  // The pool, globally — see computeHomeCta for why this one is not a per-collection sum.
  final learnable = ref.watch(learnableCountProvider).value ?? 0;
  if (stats == null) return const HomeCta(HomeCtaKind.none);
  return computeHomeCta(
    due: stats.dueToday,
    learnable: learnable,
    untriagedByCollection: untriaged,
    remainingNewQuota: stats.newRemaining,
  );
});

/// How many new words went into the pool today (local calendar day) — see [newWordsToday].
/// Straight off the local mirror, so it is right in airplane mode and moves under the finger the
/// moment a swipe enrols a word.
final newWordsTodayProvider = StreamProvider<int>((ref) {
  return ref
      .watch(appDatabaseProvider)
      .watchEnrolledAt()
      .map((moments) => newWordsToday(moments, DateTime.now()));
});

/// THE daily-goal counter — the ONE both the home screen and the session summary read, which is
/// what keeps them from printing different numbers on the same day (QA-BUG-2).
final dailyGoalProvider = Provider<({int done, int goal})>((ref) {
  final done = ref.watch(newWordsTodayProvider).value ?? 0;
  final newGoal = ref.watch(statsProvider).value?.newGoal ?? 0;
  final profileGoal = ref.watch(authControllerProvider).value?.profile?.dailyGoal ?? 0;
  return (done: done, goal: dailyGoalTarget(newGoal: newGoal, profileGoal: profileGoal));
});

/// «Слово дня» — deterministic client pick from local terms (no endpoint), or
/// null when there are no terms yet (the block hides).
final wordOfDayProvider = StreamProvider<Word?>((ref) {
  return ref.watch(appDatabaseProvider).watchAllTerms().map((terms) {
    final words = [
      for (final t in terms)
        if ((t.termText ?? '').isNotEmpty && (t.translation ?? '').isNotEmpty)
          Word(
            termId: t.id,
            term: t.termText ?? '',
            translation: t.translation ?? '',
            transcription: t.transcription,
            example: t.example,
            type: t.type,
            imageUrl: t.imageUrl,
            imageAuthor: t.imageAuthor,
            imageAuthorUrl: t.imageAuthorUrl,
          ),
    ];
    return pickWordOfDay(words, dayNumber(DateTime.now()));
  });
});
