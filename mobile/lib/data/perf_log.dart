import 'dart:async';
import 'dart:io' show File, ProcessInfo;

import 'package:flutter/scheduler.dart';
import 'package:flutter/widgets.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

/// A small on-device stall monitor, kept from the F20-r investigation.
///
/// It is deliberately NOT the full harness that investigation used — most of that (per-transition
/// frame windows, image-cache counters, provider tick counters, HTTP timing) produced noise and
/// false leads. What actually found the bug was these three signals, so only they survive:
///
///  1. **Isolate heartbeat.** A 100 ms timer that reports how late it fired. Frame timings only say
///     the UI thread missed a vsync; this says the Dart isolate itself was not running.
///  2. **The screen it fired on.** The trainer stalled ~600 ms per spoken word while the triage
///     screen — same app, same phone — was clean. That comparison is what localised the cause
///     (flutter_tts tearing the iOS audio session down after every utterance), so the screen tag is
///     the single most valuable field here.
///  3. **Touch latency.** Tap → handler. The user's real complaint was "buttons don't respond",
///     which no frame metric can see: the frames were fine, the touch just waited.
///
/// OFF by default — a disabled monitor costs one field read per hook and no timer at all. Turn it
/// on from Profile → Разработка → Perf log when something feels slow again.
class PerfLog {
  PerfLog._();
  static final PerfLog instance = PerfLog._();

  static bool enabled = false;

  /// Which screen the user is on — 'session', 'triage' or 'app'.
  String screen = 'app';

  /// Captured lines, newest last. Bounded; the perf screen renders and copies them.
  final List<String> lines = <String>[];

  /// Bumps whenever [lines] changes, so the perf screen can rebuild.
  final ValueNotifier<int> revision = ValueNotifier<int>(0);

  bool _attached = false;
  Timer? _heartbeat;
  Stopwatch? _beatClock;
  int _lastBeatMs = 0;
  DateTime? _pointerDownAt;

  /// A frame slower than this is a visible hitch even at 120 Hz. High enough to skip the steady
  /// ~25–35 ms idle frames a blinking text cursor produces on a ProMotion display, which flooded
  /// the log during the investigation and meant nothing.
  static const double _spikeMs = 100;

  /// Nominal heartbeat is 100 ms; past this the isolate was not running.
  static const int _stallMs = 160;

  /// Tap → handler beyond this is perceptible.
  static const int _tapMs = 120;

  /// Turn the monitor on or off at runtime (the dev screen's switch).
  void setEnabled(bool on) {
    enabled = on;
    if (on) {
      _attach();
      _startHeartbeat();
    } else {
      _heartbeat?.cancel();
      _heartbeat = null;
    }
    revision.value++;
  }

  void _attach() {
    if (_attached) return;
    _attached = true;
    SchedulerBinding.instance.addTimingsCallback(_onTimings);
  }

  void _onTimings(List<FrameTiming> timings) {
    if (!enabled) return;
    for (final f in timings) {
      final total = f.totalSpan.inMicroseconds / 1000;
      if (total <= _spikeMs) continue;
      _add('! slow frame ${total.toStringAsFixed(1)}ms @$screen · '
          'build ${(f.buildDuration.inMicroseconds / 1000).toStringAsFixed(1)} · '
          'raster ${(f.rasterDuration.inMicroseconds / 1000).toStringAsFixed(1)} · '
          'vsyncOh ${(f.vsyncOverhead.inMicroseconds / 1000).toStringAsFixed(1)} · rss ${_rss()}MB');
    }
  }

  void _startHeartbeat() {
    _heartbeat?.cancel();
    _beatClock = Stopwatch()..start();
    _lastBeatMs = 0;
    _heartbeat = Timer.periodic(const Duration(milliseconds: 100), (_) {
      final now = _beatClock!.elapsedMilliseconds;
      final gap = now - _lastBeatMs;
      _lastBeatMs = now;
      if (gap > _stallMs) {
        _add('! isolate stalled ${gap}ms @$screen · rss ${_rss()}MB');
      }
    });
  }

  /// Finger touched the glass — one field write, called from the screen root.
  void pointerDown() {
    if (enabled) _pointerDownAt = DateTime.now();
  }

  /// A handler ran. Reports only taps that actually waited.
  void tapHandled(String what) {
    if (!enabled) return;
    final t0 = _pointerDownAt;
    if (t0 == null) return;
    _pointerDownAt = null;
    final ms = DateTime.now().difference(t0).inMilliseconds;
    if (ms > _tapMs) _add('! tap "$what" waited ${ms}ms @$screen');
  }

  void _add(String line) {
    lines.add(line);
    if (lines.length > 300) lines.removeAt(0);
    debugPrint('[perf] $line');
    revision.value++;
  }

  static String _rss() {
    try {
      return (ProcessInfo.currentRss / (1024 * 1024)).toStringAsFixed(0);
    } catch (_) {
      return '?';
    }
  }

  String get text => lines.join('\n');

  void clear() {
    lines.clear();
    revision.value++;
  }

  /// Persist next to the drift DB, so a run can be pulled off the device without the clipboard.
  Future<String?> dumpToFile() async {
    try {
      final dir = await getApplicationDocumentsDirectory();
      final file = File(p.join(dir.path, 'perf.txt'));
      await file.writeAsString(text);
      return file.path;
    } catch (e) {
      debugPrint('[perf] dump failed: $e');
      return null;
    }
  }
}
