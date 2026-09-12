import 'dart:async';
import 'dart:collection';
import 'dart:io';

import 'package:flutter/foundation.dart';

import 'local/image_disk_cache.dart';

/// ONE IMAGE LOADER FOR THE APP (наряд PLAN-UI-3, §3 «картинки бесшовно»).
///
/// Every remote photo the app shows goes through here: at most [maxParallel] requests at once
/// (the rest wait in order), the disk cache first, a retry with backoff when the connection drops,
/// and one in-flight future per URL — the route circle, the plate cover and a prefetch asking for
/// the same crop share a single download.
///
/// It returns BYTES. Decoding, resizing and the in-memory `ImageCache` stay with Flutter's image
/// providers ([CachedNetworkImage] reads from here), so there is still exactly one decoded-image
/// cache in the app — this is the layer under it, not a second one.
class ImageLoader {
  ImageLoader._();

  static final ImageLoader instance = ImageLoader._();

  /// Six: iOS keeps six connections per host by default, and a seventh request would only queue
  /// inside the HTTP stack where nothing can reorder it.
  static const int maxParallel = 6;

  /// Attempts after the first one, on a dropped connection or a 5xx.
  static const int retries = 2;

  static const Duration _firstBackoff = Duration(milliseconds: 350);

  /// Disk store — installed at start-up, null in tests (then every read is the network).
  ImageDiskCache? store;

  /// Extra headers for a URL — the app installs the bearer token for its own API host (the
  /// scene crops are served by backend2 behind `auth:sanctum`). Other hosts get none.
  Map<String, String> Function(Uri uri) headers = _noHeaders;

  /// The network, replaceable in tests.
  @visibleForTesting
  Future<Uint8List> Function(Uri uri, Map<String, String> headers) fetcher = _httpFetch;

  final Map<String, Future<Uint8List>> _inflight = {};
  final Queue<Completer<void>> _waiting = Queue();
  int _active = 0;

  /// Time to bytes of the last loads, newest last — what the report's cold/warm measurement reads
  /// (`[image]` lines in the log carry the same numbers).
  final List<ImageLoadTiming> timings = [];

  static Map<String, String> _noHeaders(Uri _) => const {};

  /// Is [url] on disk already? Synchronous: a widget decides while building whether it may show
  /// the photo straight away.
  bool isCached(String? url) => url != null && url.isNotEmpty && (store?.containsSync(url) ?? false);

  /// The bytes of [url] — from disk, or downloaded (and then written to disk).
  Future<Uint8List> bytes(String url) => _inflight[url] ??= _load(url).whenComplete(() {
    // Блоком, а не стрелкой: `remove` вернул бы саму эту future, и `whenComplete` ждал бы её —
    // то есть себя.
    _inflight.remove(url);
  });

  /// Warm [urls] into the disk cache without anyone waiting: the route asks for the photos of the
  /// visible days and the next three, «Начать» on the preview for day one's. A failure here is
  /// silent — the circle that needs the photo will ask again and show its tone meanwhile.
  Future<void> prefetch(Iterable<String?> urls) async {
    final wanted = {for (final u in urls) if (u != null && u.isNotEmpty && !isCached(u)) u};
    await Future.wait([
      for (final u in wanted)
        bytes(u).then<void>((_) {}, onError: (Object e) => debugPrint('[image] prefetch $u: $e')),
    ]);
  }

  Future<Uint8List> _load(String url) async {
    final started = Stopwatch()..start();
    final cached = await store?.read(url);
    if (cached != null) {
      _record(url, started, fromDisk: true);

      return cached;
    }

    await _acquire();
    try {
      final data = await _fetchWithRetry(url);
      await store?.write(url, data);
      _record(url, started, fromDisk: false);

      return data;
    } finally {
      _release();
    }
  }

  Future<Uint8List> _fetchWithRetry(String url) async {
    final uri = Uri.parse(url);
    var backoff = _firstBackoff;
    for (var attempt = 0; ; attempt++) {
      try {
        return await fetcher(uri, headers(uri));
      } catch (e) {
        if (attempt >= retries || !_isTransient(e)) rethrow;
        debugPrint('[image] retry ${attempt + 1} $url: $e');
        await Future<void>.delayed(backoff);
        backoff *= 2;
      }
    }
  }

  static bool _isTransient(Object e) =>
      e is SocketException ||
      e is HttpException ||
      e is TimeoutException ||
      e is HandshakeException ||
      (e is ImageHttpError && e.statusCode >= 500);

  Future<void> _acquire() async {
    if (_active < maxParallel) {
      _active++;

      return;
    }
    final turn = Completer<void>();
    _waiting.add(turn);
    await turn.future;
  }

  void _release() {
    if (_waiting.isNotEmpty) {
      _waiting.removeFirst().complete();
    } else {
      _active--;
    }
  }

  void _record(String url, Stopwatch watch, {required bool fromDisk}) {
    final t = ImageLoadTiming(url: url, elapsed: watch.elapsed, fromDisk: fromDisk);
    timings.add(t);
    if (timings.length > 200) timings.removeAt(0);
    debugPrint('[image] ${fromDisk ? 'disk' : 'net'} ${t.elapsed.inMilliseconds}ms $url');
  }

  /// How many requests are on the wire right now — for the parallelism test.
  @visibleForTesting
  int get active => _active;

  static Future<Uint8List> _httpFetch(Uri uri, Map<String, String> headers) async {
    final client = HttpClient()
      ..autoUncompress = true
      ..connectionTimeout = const Duration(seconds: 10);
    try {
      final request = await client.getUrl(uri);
      headers.forEach(request.headers.set);
      final response = await request.close().timeout(const Duration(seconds: 20));
      if (response.statusCode != HttpStatus.ok) {
        await response.drain<void>();
        throw ImageHttpError(response.statusCode, uri);
      }

      return await consolidateHttpClientResponseBytes(response);
    } finally {
      client.close();
    }
  }
}

/// A non-200 answer for an image.
class ImageHttpError implements Exception {
  const ImageHttpError(this.statusCode, this.uri);

  final int statusCode;
  final Uri uri;

  @override
  String toString() => 'ImageHttpError($statusCode, $uri)';
}

/// One measured load: how long until the bytes were in hand, and from where.
class ImageLoadTiming {
  const ImageLoadTiming({required this.url, required this.elapsed, required this.fromDisk});

  final String url;
  final Duration elapsed;
  final bool fromDisk;
}
