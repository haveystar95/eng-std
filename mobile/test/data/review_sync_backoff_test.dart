import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_queue.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/data/seq_counter.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

/// F20-r2: what the queue does when the server is broken.
///
/// The lesson being pinned is a real incident — a poisoned cache entry made `POST /reviews/batch`
/// answer 500 for a full day. 500 is transient by definition, so the client was right to keep the
/// answers; what it got wrong was retrying on every trigger and growing without limit.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late AppDatabase db;
  late _StubApi api;
  late ProviderContainer container;
  late Ref ref;

  /// A throwaway provider is the only way to get a real [Ref] outside the widget tree.
  final refProbe = Provider<int>((r) {
    ref = r;
    return 0;
  });

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    api = _StubApi();
    container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
    container.read(refProbe);
  });

  ReviewSync build({int maxQueue = 500, Duration backoff = const Duration(seconds: 5)}) => ReviewSync(
        api,
        ReviewQueue(db, _NullStorage()),
        SeqCounter(_NullStorage()),
        ref,
        maxQueue: maxQueue,
        backoffBase: backoff,
        backoffMax: backoff,
      );
  tearDown(() {
    container.dispose();
    return db.close();
  });

  Future<void> answer(ReviewSync sync, {bool practice = true}) => sync.record(
        termId: 'term',
        exerciseMode: 'typing',
        response: 'x',
        isPractice: practice,
      );

  test('a transient failure keeps the answers and backs off the next attempt', () async {
    final sync = build();
    api.failWith = _transient(500);

    await answer(sync);
    await sync.flush();

    expect(api.calls, 1);
    expect(await sync.pendingCount(), 1, reason: 'a 500 must never lose an answer');

    // Every trigger calls flush; the backoff is what stops them hammering a broken server.
    await sync.flush();
    await sync.flush();
    expect(api.calls, 1, reason: 'still inside the backoff window');
  });

  test('a success clears the backoff and drains the queue', () async {
    final sync = build(backoff: Duration.zero);
    api.failWith = _transient(500);
    await answer(sync);
    await sync.flush();
    expect(await sync.pendingCount(), 1);

    api.failWith = null;
    await sync.flush();

    expect(await sync.pendingCount(), 0);
    // A later failure attempts again rather than being stuck behind the earlier one.
    api.failWith = _transient(503);
    await answer(sync);
    await sync.flush();
    expect(api.calls, 3);
  });

  test('a permanent reject drops the chunk instead of blocking the queue forever', () async {
    final sync = build();
    api.failWith = _permanent(422);

    await answer(sync);
    await sync.flush();

    expect(await sync.pendingCount(), 0, reason: '422 can never succeed on a retry');
  });

  test('the cap drops the oldest PRACTICE answers and never raises the banner for them', () async {
    final sync = build(maxQueue: 2);
    for (var i = 0; i < 4; i++) {
      await answer(sync);
    }

    expect(await sync.pendingCount(), 2, reason: 'trimmed to the cap as answers arrive');
    expect(sync.stuck.value, isFalse, reason: 'dropping practice is not a user-visible failure');
  });

  test('the cap never drops a scheduling answer — it raises the banner instead', () async {
    final sync = build(maxQueue: 2);
    api.failWith = _transient(500); // nothing drains, so the queue really fills
    for (var i = 0; i < 4; i++) {
      await answer(sync, practice: false);
    }
    await Future<void>.delayed(Duration.zero); // let the fire-and-forget flushes settle

    expect(await sync.pendingCount(), 4,
        reason: 'progress the server has not seen is never dropped to make room');
    expect(sync.stuck.value, isTrue);
  });
}

DioException _transient(int status) => DioException(
      requestOptions: RequestOptions(path: '/reviews/batch'),
      response: Response(requestOptions: RequestOptions(path: '/reviews/batch'), statusCode: status),
    );

DioException _permanent(int status) => _transient(status);

class _StubApi implements ApiClient {
  int calls = 0;
  DioException? failWith;

  @override
  Future<({int accepted, int duplicates, int unknown})> submitReviews(List<PendingReview> reviews) async {
    calls++;
    final failure = failWith;
    if (failure != null) throw failure;
    return (accepted: reviews.length, duplicates: 0, unknown: 0);
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// The queue's own store is drift here; the Keychain stand-in only has to answer "nothing stored".
class _NullStorage implements FlutterSecureStorage {
  final Map<String, String> _v = {};

  @override
  Future<String?> read({
    required String key,
    AppleOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    AppleOptions? mOptions,
    WindowsOptions? wOptions,
  }) async =>
      _v[key];

  @override
  Future<void> write({
    required String key,
    required String? value,
    AppleOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    AppleOptions? mOptions,
    WindowsOptions? wOptions,
  }) async {
    if (value == null) {
      _v.remove(key);
    } else {
      _v[key] = value;
    }
  }

  @override
  Future<void> delete({
    required String key,
    AppleOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    AppleOptions? mOptions,
    WindowsOptions? wOptions,
  }) async {
    _v.remove(key);
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
