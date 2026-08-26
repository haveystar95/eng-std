import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/local/sync_service.dart';
import '../../data/providers.dart';

/// Per-day review counts (day key → count) for the Progress screen — local `daily_activity`,
/// reactive. Empty until the first review lands (accrues forward, no backfill).
final dailyActivityProvider = StreamProvider<Map<String, int>>((ref) {
  return ref
      .watch(appDatabaseProvider)
      .watchDailyActivity()
      .map((rows) => {for (final r in rows) r.day: r.reviews});
});

/// The global density bar («Все N слов»): every term folded into one of освоено / в работе /
/// разобрать by the same [classifyDensity] rule the collection screen uses. Local + reactive.
final globalDensityProvider = StreamProvider<CollectionDensity>((ref) {
  return ref.watch(appDatabaseProvider).watchTermStates().map((rows) {
    var mastered = 0, inWork = 0, toSort = 0;
    for (final r in rows) {
      switch (classifyDensity(
        state: r.state,
        intervalDays: r.intervalDays ?? 0,
        enrolled: r.enrolledAt != null,
      )) {
        case DensityBucket.mastered:
          mastered++;
        case DensityBucket.inWork:
          inWork++;
        case DensityBucket.toSort:
          toSort++;
      }
    }
    return CollectionDensity(mastered: mastered, inWork: inWork, toSort: toSort);
  });
});

/// «Лучший результат» — the local running-max streak the sync cache keeps (there's no server
/// field). Depends on [statsProvider] so it refreshes whenever a sync updates the streak cache.
final bestStreakProvider = FutureProvider<int>((ref) async {
  ref.watch(statsProvider);
  final db = ref.watch(appDatabaseProvider);
  return int.tryParse(await db.getMeta(SyncKeys.bestStreak) ?? '') ?? 0;
});
