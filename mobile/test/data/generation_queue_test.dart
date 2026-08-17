import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/generation_controller.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/token_store.dart';

/// Offline prompt queue (A3.5) — the behaviours that can't be seen from a screenshot and that the
/// handoff flags as CRITICAL: an offline-created (un-sent) generation must NOT be dropped by
/// reconciliation, and a collection a pending generation still references must NOT be reaped by a
/// full-snapshot reconcile. Both would present as ghosts.
///
/// The API client here always fails (no server / no token), so every send is a "network down".
class _OfflineApi extends ApiClient {
  _OfflineApi() : super(TokenStore());
  @override
  Future<String> generateCollection({
    String? id,
    required String topic,
    required List<String> levels,
    required int size,
    String? sourceLang,
    String? targetLang,
  }) {
    throw DioException.connectionError(
        requestOptions: RequestOptions(path: '/generations'), reason: 'offline');
  }
}

void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  GenerationController controller() => GenerationController(_OfflineApi(), db, () async {});

  test('a start() while offline records a queued (un-sent) row that survives', () async {
    final ctrl = controller();
    final id = await ctrl.start(
      topic: 'иду в банк',
      levels: ['A2', 'B1'],
      size: 15,
      sourceLang: 'ru',
      targetLang: 'en',
      targetLangExplicit: true,
    );
    // Give the fire-and-forget _send() a turn; it fails (offline) and leaves the row queued.
    await Future<void>.delayed(const Duration(milliseconds: 20));

    final rows = await db.allPendingGenerations();
    expect(rows, hasLength(1));
    expect(rows.single.id, id);
    expect(rows.single.sent, isFalse, reason: 'the POST never reached the server');
    expect(rows.single.status, 'pending');
  });

  test('reconcile() does NOT drop an un-sent offline generation (never polled → no 404 drop)', () async {
    final ctrl = controller();
    await ctrl.start(
      topic: 'у врача', levels: ['B1'], size: 10, sourceLang: 'ru', targetLang: 'en', targetLangExplicit: false,
    );
    await Future<void>.delayed(const Duration(milliseconds: 20));

    // Reconcile at "launch": the un-sent row must be re-sent (which fails, still offline), NOT
    // dropped as a ghost. A sent row would have been polled and 404-dropped.
    await ctrl.reconcile();
    await Future<void>.delayed(const Duration(milliseconds: 20));

    final rows = await db.allPendingGenerations();
    expect(rows, hasLength(1), reason: 'the offline generation is still owed, not a ghost');
    expect(rows.single.sent, isFalse);
  });

  test('reconcileCollections keeps a collection a pending generation still references', () async {
    final t0 = DateTime.utc(2026, 8, 6, 9);
    // A collection that arrived via sync for a just-finished generation.
    await db.applyDelta(collectionUpserts: [
      CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('В банке')),
    ]);
    // Its pending (succeeded, not yet acknowledged) card still points at it.
    await db.upsertPendingGeneration(PendingGenerationsCompanion.insert(
      id: 'g1',
      topic: 'иду в банк',
      status: const Value('succeeded'),
      collectionId: const Value('c1'),
      createdAt: t0,
      updatedAt: t0,
    ));

    // A racing full snapshot that does NOT list c1 yet (replica lag) must not reap it.
    await db.reconcileCollections(<String>{});

    final ids = (await db.watchCollections().first).map((c) => c.id).toSet();
    expect(ids, {'c1'}, reason: 'a pending-referenced collection is never reaped');
  });

  test('reconcileCollections still reaps a genuine ghost with no pending reference', () async {
    final t0 = DateTime.utc(2026, 8, 6, 9);
    await db.applyDelta(collectionUpserts: [
      CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('Ghost')),
    ]);
    await db.reconcileCollections(<String>{});
    expect(await db.watchCollections().first, isEmpty);
  });
}
