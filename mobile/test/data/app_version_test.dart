import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/profile/account_providers.dart';

/// THE VERSION IN THE PROFILE'S FOOTER IS THE DEVICE'S (наряд CLIENT-22-1 §3): what iOS has in the installed bundle —
/// `CFBundleShortVersionString (CFBundleVersion)`, asked over `com.denis.engstd/app_info` (`AppDelegate.swift`), the
/// same two keys a package-info plugin reads — and never a constant of the code. Off iOS nothing answers: no line.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('com.denis.engstd/app_info');
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  tearDown(() => messenger.setMockMethodCallHandler(channel, null));

  Future<String?> version() async {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    return container.read(profileVersionProvider.future);
  }

  // CATCHES: a footer that shows a number written in the code — it falls behind the build it is on.
  test('the footer reads the installed bundle: «1.0.0 (24)»', () async {
    messenger.setMockMethodCallHandler(channel, (call) async {
      expect(call.method, 'version');
      return {'name': '1.0.0', 'build': '24'};
    });
    expect(await version(), '1.0.0 (24)');
  });

  test('a bundle without a build number — the version alone', () async {
    messenger.setMockMethodCallHandler(channel, (call) async => {'name': '2.1.0', 'build': ''});
    expect(await version(), '2.1.0');
  });

  // CATCHES: an invented version where the device said nothing.
  test('no answer from the device — an empty footer, not a guess', () async {
    expect(await version(), '');
  });
}
