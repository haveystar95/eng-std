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
  final learnable = ref.watch(learnableByCollectionProvider).value ?? const <String, int>{};
  if (stats == null) return const HomeCta(HomeCtaKind.none);
  return computeHomeCta(
    due: stats.dueToday,
    learnableByCollection: learnable,
    untriagedByCollection: untriaged,
    totalWords: stats.totalWords,
  );
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
