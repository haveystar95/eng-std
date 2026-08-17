import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/cached_image_provider.dart';
import 'package:eng_std/data/local/image_disk_cache.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The provider that puts the disk cache under the existing image pipeline.
///
/// The scenario that matters: a photo was seen, the app was killed (or iOS reclaimed the decoded
/// image), and now there is no network. Before this layer that was a grey plate forever.
void main() {
  /// A real 1×1 PNG — the decoder has to accept it, so bytes must be genuine.
  final png = base64Decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  );

  late Directory dir;
  late AppDatabase db;
  late ImageDiskCache cache;

  setUp(() async {
    dir = Directory.systemTemp.createTempSync('cached_image_provider_test');
    db = AppDatabase.forTesting(NativeDatabase.memory());
    cache = ImageDiskCache(directory: dir, database: db);
    await cache.init();
    CachedNetworkImage.store = cache;
  });

  tearDown(() async {
    CachedNetworkImage.store = null;
    await db.close();
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  /// Resolve a provider the way a widget does, inside `runAsync` — the disk read is real I/O, and
  /// the widget-test clock does not turn it. Returns the image, or the error it failed with.
  Future<({ImageInfo? image, Object? error})> resolve(WidgetTester tester, ImageProvider provider) async {
    final result = await tester.runAsync(() async {
      final completer = Completer<({ImageInfo? image, Object? error})>();
      provider.resolve(ImageConfiguration.empty).addListener(ImageStreamListener(
            (info, _) => completer.complete((image: info, error: null)),
            onError: (e, _) => completer.complete((image: null, error: e)),
          ));

      return completer.future.timeout(const Duration(seconds: 10));
    });

    return result!;
  }

  testWidgets('a cached photo decodes with memory thrown away and no network', (tester) async {
    const url = 'https://images.example/photo.jpg';
    await tester.runAsync(() => cache.write(url, Uint8List.fromList(png)));

    // Everything Flutter had decoded is gone — a restart, or iOS reclaiming under pressure. The
    // only place the bytes still exist is the disk. Nothing is listening on that host, so a
    // provider that reached for the network would come back with an error instead of an image.
    PaintingBinding.instance.imageCache.clear();
    PaintingBinding.instance.imageCache.clearLiveImages();

    final loaded = await resolve(tester, const CachedNetworkImage(url));

    expect(loaded.error, isNull, reason: 'the bytes were on disk; nothing should have been fetched');
    expect(loaded.image, isNotNull);
    expect(loaded.image!.image.width, 1);
  });

  testWidgets('the same photo then paints, no plate, straight from the cache', (tester) async {
    const url = 'https://images.example/photo.jpg';
    await tester.runAsync(() => cache.write(url, Uint8List.fromList(png)));
    await resolve(tester, const CachedNetworkImage(url)); // as the session's warm-up would

    await tester.pumpWidget(const MaterialApp(
      home: Image(image: CachedNetworkImage(url), width: 10, height: 10),
    ));
    await tester.pump();

    // Decoded before the frame → `wasSync`, which is the branch that shows the banner instantly.
    expect(tester.takeException(), isNull);
    expect(find.byType(Image), findsOneWidget);
    expect(PaintingBinding.instance.imageCache.currentSize, greaterThan(0));
  });

  testWidgets('a photo never cached fails cleanly — the plate stays, nothing hangs', (tester) async {
    // Nothing on disk and nowhere to fetch from (port 9 is the discard port): offline without a
    // cached copy must surface as an error the banner draws around, exactly as it did before.
    final loaded = await resolve(tester, const CachedNetworkImage('http://127.0.0.1:9/missing.jpg'));

    expect(loaded.image, isNull);
    expect(loaded.error, isNotNull);
  });

  test('isCached mirrors the store, and is safe before the store exists', () {
    const url = 'https://images.example/photo.jpg';

    expect(CachedNetworkImage.isCached(url), isFalse);
    expect(CachedNetworkImage.isCached(null), isFalse);
    expect(CachedNetworkImage.isCached(''), isFalse);

    CachedNetworkImage.store = null; // preview harness / tests: no cache installed at all
    expect(CachedNetworkImage.isCached(url), isFalse, reason: 'must never claim a cache it lacks');
  });

  test('the provider keys on the url alone, like NetworkImage', () {
    // The session warms `ResizeImage(CachedNetworkImage(url))` and the card builds the same thing;
    // they have to be one ImageCache entry or the warm-up buys nothing (F20).
    const a = CachedNetworkImage('https://x/a.jpg');
    const b = CachedNetworkImage('https://x/a.jpg');

    expect(a, b);
    expect(a.hashCode, b.hashCode);
    expect(ResizeImage(a, width: 100), ResizeImage(b, width: 100));
  });
}
