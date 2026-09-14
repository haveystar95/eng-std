import 'dart:async';
import 'dart:collection';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:path/path.dart' as p;

/// ONE AUDIO LOADER FOR THE APP (наряд DAY-UI-3) — the twin of `ImageLoader`.
///
/// Every server voice file goes to disk through here: at most [maxParallel] downloads on the wire
/// (the rest wait in order), the disk first, a retry with backoff on a dropped connection or a 5xx,
/// and one in-flight download per address — the day window's prefetch, the sheet's «прослушать» and
/// the session asking for the same line share one download. It writes to a temporary file and
/// renames it, so a download cut in half never leaves half a file under a whole file's name.
///
/// It knows ADDRESSES and FILES, not lines: which text a file voices is [LineAudioCache]'s business
/// (the key is the text), and the native player plays the file this returns.
class AudioLoader {
  AudioLoader({required Dio http, required this.directory, String? Function()? bearer, Duration? firstBackoff})
    // ignore: prefer_initializing_formals
    : _http = http,
      // ignore: prefer_initializing_formals
      _bearer = bearer,
      _firstBackoff = firstBackoff ?? const Duration(milliseconds: 350);

  /// Six: iOS keeps six connections per host, and a seventh request would only queue inside the HTTP
  /// stack where nothing can reorder it — the same number `ImageLoader` uses, for the same reason.
  static const int maxParallel = 6;

  /// Attempts after the first one, on a dropped connection or a 5xx. A 4xx is an answer, not a delay.
  static const int retries = 2;

  final Dio _http;
  final Directory directory;

  /// Asked AT THE MOMENT OF THE REQUEST — a token captured once went stale on the device (TTS-1).
  final String? Function()? _bearer;
  final Duration _firstBackoff;

  final Map<String, Future<AudioLoad>> _inflight = {};
  final Queue<Completer<void>> _waiting = Queue();
  int _active = 0;

  /// Time to file of the last loads, newest last — what the report's cold/warm measurement reads.
  final List<AudioLoadTiming> timings = [];

  /// The file name of an address: the audio row's id from the path. A new voice is a new id, so its
  /// file lands beside the old one rather than over it.
  static String fileNameOf(String url) {
    final name = p.basename(Uri.parse(url).path);

    return name.contains('.') ? name : '$name.mp3';
  }

  /// The file of [url] when it is on disk already — synchronous, for a widget deciding while building.
  String? cachedPath(String url) {
    final file = File(p.join(directory.path, fileNameOf(url)));

    return file.existsSync() && file.lengthSync() > 0 ? file.path : null;
  }

  /// The file of [url] — from disk, or downloaded now.
  Future<AudioLoad> load(String url) => _inflight[url] ??= _load(url).whenComplete(() {
    // Блоком, а не стрелкой: `remove` вернул бы саму эту future, и `whenComplete` ждал бы её — себя.
    _inflight.remove(url);
  });

  /// Warm [urls] onto the disk with nobody waiting — the day window asks for the whole day's voice
  /// the moment it opens, so «Начать» finds every file in place.
  Future<void> prefetch(Iterable<String?> urls) async {
    final wanted = {for (final u in urls) if (u != null && u.isNotEmpty && cachedPath(u) == null) u};
    await Future.wait([for (final u in wanted) load(u)]);
  }

  @visibleForTesting
  int get active => _active;

  Future<AudioLoad> _load(String url) async {
    final started = Stopwatch()..start();
    final cached = cachedPath(url);
    if (cached != null) {
      _record(url, started, fromDisk: true);

      return AudioLoad.file(cached);
    }

    await _acquire();
    try {
      final result = await _fetchWithRetry(url);
      if (result.path != null) _record(url, started, fromDisk: false);

      return result;
    } finally {
      _release();
    }
  }

  Future<AudioLoad> _fetchWithRetry(String url) async {
    var backoff = _firstBackoff;
    for (var attempt = 0; ; attempt++) {
      final result = await _fetch(url);
      final transient = result.status == null || result.status! >= 500;
      if (result.path != null || !transient || attempt >= retries) return result;
      debugPrint('[audio] retry ${attempt + 1} $url: ${result.reason}');
      if (backoff > Duration.zero) await Future<void>.delayed(backoff);
      backoff *= 2;
    }
  }

  Future<AudioLoad> _fetch(String url) async {
    final target = File(p.join(directory.path, fileNameOf(url)));
    final bearer = _bearer?.call();
    try {
      final response = await _http.get<List<int>>(
        url,
        options: Options(
          responseType: ResponseType.bytes,
          headers: bearer == null ? null : {'Authorization': 'Bearer $bearer'},
          // A 4xx is an answer («no such file»), not an exception: the row may have gone with a voice
          // change between the day being read and the download.
          validateStatus: (code) => code != null && code < 600,
        ),
      );
      final bytes = response.data;
      if (response.statusCode != 200 || bytes == null || bytes.isEmpty) {
        return AudioLoad.failed(response.statusCode, 'http ${response.statusCode}');
      }
      if (!directory.existsSync()) directory.createSync(recursive: true);
      final part = File('${target.path}.part');
      part.writeAsBytesSync(bytes, flush: true);
      part.renameSync(target.path);

      return AudioLoad.file(target.path);
    } catch (e) {
      // The first line of the reason: Dio follows it with a paragraph about status codes.
      return AudioLoad.failed(null, e.toString().split('\n').first);
    }
  }

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
    final t = AudioLoadTiming(url: url, elapsed: watch.elapsed, fromDisk: fromDisk);
    timings.add(t);
    if (timings.length > 200) timings.removeAt(0);
    debugPrint('[audio] ${fromDisk ? 'disk' : 'net'} ${t.elapsed.inMilliseconds}ms $url');
  }
}

/// What a load came to: a file on disk, or why there is none (`status` null — no answer at all).
class AudioLoad {
  const AudioLoad.file(String this.path) : status = 200, reason = null;

  const AudioLoad.failed(this.status, this.reason) : path = null;

  final String? path;
  final int? status;
  final String? reason;
}

/// One measured load: how long until the file was in hand, and from where.
class AudioLoadTiming {
  const AudioLoadTiming({required this.url, required this.elapsed, required this.fromDisk});

  final String url;
  final Duration elapsed;
  final bool fromDisk;
}
