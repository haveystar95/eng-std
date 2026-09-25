import 'dart:async';
import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:speech_to_text/speech_to_text.dart';

import 'package:eng_std/data/languages.dart' show sttLocaleFor;
import 'package:eng_std/data/models.dart' show ExerciseMode;
import 'package:eng_std/data/practice/language_mode_support.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';

/// THE RECOGNIZER'S REQUEST TO iOS (work order LANG-1, part C) — what [PluginSpeechRecognizer] puts on the plugin's
/// method channel, which is where `requiresOnDeviceRecognition` is decided on the Swift side.
///
/// iOS has no on-device model for Polish and Romanian (`docs/research/language-capability-matrix.md`), and a request
/// for one there is refused before the task starts: every speaking card and the talk's microphone of a pl/ro plan
/// failed. Those two ask Apple's server recognizer; everything else stays on the device. The offline failure of that
/// server request must end the way every dead channel already ends — without a transcript, never as a heard answer.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const channel = MethodChannel('plugin.csdcorp.com/speech_to_text');
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  late Completer<Map<Object?, Object?>> listened;
  final calls = <String>[];

  setUp(() {
    calls.clear();
    listened = Completer();
    messenger.setMockMethodCallHandler(channel, (call) async {
      calls.add(call.method);
      switch (call.method) {
        case 'initialize':
          return true;
        case 'listen':
          if (!listened.isCompleted) listened.complete(call.arguments as Map<Object?, Object?>);
          return true;
        default:
          return null;
      }
    });
  });

  tearDown(() => messenger.setMockMethodCallHandler(channel, null));

  /// A callback from the native side of the plugin, as iOS sends it.
  Future<void> fromIos(String method, Object? arguments) async {
    await messenger.handlePlatformMessage(channel.name, channel.codec.encodeMethodCall(MethodCall(method, arguments)), (_) {});
    await Future<void>.delayed(Duration.zero);
  }

  /// One attempt in [localeId] up to the moment iOS says it is listening; returns the `listen` call's arguments and
  /// the attempt still in flight.
  Future<(Map<Object?, Object?>, Future<SpeechAttempt>, PluginSpeechRecognizer)> listenIn(
    String localeId, {
    List<String> contextualStrings = const [],
  }) async {
    final recognizer = PluginSpeechRecognizer(SpeechToText.withMethodChannel());
    final attempt = recognizer.listenOnce(expected: const ['Rücken'], localeId: localeId, contextualStrings: contextualStrings);
    final args = await listened.future;
    await fromIos('notifyStatus', 'listening');
    return (args, attempt, recognizer);
  }

  group('on the device or on the server — by the language', () {
    // RULE (LANG-1 C1, DECISIONS item 48): pl and ro are recognized by Apple's server; every other language keeps the
    // on-device request. The pair comes from the capability table's online-only listening trainers, not a second list.
    // CATCHES: `onDevice: true` for everyone (the pl/ro plan whose every speaking card fails), a hand-kept list that
    // drifts from the table, a locale spelled with a hyphen or in another case read as a different language.
    test('the seven targets and the natives: only pl and ro ask for the network', () {
      for (final (code, onDevice) in [
        ('en', true), ('pl', false), ('ro', false), ('es', true), ('it', true), ('de', true), ('fr', true),
        // The natives the targets do not cover — a plan goal is dictated in them. `be` is heard as ru_RU (iOS has no
        // Belarusian recognizer), and that stays on the device.
        ('ru', true), ('uk', true), ('be', true),
      ]) {
        expect(PluginSpeechRecognizer.onDeviceFor(sttLocaleFor(code)), onDevice, reason: '$code → ${sttLocaleFor(code)}');
      }
      // A `be_BY` request would reach iOS as a locale with no recognizer at all (`noRecognizerError`), on the device or
      // not — the Russian stand-in is what makes the `be` row above a working microphone.
      expect(sttLocaleFor('be'), 'ru_RU');
      expect(PluginSpeechRecognizer.onDeviceFor('pl-PL'), isFalse, reason: 'a hyphen is the same locale');
      expect(PluginSpeechRecognizer.onDeviceFor('RO_ro'), isFalse, reason: 'case is not a language');
      expect(PluginSpeechRecognizer.onDeviceFor('de_AT'), isTrue);
    });

    // `speaking` is the row of the recognizer itself; `dictation` here is heard and TYPED, never recognized, so it is
    // not what decides where a language's audio goes.
    test('derived from the table: a language needs the network exactly when its speaking trainer is online-only', () {
      for (final lang in LanguageModeSupport.languages) {
        final online = LanguageModeSupport.isOnlineOnly(lang, ExerciseMode.speaking);
        expect(PluginSpeechRecognizer.onDeviceFor('${lang}_XX'), !online, reason: lang);
      }
      expect(LanguageModeSupport.languages.where((l) => !PluginSpeechRecognizer.onDeviceFor(l)).toList(), ['pl', 'ro']);
    });

    // CATCHES: the flag computed and then not sent — the channel is the only place iOS reads it from.
    test('the channel carries it: de_DE on the device, pl_PL and ro_RO to the server', () async {
      for (final (locale, onDevice) in [('de_DE', true), ('pl_PL', false), ('ro_RO', false), ('en_US', true)]) {
        calls.clear();
        listened = Completer();
        final (args, attempt, recognizer) = await listenIn(locale);
        expect(args['onDevice'], onDevice, reason: locale);
        expect(args['localeId'], locale, reason: 'the locale goes as it came');
        await recognizer.cancel();
        expect(await attempt, isA<SpeechAttempt>().having((a) => a.outcome, 'outcome', SpeechOutcome.silent));
      }
    });

    // RULE: `contextualStrings` is a hint and reaches `SFSpeechRecognitionRequest` exactly as the card built it —
    // umlauts, «ß», capitals, the order — whichever recognizer the language gets.
    // CATCHES: a hint folded to ASCII or lower case on its way, dropped on the server path.
    test('contextualStrings go through unchanged, on the server path too', () async {
      const hints = ['Mir tut der Rücken weh.', 'Seit drei Tagen.', 'Straße', 'Übelkeit'];
      final (deArgs, deAttempt, de) = await listenIn('de_DE', contextualStrings: hints);
      expect(deArgs['contextualStrings'], hints);
      await de.cancel();
      await deAttempt;

      listened = Completer();
      const polish = ['Bolą mnie plecy.', 'Od trzech dni.'];
      final (plArgs, plAttempt, pl) = await listenIn('pl_PL', contextualStrings: polish);
      expect(plArgs['contextualStrings'], polish);
      expect(plArgs['onDevice'], isFalse);
      await pl.cancel();
      await plAttempt;
    });
  });

  group('offline — the server request fails as a dead channel, never as an answer', () {
    // RULE (DECISIONS item 48 «offline — Skip without a penalty»): with no network the server request ends WITHOUT a
    // transcript — the plugin reports `doneNoResult`, then a recognizer error (SpeechToTextPlugin.swift,
    // `didFinishSuccessfully`). That is a silent attempt, which the turn reads as a dead channel and the card as its
    // «microphone needed» view with «Skip» (`skipped`, `no_mic`) — the path every refused channel already takes.
    // CATCHES: a network failure turned into a heard (empty or stale) answer the judge would grade as wrong.
    test('doneNoResult, then the error: a silent attempt, nothing heard', () async {
      final (_, attempt, _) = await listenIn('pl_PL');
      await fromIos('notifyStatus', 'doneNoResult');
      await fromIos('notifyError', jsonEncode({'errorMsg': 'error_speech_recognizer_connection_interrupted', 'permanent': true}));
      final result = await attempt;
      expect(result.outcome, SpeechOutcome.silent);
      expect(result.isHeard, isFalse);
      expect(result.text, isEmpty);
    });

    test('the error alone: the channel is unavailable, and the plugin is cancelled', () async {
      final (_, attempt, _) = await listenIn('ro_RO');
      await fromIos('notifyError', jsonEncode({'errorMsg': 'error_unknown (1101)', 'permanent': true}));
      final result = await attempt;
      expect(result.outcome, SpeechOutcome.unavailable);
      expect(result.isHeard, isFalse);
      expect(calls, contains('cancel'), reason: 'cancelOnError: a permanent error ends the session');
    });

    test('words already heard when the network drops are kept — a cut, not a refusal', () async {
      final (_, attempt, _) = await listenIn('pl_PL');
      await fromIos(
        'textRecognition',
        jsonEncode({
          'alternates': [
            {'recognizedWords': 'Bolą mnie', 'confidence': 0.8},
          ],
          // ResultType.partial — the words so far, not a final result.
          'resultType': 0,
        }),
      );
      await fromIos('notifyError', jsonEncode({'errorMsg': 'error_unknown (1101)', 'permanent': true}));
      final result = await attempt;
      expect(result.outcome, SpeechOutcome.heard);
      expect(result.text, 'Bolą mnie');
    });
  });
}
