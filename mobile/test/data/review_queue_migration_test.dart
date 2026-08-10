import 'dart:convert';

import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/review_queue.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

/// F20-r2: the durable review queue moved out of the Keychain into drift. The move is one-way and
/// happens once on an updated install, so the import is where data can be lost — pinned here.
///
/// A fake secure store stands in for the Keychain: the real plugin needs a platform channel, and
/// what matters is the ORDER of operations (write rows → set the marker → delete the blob), not
/// the storage backend.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late AppDatabase db;
  late _FakeSecureStorage storage;
  late ReviewQueue queue;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    storage = _FakeSecureStorage();
    queue = ReviewQueue(db, storage);
  });
  tearDown(() => db.close());

  String blob(List<int> seqs) => jsonEncode([
        for (final s in seqs)
          {
            'id': 'ULID$s',
            'term_id': 'term$s',
            'exercise_mode': 'typing',
            'response': 'answer$s',
            'client_seq': s,
            'answered_at': '2026-08-10T09:00:0${s}Z',
            'used_hint': false,
            'is_practice': s.isEven,
            'latency_ms': 1000 + s,
            'session_id': 'sess',
          },
      ]);

  test('no legacy blob: migration is a no-op that still marks itself done', () async {
    await queue.migrateFromKeychain();

    expect(await queue.load(), isEmpty);
    expect(await db.getMeta(ReviewQueue.migratedMetaKey), '1');
  });

  test('a legacy blob is imported in client_seq order and then removed from the Keychain', () async {
    storage.values[ReviewQueue.legacyKey] = blob([3, 1, 2]);

    await queue.migrateFromKeychain();

    final imported = await queue.load();
    expect(imported.map((e) => e.clientSeq), [1, 2, 3], reason: 'order rides on client_seq');
    expect(imported.first.termId, 'term1');
    expect(imported.firstWhere((e) => e.clientSeq == 2).isPractice, isTrue);
    expect(storage.values.containsKey(ReviewQueue.legacyKey), isFalse,
        reason: 'the blob is only deleted AFTER the rows are written');
    expect(await db.getMeta(ReviewQueue.migratedMetaKey), '1');
  });

  test('a second launch does not re-import and does not touch the Keychain again', () async {
    storage.values[ReviewQueue.legacyKey] = blob([1, 2]);
    await queue.migrateFromKeychain();
    await queue.removeIds(['ULID1']); // the app uploaded one in the meantime

    // A stale blob reappearing (e.g. a restored backup) must not resurrect the uploaded answer.
    storage.values[ReviewQueue.legacyKey] = blob([1, 2]);
    storage.reads = 0;
    await queue.migrateFromKeychain();

    expect((await queue.load()).map((e) => e.clientSeq), [2]);
    expect(storage.reads, 0, reason: 'the marker short-circuits before reading the Keychain');
  });

  test('a corrupt blob is dropped instead of wedging the queue forever', () async {
    storage.values[ReviewQueue.legacyKey] = '{not json';

    await queue.migrateFromKeychain();

    expect(await queue.load(), isEmpty);
    expect(await db.getMeta(ReviewQueue.migratedMetaKey), '1');
    expect(storage.values.containsKey(ReviewQueue.legacyKey), isFalse);
  });

  test('re-running the import cannot duplicate rows (client ULID is the key)', () async {
    storage.values[ReviewQueue.legacyKey] = blob([1, 2]);
    await queue.migrateFromKeychain();

    // Simulate a crash between the row write and the marker: clear the marker, restore the blob.
    await db.setMeta(ReviewQueue.migratedMetaKey, null);
    storage.values[ReviewQueue.legacyKey] = blob([1, 2]);
    await queue.migrateFromKeychain();

    expect((await queue.load()).length, 2);
  });

  test('the cap only ever offers PRACTICE answers for dropping', () async {
    storage.values[ReviewQueue.legacyKey] = blob([1, 2, 3, 4]); // 2 and 4 are practice
    await queue.migrateFromKeychain();

    expect(await queue.length(), 4);
    expect(await queue.oldestPracticeIds(10), ['ULID2', 'ULID4']);
    expect(await queue.oldestPracticeIds(1), ['ULID2'], reason: 'oldest first, by client_seq');
  });
}

/// Minimal in-memory stand-in for the Keychain. Only the three calls the migration makes.
class _FakeSecureStorage implements FlutterSecureStorage {
  final Map<String, String> values = {};
  int reads = 0;

  @override
  Future<String?> read({
    required String key,
    AppleOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    AppleOptions? mOptions,
    WindowsOptions? wOptions,
  }) async {
    reads++;
    return values[key];
  }

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
      values.remove(key);
    } else {
      values[key] = value;
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
    values.remove(key);
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
