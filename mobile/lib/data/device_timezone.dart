import 'package:flutter/foundation.dart';
import 'package:flutter_timezone/flutter_timezone.dart';

/// The device's IANA timezone identifier (e.g. `Europe/Kyiv`), sent to the backend at login and on
/// profile edits so the scheduler can round day-scale due dates to the start of the user's calendar
/// day (device-batch F19). Falls back to `UTC` if the platform can't report a zone — the server
/// applies the same UTC fallback, so a null is never fatal, only less personal.
Future<String> deviceTimezone() async {
  try {
    final info = await FlutterTimezone.getLocalTimezone();
    final id = info.identifier.trim();
    return id.isEmpty ? 'UTC' : id;
  } catch (e) {
    debugPrint('deviceTimezone: falling back to UTC ($e)');
    return 'UTC';
  }
}
