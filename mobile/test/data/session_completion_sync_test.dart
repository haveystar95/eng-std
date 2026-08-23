import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/session_completion_sync.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:flutter_test/flutter_test.dart';

/// QA-12: a run played to its summary must reach `study_sessions.ended_at` — including one played
/// with the network off from the first card to the last.
///
/// Before this there was no writer for the column anywhere in the backend, so every session in the
/// table looked abandoned. The queue is the reason the fix survives a flight: the completion is
/// durable before it is sent, keyed by session so a re-send is the same event.
class _FakeApi extends ApiClient {
  _FakeApi(super.tokenStore, {this.failWith});

  /// When set, every call throws it — the offline case.
  final Object? failWith;
  final List<({String sessionId, String endedAt})> calls = [];

  @override
  Future<bool> completeSession({required String sessionId, required String endedAt}) async {
    calls.add((sessionId: sessionId, endedAt: endedAt));
    final failure = failWith;
    if (failure != null) throw failure;
    return true;
  }
}

void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  DioException offline() => DioException.connectionError(
    requestOptions: RequestOptions(path: '/study/sessions/x/complete'),
    reason: 'no network',
  );

  DioException rejected(int status) => DioException.badResponse(
    statusCode: status,
    requestOptions: RequestOptions(path: '/study/sessions/x/complete'),
    response: Response(
      requestOptions: RequestOptions(path: '/'),
      statusCode: status,
    ),
  );

  test('records durably and sends, then drops the row', () async {
    final api = _FakeApi(TokenStore());
    final sync = SessionCompletionSync(api, db);

    // `record` sends on its own — the summary screen should not have to wait for a flush trigger.
    await sync.record(sessionId: 'S1', endedAt: DateTime.utc(2026, 8, 17, 17, 49, 16));
    await pumpEventQueue();

    expect(api.calls.single.sessionId, 'S1');
    expect(api.calls.single.endedAt, '2026-08-17T17:49:16.000Z');
    expect(await sync.pendingCount(), 0);
  });

  test('a run finished OFFLINE stays queued and goes up when the network returns', () async {
    final failing = SessionCompletionSync(_FakeApi(TokenStore(), failWith: offline()), db);

    await failing.record(sessionId: 'S1', endedAt: DateTime.utc(2026, 8, 17, 17, 49, 16));
    await failing.flush();
    expect(await failing.pendingCount(), 1, reason: 'the completion must survive the flight');

    final api = _FakeApi(TokenStore());
    await SessionCompletionSync(api, db).flush();

    expect(api.calls.single.sessionId, 'S1');
    expect(
      api.calls.single.endedAt,
      '2026-08-17T17:49:16.000Z',
      reason: 'the time the learner stopped, not the time the queue drained',
    );
    expect(await SessionCompletionSync(api, db).pendingCount(), 0);
  });

  test('the same run recorded twice is one completion, keeping the FIRST time', () async {
    final api = _FakeApi(TokenStore());
    final sync = SessionCompletionSync(api, db);

    await sync.record(sessionId: 'S1', endedAt: DateTime.utc(2026, 8, 17, 17, 49, 16));
    await sync.record(sessionId: 'S1', endedAt: DateTime.utc(2026, 8, 18, 9));
    await sync.flush();

    expect(api.calls.map((c) => c.endedAt), ['2026-08-17T17:49:16.000Z']);
  });

  test('a permanent reject is dropped rather than retried forever', () async {
    final api = _FakeApi(TokenStore(), failWith: rejected(422));
    final sync = SessionCompletionSync(api, db);

    await sync.record(sessionId: 'S1');
    await sync.flush();

    expect(await sync.pendingCount(), 0);
  });

  test('a backlog drains oldest first', () async {
    // Queued directly: this is the state an app that was offline for two sessions wakes up in, and
    // it must go up in the order the runs actually finished.
    await db.enqueueCompletion(
      SessionCompletionQueueRowsCompanion.insert(
        sessionId: 'S2',
        endedAt: '2026-08-17T18:00:00.000Z',
      ),
    );
    await db.enqueueCompletion(
      SessionCompletionQueueRowsCompanion.insert(
        sessionId: 'S1',
        endedAt: '2026-08-17T17:00:00.000Z',
      ),
    );

    final api = _FakeApi(TokenStore());
    await SessionCompletionSync(api, db).flush();

    expect(api.calls.map((c) => c.sessionId), ['S1', 'S2']);
    expect(await db.completionQueue(), isEmpty);
  });
}
