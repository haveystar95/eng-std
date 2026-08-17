import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/day_key.dart';
import 'package:eng_std/data/local/sync_service.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// QA-10: the daily numbers must reflect the last `/stats` answer WITHOUT restarting the app.
///
/// On the device the summary read `Daily goal 0 / 20` in the same minute the server answered
/// `new_today: 4, reviews_today: 12`, and the new-word gate said «limit reached» on an untouched
/// quota. Two independent reasons, both here:
///
///  * [statsProvider] watched the progress rows alone and read the cached aggregates INSIDE that
///    loop. Its first emission happened before the sync had written them, and nothing emitted again
///    — neither a triage nor a fresh `/stats` creates a progress row — so the `?? 0` defaults stood
///    for the rest of the app's life.
///  * `bumpDailyActivity` wrote through a raw statement, which drift cannot attribute to a table, so
///    the activity stream never woke either.
void main() {
  late AppDatabase db;
  late ProviderContainer container;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    container = ProviderContainer(overrides: [appDatabaseProvider.overrideWith((ref) => db)]);
  });

  tearDown(() async {
    container.dispose();
    await db.close();
  });

  /// What the sync service caches after `/stats` — the numbers that are not in the delta feed.
  Future<void> cacheStats({
    required int reviewsToday,
    required int newGoal,
    required int newRemaining,
    int streak = 1,
  }) async {
    await db.setMeta(SyncKeys.reviewsToday, '$reviewsToday');
    await db.setMeta(SyncKeys.newGoal, '$newGoal');
    await db.setMeta(SyncKeys.newRemaining, '$newRemaining');
    await db.setMeta(SyncKeys.streak, '$streak');
  }

  test('an emission AFTER the stats cache is refreshed carries the fresh numbers', () async {
    final seen = <Stats>[];
    final sub = container.listen(statsProvider, (_, next) {
      final value = next.value;
      if (value != null) seen.add(value);
    });
    addTearDown(sub.close);

    // The first emission is the pre-sync one: nothing cached yet, so every aggregate is 0. This is
    // the snapshot the screen used to keep forever.
    await container.read(statsProvider.future);
    expect(seen.first.reviewsTotal, 0);
    expect(seen.first.newRemaining, 0);

    // …then the sync lands and writes what `/stats` said. No progress row is created by this — which
    // is exactly why the old stream stayed silent.
    await cacheStats(reviewsToday: 12, newGoal: 20, newRemaining: 16, streak: 1);
    await pumpEventQueue();

    expect(seen.last.reviewsTotal, 12, reason: 'the plaque read 0 here');
    expect(seen.last.newGoal, 20);
    expect(seen.last.newRemaining, 16, reason: 'the gate read «limit reached» here');
    expect(seen.last.streakDays, 1);
  });

  test('a recorded answer wakes the activity stream', () async {
    final seen = <Map<String, int>>[];
    final sub = container.listen(dailyActivityProviderForTest(db), (_, next) {
      final value = next.value;
      if (value != null) seen.add(value);
    });
    addTearDown(sub.close);

    await pumpEventQueue();
    final today = localDayKey(DateTime.now());
    expect(seen.last[today] ?? 0, 0);

    await db.bumpDailyActivity(today);
    await pumpEventQueue();

    expect(seen.last[today], 1, reason: 'raw SQL used to write the row without waking the stream');

    await db.bumpDailyActivity(today);
    await pumpEventQueue();
    expect(seen.last[today], 2);
  });

  test('merging the server activity calendar wakes it too', () async {
    final seen = <Map<String, int>>[];
    final sub = container.listen(dailyActivityProviderForTest(db), (_, next) {
      final value = next.value;
      if (value != null) seen.add(value);
    });
    addTearDown(sub.close);

    await pumpEventQueue();
    await db.mergeActiveDays(['2026-08-16', '2026-08-17']);
    await pumpEventQueue();

    expect(seen.last.keys, containsAll(['2026-08-16', '2026-08-17']));
  });
}

/// The activity provider, re-declared here rather than imported: the real one lives in the
/// features layer and this file is about the DATA layer waking up. Same query, same stream.
StreamProvider<Map<String, int>> dailyActivityProviderForTest(AppDatabase db) =>
    StreamProvider<Map<String, int>>((ref) =>
        db.watchDailyActivity().map((rows) => {for (final r in rows) r.day: r.reviews}));
