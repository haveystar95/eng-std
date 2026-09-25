import 'package:flutter/foundation.dart' show visibleForTesting;
import 'package:flutter/services.dart';

/// «WHICH BUILD, ON WHICH PHONE» — two headers every request to the server carries (наряд CLIENT-FIX-4 §6), so the
/// admin can tell which build and which kind of phone a learner's calls came from:
///
///   * `X-App-Build: 1.0.0 (21)` — `CFBundleShortVersionString (CFBundleVersion)` of the running build;
///   * `X-Device: iPhone 15 Pro, iOS 26.5` — the model by its marketing name and the system's version.
///
/// NOTHING THAT IDENTIFIES THIS PHONE OR ITS OWNER: no device name («iPhone (Denis)»), no vendor id, no user name —
/// the model identifier is the kind of phone, shared by millions. The values are plain ASCII: `dart:io` refuses any
/// other byte in a header (a `FormatException` on «·»), so the model and the system are joined by a comma.
///
/// Asked from iOS (`com.denis.engstd/app_info`, `AppDelegate.swift`) once and kept for the run. Not iOS (tests) — no
/// headers; a channel not up yet is asked again by the next request.
abstract final class AppIdentity {
  static const _channel = MethodChannel('com.denis.engstd/app_info');

  static Map<String, String> _headers = const {};
  static Future<Map<String, String>>? _loading;

  /// The headers as far as they are known — empty until [load] has answered.
  static Map<String, String> get headers => _headers;

  /// Ask iOS; the answer is kept, so a later call costs nothing.
  static Future<Map<String, String>> load() => _loading ??= _ask();

  /// Forget the answer — a test asks again against its own channel.
  @visibleForTesting
  static void resetForTest() {
    _loading = null;
    _headers = const {};
  }

  static Future<Map<String, String>> _ask() async {
    try {
      final version = await _channel.invokeMapMethod<String, dynamic>('version');
      final device = await _channel.invokeMapMethod<String, dynamic>('device');
      final build = buildLine(name: '${version?['name'] ?? ''}', build: '${version?['build'] ?? ''}');
      final phone = device == null
          ? null
          : deviceLine(
              machine: '${device['machine'] ?? ''}',
              system: '${device['system'] ?? ''}',
              version: '${device['version'] ?? ''}',
              simulator: device['simulator'] == true,
            );
      _headers = {'X-App-Build': ?build, 'X-Device': ?phone};
      return _headers;
    } on MissingPluginException {
      _loading = null;
      return const {};
    } on PlatformException {
      _loading = null;
      return const {};
    }
  }

  /// «1.0.0 (21)» — the name and, in brackets, the build; null when iOS gave neither.
  static String? buildLine({required String name, required String build}) {
    final n = _ascii(name);
    final b = _ascii(build);
    if (n.isEmpty && b.isEmpty) return null;
    return b.isEmpty ? n : (n.isEmpty ? b : '$n ($b)');
  }

  /// «iPhone 15 Pro, iOS 26.5» — the model's name and the system; a simulator says so. Null when iOS named no model.
  static String? deviceLine({required String machine, required String system, required String version, bool simulator = false}) {
    final model = _ascii(modelName(machine));
    if (model.isEmpty) return null;
    final os = _ascii([system, version].where((s) => s.trim().isNotEmpty).join(' '));
    final what = simulator ? '$model (simulator)' : model;
    return os.isEmpty ? what : '$what, $os';
  }

  /// The marketing name of a model identifier («iPhone15,2» → «iPhone 14 Pro»); an identifier this build does not know
  /// is sent as it is — the admin still sees what kind of phone it was.
  static String modelName(String machine) => _models[machine.trim()] ?? machine.trim();

  /// Whatever is not printable ASCII goes: a header with any other byte is not sent at all.
  static String _ascii(String s) => s.replaceAll(RegExp(r'[^\x20-\x7E]'), '').trim();

  static const Map<String, String> _models = {
    'iPhone10,1': 'iPhone 8',
    'iPhone10,4': 'iPhone 8',
    'iPhone10,2': 'iPhone 8 Plus',
    'iPhone10,5': 'iPhone 8 Plus',
    'iPhone10,3': 'iPhone X',
    'iPhone10,6': 'iPhone X',
    'iPhone11,2': 'iPhone XS',
    'iPhone11,4': 'iPhone XS Max',
    'iPhone11,6': 'iPhone XS Max',
    'iPhone11,8': 'iPhone XR',
    'iPhone12,1': 'iPhone 11',
    'iPhone12,3': 'iPhone 11 Pro',
    'iPhone12,5': 'iPhone 11 Pro Max',
    'iPhone12,8': 'iPhone SE (2nd generation)',
    'iPhone13,1': 'iPhone 12 mini',
    'iPhone13,2': 'iPhone 12',
    'iPhone13,3': 'iPhone 12 Pro',
    'iPhone13,4': 'iPhone 12 Pro Max',
    'iPhone14,4': 'iPhone 13 mini',
    'iPhone14,5': 'iPhone 13',
    'iPhone14,2': 'iPhone 13 Pro',
    'iPhone14,3': 'iPhone 13 Pro Max',
    'iPhone14,6': 'iPhone SE (3rd generation)',
    'iPhone14,7': 'iPhone 14',
    'iPhone14,8': 'iPhone 14 Plus',
    'iPhone15,2': 'iPhone 14 Pro',
    'iPhone15,3': 'iPhone 14 Pro Max',
    'iPhone15,4': 'iPhone 15',
    'iPhone15,5': 'iPhone 15 Plus',
    'iPhone16,1': 'iPhone 15 Pro',
    'iPhone16,2': 'iPhone 15 Pro Max',
    'iPhone17,1': 'iPhone 16 Pro',
    'iPhone17,2': 'iPhone 16 Pro Max',
    'iPhone17,3': 'iPhone 16',
    'iPhone17,4': 'iPhone 16 Plus',
    'iPhone17,5': 'iPhone 16e',
    'iPhone18,1': 'iPhone 17 Pro',
    'iPhone18,2': 'iPhone 17 Pro Max',
    'iPhone18,3': 'iPhone 17',
    'iPhone18,4': 'iPhone Air',
  };
}
