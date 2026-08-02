import 'dart:async';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// A per-user monotonic counter, persisted in the keychain under its OWN key so it
/// survives the durable queue being cleared after a successful upload. Every triage
/// swipe (and, once the review pipeline is rebuilt, every answer) gets the next value;
/// the server orders verdicts/reviews by it instead of the device clock, which can lag
/// and silently roll back a genuinely-later action.
///
/// Two independent keys (triage, review): the two sequences are never compared to each
/// other, so they must not share a counter.
///
/// Limits, deliberate for pre-release:
///  - A reinstall resets the keychain to 0. [seedAtLeast] fixes this by lifting the
///    counter to the server's high-water mark (GET /sync/cursor) on login, so new
///    values can't lose to already-stored rows.
///  - Two devices used in parallel keep independent counters and CAN still collide.
///    Acceptable until multi-device sync; a server-assigned arrival order would be the
///    real fix then.
class SeqCounter {
  SeqCounter([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;
  final Map<String, int> _mem = {};
  Future<void> _lock = Future<void>.value();

  static const String triage = 'seq_triage';
  static const String review = 'seq_review';

  /// The next value for [key] — read, increment, persist. Serialized so concurrent
  /// callers never hand out the same number.
  Future<int> next(String key) => _serialize(() async {
        final v = (await _current(key)) + 1;
        _mem[key] = v;
        await _storage.write(key: key, value: v.toString());
        return v;
      });

  /// Lift the counter to at least [floor] (the server's stored max) — used on login so
  /// a reinstall or a fresh device does not emit values below what the server already has.
  Future<void> seedAtLeast(String key, int floor) => _serialize(() async {
        if (floor > await _current(key)) {
          _mem[key] = floor;
          await _storage.write(key: key, value: floor.toString());
        }
      });

  Future<int> _current(String key) async {
    final cached = _mem[key];
    if (cached != null) return cached;
    final v = int.tryParse(await _storage.read(key: key) ?? '') ?? 0;
    _mem[key] = v;
    return v;
  }

  /// Chain operations so each read-modify-write completes before the next begins.
  Future<T> _serialize<T>(Future<T> Function() op) {
    final completer = Completer<T>();
    _lock = _lock.then((_) async {
      try {
        completer.complete(await op());
      } catch (e, s) {
        completer.completeError(e, s);
      }
    });
    return completer.future;
  }
}
