import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';
import 'package:flutter/painting.dart';
import 'package:flutter/scheduler.dart';

import 'image_disk_cache.dart';

/// A remote image whose BYTES come from the disk cache when we have them.
///
/// A drop-in replacement for [NetworkImage] and nothing more. It is still wrapped in `ResizeImage`
/// at every call site, so the ImageCache key, the decode width, the session's warm-up and its
/// N−3 eviction all keep working on the same entries as before (F20 / F20-r) — the only thing that
/// changed is where the compressed bytes are read from. That is the whole point of putting this
/// under the existing logic instead of swapping in a package that brings its own image cache,
/// its own placeholder lifecycle and its own idea of when a photo may appear.
///
/// Equality is the URL alone, exactly like [NetworkImage], so an image warmed by the shell and the
/// one built by the card are the same cache entry.
@immutable
class CachedNetworkImage extends ImageProvider<CachedNetworkImage> {
  const CachedNetworkImage(this.url);

  final String url;

  /// The cache every instance reads and fills. A single app-wide store, installed once at start-up
  /// ([installImageDiskCache]); null in tests and in the design preview, where this degrades to a
  /// plain network image.
  static ImageDiskCache? store;

  /// Is this URL already on disk? Drives the card's decision to show the banner straight away
  /// instead of reserving the plate — synchronous because that decision is made while building.
  static bool isCached(String? url) =>
      url != null && url.isNotEmpty && (store?.containsSync(url) ?? false);

  @override
  Future<CachedNetworkImage> obtainKey(ImageConfiguration configuration) =>
      SynchronousFuture<CachedNetworkImage>(this);

  @override
  ImageStreamCompleter loadImage(CachedNetworkImage key, ImageDecoderCallback decode) {
    return MultiFrameImageStreamCompleter(
      codec: _load(key, decode),
      scale: 1.0,
      debugLabel: key.url,
      informationCollector: () => [DiagnosticsProperty<CachedNetworkImage>('Image provider', this)],
    );
  }

  Future<ui.Codec> _load(CachedNetworkImage key, ImageDecoderCallback decode) async {
    final cache = store;

    final cached = await cache?.read(key.url);
    if (cached != null) {
      return decode(await ui.ImmutableBuffer.fromUint8List(cached));
    }

    final bytes = await _fetch(key.url);
    if (cache != null) _storeWhenIdle(cache, key.url, bytes);

    return decode(await ui.ImmutableBuffer.fromUint8List(bytes));
  }

  static Future<Uint8List> _fetch(String url) async {
    final client = HttpClient()..autoUncompress = true;
    try {
      final request = await client.getUrl(Uri.parse(url));
      final response = await request.close();
      if (response.statusCode != HttpStatus.ok) {
        throw NetworkImageLoadException(statusCode: response.statusCode, uri: Uri.parse(url));
      }

      return await consolidateHttpClientResponseBytes(response);
    } finally {
      client.close();
    }
  }

  /// Write at idle priority, never inside the frames of a card transition.
  ///
  /// The write itself is off the main thread (dart:io hands it to the IO pool), so this is belt and
  /// braces — but F20's lesson was that anything scheduled during the 250 ms slide is a candidate
  /// for the stutter, and by the time we get here the image is already decoded and on screen.
  /// Nothing is waiting on the file.
  static void _storeWhenIdle(ImageDiskCache cache, String url, Uint8List bytes) {
    SchedulerBinding.instance.scheduleTask<void>(
      () => cache.write(url, bytes),
      Priority.idle,
      debugLabel: 'image-cache write',
    );
  }

  @override
  bool operator ==(Object other) => other is CachedNetworkImage && other.url == url;

  @override
  int get hashCode => url.hashCode;

  @override
  String toString() => 'CachedNetworkImage("$url")';
}
