import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:speech_to_text/speech_to_text.dart';

/// Tests the FORK's addition to the vendored `speech_to_text` plugin, at the plugin's own Dart
/// API — not through [PluginSpeechRecognizer] — since that is the layer QA-20's fix actually
/// changed. See `packages/speech_to_text/UPSTREAM.md` for what and why.
///
/// [SpeechToText.listen]'s new `contextualStrings` parameter is meant to reach
/// `SFSpeechRecognitionRequest.contextualStrings` on iOS via the plugin's existing method
/// channel — this pins that it actually lands in that channel call's arguments (with the
/// parameter) and is a complete no-op without it (backward compatibility: every call site this
/// fork didn't touch keeps behaving exactly as upstream 7.4.0).
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const channel = MethodChannel('plugin.csdcorp.com/speech_to_text');
  final calls = <MethodCall>[];

  setUp(() {
    calls.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      calls.add(call);
      switch (call.method) {
        case 'initialize':
        case 'listen':
          return true;
        default:
          return null;
      }
    });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  test('listen() with contextualStrings puts them in the channel call arguments', () async {
    final speech = SpeechToText.withMethodChannel();
    await speech.initialize();

    await speech.listen(
      listenOptions: SpeechListenOptions(localeId: 'en_US'),
      contextualStrings: const ['reservation', 'booking'],
    );

    final listenCall = calls.firstWhere((c) => c.method == 'listen');
    final args = listenCall.arguments as Map;
    expect(args['contextualStrings'], ['reservation', 'booking']);
    // The rest of the call is untouched — this fork adds a key, it doesn't rebuild the contract.
    expect(args['localeId'], 'en_US');
  });

  test('listen() without contextualStrings — no such argument at all (backward compatibility)', () async {
    final speech = SpeechToText.withMethodChannel();
    await speech.initialize();

    await speech.listen(listenOptions: SpeechListenOptions(localeId: 'en_US'));

    final listenCall = calls.firstWhere((c) => c.method == 'listen');
    final args = listenCall.arguments as Map;
    expect(args.containsKey('contextualStrings'), isFalse);
  });

  test('listen() with an empty contextualStrings list — same as omitting it', () async {
    final speech = SpeechToText.withMethodChannel();
    await speech.initialize();

    await speech.listen(
      listenOptions: SpeechListenOptions(localeId: 'en_US'),
      contextualStrings: const [],
    );

    final listenCall = calls.firstWhere((c) => c.method == 'listen');
    final args = listenCall.arguments as Map;
    expect(args.containsKey('contextualStrings'), isFalse);
  });
}
