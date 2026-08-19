import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/sync_service.dart';
import 'package:eng_std/data/models.dart';

/// QA-24 — a full snapshot must reap rows the server no longer has, not just stale collections.
///
/// The device case: the server database had been rebuilt (`migrate:fresh`), so every id the phone
/// had mirrored before that rebuild pointed at rows the server has no record of. A server cannot
/// send a tombstone for a row it has never heard of, so no delta could ever clear them — and the
/// phone's own sign-out wipe could not run either, because its local store was unopenable at the
/// time. On the next login the snapshot reaped the stale COLLECTIONS (the screen correctly read
/// «Пока нет коллекций») while nothing reaped progress, terms or triage markers, so a freshly
/// registered account was offered «Повторить 62 слова» and a «Слово дня» from the old world.
///
/// The snapshot is the authoritative live set for whoever is signed in: what it does not name is
/// gone, and only this pass can say so.
class _SnapshotApi implements ApiClient {
  _SnapshotApi(this._changes);

  final Map<String, dynamic> _changes;
  int calls = 0;

  /// What the service asked for — null means it asked for a full snapshot.
  String? lastSince;

  @override
  Future<Map<String, dynamic>> syncDelta({String? since, String? cursor, int limit = 500}) async {
    calls++;
    lastSince = since;

    return {
      'server_time': '2026-08-19T18:00:00Z',
      'has_more': false,
      'changes': _changes,
    };
  }

  @override
  Future<Stats> stats() async => throw Exception('offline');

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 17);

  /// Rows mirrored before the server was rebuilt — ids the server can no longer account for.
  Future<void> seedPreRebuildRows() => db.applyDelta(
        collectionUpserts: [
          CollectionsCompanion.insert(id: 'oldCol', updatedAt: t0, title: const Value('Аэропорт')),
        ],
        itemUpserts: [
          CollectionItemsCompanion.insert(collectionId: 'oldCol', termId: 'oldTerm', updatedAt: t0),
        ],
        termUpserts: [
          TermsCompanion.insert(
            id: 'oldTerm',
            updatedAt: t0,
            termText: const Value('May I join you?'),
            translation: const Value('Можно присоединиться?'),
          ),
        ],
        progressUpserts: [
          TermProgressCompanion.insert(
            termId: 'oldTerm',
            updatedAt: t0,
            dueAt: Value(DateTime.utc(2026, 8, 18)), // overdue → would show as «Повторить»
          ),
        ],
        triageUpserts: [
          TriagedTermsCompanion.insert(termId: 'oldTerm', decidedAt: t0),
        ],
      );

  test('an EMPTY snapshot clears every trace of the pre-rebuild data', () async {
    await seedPreRebuildRows();
    expect((await db.allProgress()).length, 1);

    // A brand-new account: the server has nothing for them, so the snapshot names nothing.
    await SyncService(_SnapshotApi(const {}), db).sync();

    expect(await db.allProgress(), isEmpty, reason: 'no «Повторить N слов» from the old world');
    expect(await db.watchAllTerms().first, isEmpty, reason: 'no «Слово дня» from the old world');
    final counts = await db.debugCounts();
    expect(counts.collections, 0);
    expect(counts.items, 0);
  });

  test('rows the snapshot DOES name are kept — the reap is not a wipe', () async {
    await seedPreRebuildRows();

    await SyncService(
      _SnapshotApi({
        'terms': [
          {'id': 'oldTerm', 'updated_at': '2026-08-19T00:00:00Z', 'text': 'May I join you?'},
        ],
        'progress': [
          {'term_id': 'oldTerm', 'updated_at': '2026-08-19T00:00:00Z', 'due_at': '2026-08-18T00:00:00Z'},
        ],
        'triages': [
          {'term_id': 'oldTerm', 'updated_at': '2026-08-19T00:00:00Z'},
        ],
      }),
      db,
    ).sync();

    // Same term id, but this time the server really has it: nothing is reaped.
    expect((await db.allProgress()).length, 1);
    expect((await db.watchAllTerms().first).length, 1);
  });

  test('a DELTA (a stored cursor) never reaps — only a snapshot is authoritative', () async {
    await seedPreRebuildRows();
    await db.setMeta('sync_cursor', '2026-08-18T00:00:00Z');

    // A delta carries only what CHANGED, so treating its silence as «this row is gone» would
    // delete the entire local mirror on every quiet sync.
    final api = _SnapshotApi(const {});
    var threw = false;
    try {
      await SyncService(api, db).sync();
    } catch (_) {
      threw = true;
    }

    expect(threw, isFalse);
    expect((await db.allProgress()).length, 1, reason: 'a delta must leave untouched rows alone');
    expect((await db.watchAllTerms().first).length, 1);
  });
}
