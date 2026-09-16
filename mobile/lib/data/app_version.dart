import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// BUILD VERSION — "1.0.0 (2)": `version` from pubspec, as Info.plist recorded it (work order SESSION-1b).
///
/// Asked from iOS (`com.denis.engstd/app_info`, `AppDelegate.swift`) rather than baked in as a constant: the
/// build number changes in pubspec, and a constant would silently fall behind. Not iOS (tests) — null, no line.
final appVersionProvider = FutureProvider<String?>((ref) async {
  try {
    final info = await const MethodChannel('com.denis.engstd/app_info').invokeMapMethod<String, dynamic>('version');
    if (info == null) return null;
    final name = (info['name'] as String?) ?? '';
    final build = (info['build'] as String?) ?? '';
    if (name.isEmpty && build.isEmpty) return null;
    return build.isEmpty ? name : '$name ($build)';
  } on MissingPluginException {
    return null;
  } on PlatformException {
    return null;
  }
});
