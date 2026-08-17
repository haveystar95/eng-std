import 'dart:io';

import 'package:drift/native.dart';
import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/sync_service.dart';
import 'package:eng_std/data/models.dart';
import 'package:flutter_test/flutter_test.dart';

/// F18: activity/streak are server truth. The client merges `/stats.active_days` into the local
/// `daily_activity` map so a relogin/reinstall restores the calendar, without clobbering the
/// optimistic count of a day the client already lit.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  // A one-shot read (not watch().first, whose stream would replay an earlier cached emission).
  Future<Map<String, int>> activity(AppDatabase db) async =>
      {for (final r in await db.select(db.dailyActivity).get()) r.day: r.reviews};

  group('Stats.fromJson active_days', () {
    test('parses the server active_days list', () {
      final s = Stats.fromJson({'streak_days': 4, 'active_days': ['2026-08-09', '2026-08-10']});
      expect(s.activeDays, ['2026-08-09', '2026-08-10']);
      expect(s.streakDays, 4);
    });

    test('defaults to empty when the field is absent', () {
      expect(Stats.fromJson(const {}).activeDays, isEmpty);
    });
  });

  group('AppDatabase.mergeActiveDays', () {
    late AppDatabase db;
    setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
    tearDown(() => db.close());

    test('lights up days the client never recorded (count 1)', () async {
      await db.mergeActiveDays(['2026-08-09', '2026-08-10']);
      expect(await activity(db), {'2026-08-09': 1, '2026-08-10': 1});
    });

    test('does not clobber an optimistic local count (today stays exact)', () async {
      await db.bumpDailyActivity('2026-08-11');
      await db.bumpDailyActivity('2026-08-11');
      await db.bumpDailyActivity('2026-08-11'); // today = 3, live

      // Server truth includes today plus an older day the client had lost.
      await db.mergeActiveDays(['2026-08-11', '2026-08-09']);

      final a = await activity(db);
      expect(a['2026-08-11'], 3); // preserved, NOT reset to 1
      expect(a['2026-08-09'], 1); // restored
    });
  });

  test('persists across a reopen — offline start shows the calendar without a network call', () async {
    final file = File('${Directory.systemTemp.path}/f18_${DateTime.now().microsecondsSinceEpoch}.sqlite');
    final first = AppDatabase.forTesting(NativeDatabase(file));
    await first.mergeActiveDays(['2026-08-09']);
    await first.close();

    final reopened = AppDatabase.forTesting(NativeDatabase(file));
    expect((await activity(reopened))['2026-08-09'], 1);
    await reopened.close();
    await file.delete();
  });

  test('a relogin sync restores the calendar and streak from /stats', () async {
    final db = AppDatabase.forTesting(NativeDatabase.memory());
    // A fresh install: no local activity, no cached streak.
    expect(await activity(db), isEmpty);

    final api = _StubApi(Stats(
      totalWords: 0, learned: 0, mastered: 0, dueToday: 0, reviewsTotal: 2, streakDays: 5,
      activeDays: const ['2026-08-09', '2026-08-10', '2026-08-11'],
    ));
    await SyncService(api, db).sync();

    expect((await activity(db)).keys, containsAll(['2026-08-09', '2026-08-10', '2026-08-11']));
    expect(await db.getMeta(SyncKeys.streak), '5'); // streak = server truth
    await db.close();
  });
}

/// Stubs just the two endpoints the stats restore touches; everything else routes to noSuchMethod.
class _StubApi implements ApiClient {
  _StubApi(this._stats);

  final Stats _stats;

  @override
  Future<Map<String, dynamic>> syncDelta({String? since, String? cursor, int limit = 500}) async =>
      {'server_time': '2026-08-11T00:00:00Z', 'has_more': false};

  @override
  Future<Stats> stats() async => _stats;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
