import 'dart:async';
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';
import 'package:flutter/painting.dart';

import '../image_loader.dart';
import 'image_disk_cache.dart';

/// A remote image whose BYTES come from the app's one [ImageLoader] — the disk cache when we have
/// them, otherwise a download that shares the loader's six connections and its retries.
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

  /// The disk cache every load reads and fills — the loader's. Installed once at start-up
  /// ([installImageDiskCache]); null in tests and in the design preview, where this degrades to a
  /// plain network image.
  static ImageDiskCache? get store => ImageLoader.instance.store;
  static set store(ImageDiskCache? cache) => ImageLoader.instance.store = cache;

  /// Is this URL already on disk? Drives the card's decision to show the banner straight away
  /// instead of reserving the plate — synchronous because that decision is made while building.
  static bool isCached(String? url) => ImageLoader.instance.isCached(url);

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
    final bytes = await ImageLoader.instance.bytes(key.url);

    return decode(await ui.ImmutableBuffer.fromUint8List(bytes));
  }

  @override
  bool operator ==(Object other) => other is CachedNetworkImage && other.url == url;

  @override
  int get hashCode => url.hashCode;

  @override
  String toString() => 'CachedNetworkImage("$url")';
}
