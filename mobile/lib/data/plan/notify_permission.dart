import 'package:flutter/foundation.dart' show debugPrint;
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// What iOS answered about notifications — asked without asking the learner anything (the `status` method of the
/// `com.denis.engstd/push` channel, `AppDelegate.swift`). `unknown` — no answer (a test, a harness, not iOS).
enum NotifyPermission { notDetermined, denied, granted, unknown }

class NotifyPermissionProbe {
  const NotifyPermissionProbe({this.channel = const MethodChannel('com.denis.engstd/push')});

  final MethodChannel channel;

  Future<NotifyPermission> status() async {
    try {
      final wire = await channel.invokeMethod<String>('status').timeout(const Duration(seconds: 2));
      return switch (wire) {
        'not_determined' => NotifyPermission.notDetermined,
        'denied' => NotifyPermission.denied,
        'authorized' => NotifyPermission.granted,
        _ => NotifyPermission.unknown,
      };
    } catch (e) {
      debugPrint('[notify] status: $e');
      return NotifyPermission.unknown;
    }
  }
}

final notifyPermissionProbeProvider = Provider<NotifyPermissionProbe>((ref) => const NotifyPermissionProbe());

/// The system's answer as screens read it — invalidate after asking.
final notifyPermissionProvider = FutureProvider<NotifyPermission>(
  (ref) => ref.watch(notifyPermissionProbeProvider).status(),
);

/// WHERE THE PRE-PERMISSION FOR REMINDERS STANDS (frame 43-1): shown after day 1's summary; «Не сейчас» — once more
/// after day 2, and never again (work order CLIENT-START §4).
enum NotifyAsk {
  /// Not shown yet.
  never,

  /// «Не сейчас» after day 1 — it comes back after day 2.
  laterOnce,

  /// Answered for good: «Напоминать», or «Не сейчас» the second time.
  done;

  static NotifyAsk fromKey(String? key) => switch (key) {
    'later' => laterOnce,
    'done' => done,
    _ => never,
  };

  String get key => switch (this) {
    NotifyAsk.never => '',
    NotifyAsk.laterOnce => 'later',
    NotifyAsk.done => 'done',
  };

  /// Is the sheet offered after closing day [closedDay]?
  bool offerAfter(int closedDay) => switch (this) {
    NotifyAsk.never => closedDay == 1,
    NotifyAsk.laterOnce => closedDay == 2,
    NotifyAsk.done => false,
  };

  /// Where it stands after «Не сейчас» on day [closedDay].
  NotifyAsk afterLater(int closedDay) => closedDay == 1 ? NotifyAsk.laterOnce : NotifyAsk.done;
}
