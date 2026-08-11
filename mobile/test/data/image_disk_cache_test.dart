import 'dart:io';
import 'dart:typed_data';

import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/image_disk_cache.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;

/// The disk byte cache: what makes a photo survive airplane mode and a restart.
///
/// It is deliberately NOT a second image cache — Flutter's decoded-image cache and the session's
/// warm-up own that side. These tests are about the bytes: do we have them, do we stay under the
/// ceiling, and does a photo seen once still load when memory has been thrown away.
void main() {
  late Directory dir;
  late AppDatabase db;

  Uint8List bytes(int n) => Uint8List.fromList(List.filled(n, 7));

  setUp(() {
    dir = Directory.systemTemp.createTempSync('img_cache_test');
    db = AppDatabase.forTesting(NativeDatabase.memory());
  });

  tearDown(() async {
    await db.close();
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  ImageDiskCache cache({int maxBytes = ImageDiskCache.defaultMaxBytes, DateTime Function()? now}) =>
      ImageDiskCache(directory: dir, database: db, maxBytes: maxBytes, now: now);

  group('hit and miss', () {
    test('a url never written is a miss, synchronously and asynchronously', () async {
      final c = cache();
      await c.init();

      expect(c.containsSync('https://x/none.jpg'), isFalse);
      expect(await c.read('https://x/none.jpg'), isNull);
    });

    test('what was written comes back byte for byte', () async {
      final c = cache();
      await c.init();
      await c.write('https://x/a.jpg', bytes(64));

      expect(c.containsSync('https://x/a.jpg'), isTrue);
      expect(await c.read('https://x/a.jpg'), bytes(64));
      expect(c.bytesOnDisk, 64);
    });

    test('containsSync answers false until the index is loaded — never a wrong answer', () async {
      final c = cache();
      // No init(): the map is empty, so the card falls back to the old plate-then-fade path
      // instead of promising an image that is not there.
      expect(c.containsSync('https://x/a.jpg'), isFalse);
      await c.write('https://x/a.jpg', bytes(10)); // refused: an unloaded index would double-count
      expect(c.bytesOnDisk, 0);
    });

    test('rewriting a url replaces it instead of leaving two copies', () async {
      final c = cache();
      await c.init();
      await c.write('https://x/a.jpg', bytes(100));
      await c.write('https://x/a.jpg', bytes(30));

      expect(c.bytesOnDisk, 30, reason: 'the old size must not linger in the accounting');
      expect(await c.read('https://x/a.jpg'), hasLength(30));
      expect(dir.listSync(), hasLength(1));
    });

    test('a file deleted under us (an OS purge) reads as a miss, not as a crash', () async {
      final c = cache();
      await c.init();
      await c.write('https://x/a.jpg', bytes(50));
      for (final f in dir.listSync()) {
        f.deleteSync();
      }

      expect(await c.read('https://x/a.jpg'), isNull);
      expect(c.containsSync('https://x/a.jpg'), isFalse, reason: 'the stale row is forgotten');
    });
  });

  group('ceiling', () {
    test('evicts least-recently-used until comfortably under the cap', () async {
      var clock = DateTime(2026, 8, 12, 10);
      final c = cache(maxBytes: 1000, now: () => clock);
      await c.init();

      // Oldest → newest. The cap trips on the fourth write.
      for (final name in ['a', 'b', 'c']) {
        await c.write('https://x/$name.jpg', bytes(300));
        clock = clock.add(const Duration(minutes: 1));
      }
      expect(c.bytesOnDisk, 900);

      await c.write('https://x/d.jpg', bytes(300)); // 1200 > 1000 → sweep to ≤ 850

      expect(c.bytesOnDisk, lessThanOrEqualTo(850));
      expect(c.containsSync('https://x/a.jpg'), isFalse, reason: 'the coldest goes first');
      expect(c.containsSync('https://x/d.jpg'), isTrue, reason: 'the newest must survive');
      expect(dir.listSync().length, c.bytesOnDisk ~/ 300, reason: 'files go with their rows');
    });

    test('reading an entry saves it from the next sweep', () async {
      var clock = DateTime(2026, 8, 12, 10);
      final c = cache(maxBytes: 1000, now: () => clock);
      await c.init();

      for (final name in ['a', 'b', 'c']) {
        await c.write('https://x/$name.jpg', bytes(300));
        clock = clock.add(const Duration(minutes: 1));
      }

      // `a` is the coldest by write time — but it is the one being looked at.
      await c.read('https://x/a.jpg');
      clock = clock.add(const Duration(minutes: 1));
      await c.write('https://x/d.jpg', bytes(300));

      expect(c.containsSync('https://x/a.jpg'), isTrue, reason: 'a recent read is recent use');
      expect(c.containsSync('https://x/b.jpg'), isFalse);
    });

    test('one oversized image cannot wedge the cache', () async {
      final c = cache(maxBytes: 1000);
      await c.init();

      await c.write('https://x/huge.jpg', bytes(5000));

      // It does not fit, so the sweep drops it again — and leaves the cache empty, not corrupt.
      expect(c.bytesOnDisk, 0);
      expect(c.containsSync('https://x/huge.jpg'), isFalse);
      expect(dir.listSync(), isEmpty);
    });
  });

  group('across a restart', () {
    test('a photo seen before is still there for a freshly built cache', () async {
      final first = cache();
      await first.init();
      await first.write('https://x/seen.jpg', bytes(120));

      // Same directory, same database — a new process, nothing kept in memory.
      final second = cache();
      await second.init();

      expect(second.containsSync('https://x/seen.jpg'), isTrue);
      expect(await second.read('https://x/seen.jpg'), hasLength(120));
      expect(second.bytesOnDisk, 120, reason: 'the ceiling is enforced from the first write again');
    });

    test('sign-out takes the files with it — another account gets no photos', () async {
      final c = cache();
      await c.init();
      await c.write('https://x/a.jpg', bytes(40));
      await c.write('https://x/b.jpg', bytes(40));

      await c.clear();

      expect(c.bytesOnDisk, 0);
      expect(dir.listSync(), isEmpty);
      expect(await db.select(db.cachedImages).get(), isEmpty);

      final reopened = cache();
      await reopened.init();
      expect(reopened.containsSync('https://x/a.jpg'), isFalse);
    });

    test('clearAll on the database drops the index with the rest of the account', () async {
      final c = cache();
      await c.init();
      await c.write('https://x/a.jpg', bytes(40));

      await db.clearAll();

      expect(await db.select(db.cachedImages).get(), isEmpty);
    });
  });

  test('two urls never share a file', () async {
    final c = cache();
    await c.init();
    await c.write('https://x/a.jpg', bytes(10));
    await c.write('https://x/b.jpg', bytes(20));

    final names = dir.listSync().map((f) => p.basename(f.path)).toSet();
    expect(names, hasLength(2));
    expect(await c.read('https://x/a.jpg'), hasLength(10));
    expect(await c.read('https://x/b.jpg'), hasLength(20));
  });
}
