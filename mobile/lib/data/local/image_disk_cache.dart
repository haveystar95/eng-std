import 'dart:async';
import 'dart:io';

import 'package:drift/drift.dart';
import 'package:flutter/foundation.dart' show debugPrint;
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import 'app_database.dart';
import 'cached_image_provider.dart';

/// A BYTE cache for remote images, on disk.
///
/// Deliberately not a second image cache. Flutter's `ImageCache` holds decoded, resized images and
/// the session already manages it by hand — warm 3–7 cards ahead, drop the one that is three cards
/// behind, decode at the banner's width (F20 / F20-r). All of that stays exactly as it is; this
/// layer sits UNDERNEATH and answers a different question: where do the compressed bytes come from.
/// Before, the answer was always the network, which is why airplane mode showed grey plates and why
/// a restart re-downloaded everything the user had already seen.
///
/// The bytes live in files; [AppDatabase]'s `cached_images` is the index. That split is what makes
/// the ceiling enforceable — exact sizes and a last-used stamp, without stat-ing a thousand files
/// to find the coldest one. A hot copy of the index is held in memory so the render path can ask
/// "do we already have this?" without awaiting a query (see [containsSync]).
class ImageDiskCache {
  ImageDiskCache({
    required Directory directory,
    required AppDatabase database,
    this.maxBytes = defaultMaxBytes,
    DateTime Function()? now,
  })  : _dir = directory,
        _db = database,
        _now = now ?? DateTime.now;


  /// ~150 MB. A Pexels photo is 100–400 KB, so this is several hundred terms' worth — more than a
  /// user sees between reinstalls, while staying a rounding error next to a phone's storage.
  static const int defaultMaxBytes = 150 * 1024 * 1024;

  /// A sweep clears down to this share of the ceiling instead of stopping the moment it fits, so a
  /// cache sitting exactly at the limit doesn't evict on every single write.
  static const double _sweepTo = 0.85;

  final Directory _dir;
  final AppDatabase _db;
  final int maxBytes;

  /// Injected so a test can order the LRU deliberately — two writes in the same millisecond would
  /// otherwise make "least recently used" a coin flip.
  final DateTime Function() _now;

  /// url → file name. The durable copy is the table; this is the one the UI may read synchronously.
  final Map<String, String> _index = {};
  int _bytesOnDisk = 0;
  bool _ready = false;

  /// Total bytes currently accounted for on disk. Exposed for tests and diagnostics.
  int get bytesOnDisk => _bytesOnDisk;

  /// Load the index and make the directory. Until this completes [containsSync] answers false,
  /// which degrades to exactly the old behaviour (grey plate, then fade) — never to a wrong answer.
  Future<void> init() async {
    if (_ready) return;
    if (!await _dir.exists()) await _dir.create(recursive: true);
    for (final row in await _db.select(_db.cachedImages).get()) {
      _index[row.url] = row.file;
      _bytesOnDisk += row.bytes;
    }
    _ready = true;
  }

  /// Is this image already on disk? Synchronous on purpose: the card decides whether it can show
  /// the banner immediately or must reserve the plate, and that decision happens while building.
  bool containsSync(String url) => _index.containsKey(url);

  /// The cached bytes, or null on a miss. A row whose file vanished (an OS purge of the caches
  /// directory) is treated as a miss and forgotten, not as an error.
  Future<Uint8List?> read(String url) async {
    final name = _index[url];
    if (name == null) return null;

    final file = File(p.join(_dir.path, name));
    try {
      final bytes = await file.readAsBytes();
      unawaited(_touch(url));
      return bytes;
    } on FileSystemException {
      await _forget(url);
      return null;
    }
  }

  /// Store bytes for [url]. Overwrites an existing entry (same file name) so a re-fetch of a
  /// changed photo cannot leave two copies. Sweeps afterwards if the ceiling is breached.
  Future<void> write(String url, Uint8List bytes) async {
    if (!_ready) return; // never write into an index we have not loaded — it would double-count
    if (bytes.isEmpty) return;

    final name = _index[url] ?? _fileNameFor(url);
    final file = File(p.join(_dir.path, name));
    try {
      await file.writeAsBytes(bytes, flush: false);
    } on FileSystemException catch (e) {
      debugPrint('[image-cache] write failed for $url: $e');
      return; // a full disk must not break the session; the image still renders from memory
    }

    final previous = _index.containsKey(url)
        ? (await (_db.select(_db.cachedImages)..where((t) => t.url.equals(url))).getSingleOrNull())?.bytes ?? 0
        : 0;
    _index[url] = name;
    _bytesOnDisk += bytes.length - previous;

    await _db.into(_db.cachedImages).insertOnConflictUpdate(CachedImagesCompanion.insert(
          url: url,
          file: name,
          bytes: bytes.length,
          usedAt: _now(),
        ));

    if (_bytesOnDisk > maxBytes) await sweep();
  }

  /// Evict least-recently-used entries until the cache is comfortably under the ceiling.
  ///
  /// One global LRU, no per-collection bookkeeping: an unsubscribed collection's photos simply go
  /// cold and fall out on their own. Targeted cleanup would have to guess which images are shared
  /// with a collection the user kept — terms are global, so that guess would be wrong.
  Future<void> sweep() async {
    final target = (maxBytes * _sweepTo).round();
    if (_bytesOnDisk <= target) return;

    final rows = await (_db.select(_db.cachedImages)
          ..orderBy([(t) => OrderingTerm(expression: t.usedAt)]))
        .get();

    for (final row in rows) {
      if (_bytesOnDisk <= target) break;
      await _forget(row.url, sizeHint: row.bytes);
    }
  }

  /// Drop everything — files and index. Sign-out: another account's photos are not ours to keep.
  Future<void> clear() async {
    for (final name in _index.values) {
      try {
        await File(p.join(_dir.path, name)).delete();
      } on FileSystemException {
        // already gone — nothing to reclaim
      }
    }
    _index.clear();
    _bytesOnDisk = 0;
    await _db.delete(_db.cachedImages).go();
  }

  Future<void> _forget(String url, {int? sizeHint}) async {
    final name = _index.remove(url);
    if (name != null) {
      try {
        await File(p.join(_dir.path, name)).delete();
      } on FileSystemException {
        // the file was already gone; the row still has to go
      }
    }
    _bytesOnDisk -= sizeHint ??
        (await (_db.select(_db.cachedImages)..where((t) => t.url.equals(url))).getSingleOrNull())?.bytes ??
        0;
    if (_bytesOnDisk < 0) _bytesOnDisk = 0;
    await (_db.delete(_db.cachedImages)..where((t) => t.url.equals(url))).go();
  }

  /// Refresh the LRU stamp. Fire-and-forget: a lost touch costs one image its place in the queue,
  /// which is not worth making the render path wait on a write.
  Future<void> _touch(String url) async {
    await (_db.update(_db.cachedImages)..where((t) => t.url.equals(url)))
        .write(CachedImagesCompanion(usedAt: Value(_now())));
  }

  /// A stable, filesystem-safe name. The URL is hashed only to get a short name — the index, not
  /// the name, is what maps a URL to its file, so a hash collision costs one cache entry (the
  /// second URL overwrites the first's file and takes ownership of the row), never a wrong photo.
  static String _fileNameFor(String url) {
    // FNV-1a, 64-bit, in two 32-bit halves — Dart's ints are 64-bit but JS-compiled ints are not,
    // and the preview harness runs on web.
    var hi = 0x811c9dc5, lo = 0x811c9dc5; // hex-ok: FNV offset basis, not a colour
    for (var i = 0; i < url.length; i++) {
      final c = url.codeUnitAt(i);
      lo = ((lo ^ c) * 0x01000193) & 0xFFFFFFFF; // hex-ok: FNV prime + 32-bit mask
      hi = ((hi ^ (c + i)) * 0x01000193) & 0xFFFFFFFF; // hex-ok: FNV prime + 32-bit mask
    }
    return '${hi.toRadixString(16)}${lo.toRadixString(16)}';
  }
}

/// Build the app-wide cache and hand it to [CachedNetworkImage].
///
/// Lives in the CACHES directory, not documents: these bytes are re-downloadable, so the OS is
/// welcome to reclaim them under storage pressure (a purged file reads as a miss and is forgotten).
/// Failure here is not fatal — images simply fall back to the network, which is where they came
/// from before this layer existed.
Future<ImageDiskCache?> installImageDiskCache(AppDatabase db) async {
  try {
    final base = await getApplicationCacheDirectory();
    final cache = ImageDiskCache(directory: Directory(p.join(base.path, 'images')), database: db);
    await cache.init();
    CachedNetworkImage.store = cache;

    return cache;
  } catch (e) {
    debugPrint('[image-cache] disabled: $e');

    return null;
  }
}
