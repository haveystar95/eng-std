/// SESSION CARD HARNESS (work orders SESSION-1b, SESSION-1b′): a server fixture card → the real widget of its
/// kind → a person's taps → what the card recorded. Sound and microphone are stubs: the voice plays nothing and
/// the recognizer stays silent (voice in tests goes through the debug build's «what was heard» field — the same
/// road as a recording's final transcript).
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/dialogue_feed.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/pronouncer.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/session/cards/card_host.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/features/plan/session/session_voice.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

SessionDay sessionFixture(String name) => SessionDay.fromJson(sessionFixtureJson(name));

/// The fixture's raw JSON — to answer cards before parsing it ([sessionDayOf]).
Map<String, dynamic> sessionFixtureJson(String name) =>
    jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;

SessionDay sessionDayOf(Map<String, dynamic> json) => SessionDay.fromJson(json);

/// The first card of [kind] in the fixture ([skip] cards of that kind are passed over).
SessionCard fixtureCard(SessionDay day, SessionKind kind, {int skip = 0}) =>
    day.stages.expand((s) => s.cards).where((c) => c.kind == kind).skip(skip).first;

/// A voice that plays nothing and remembers what it was asked to play.
class QuietVoice extends SessionVoice {
  QuietVoice() : super(lines: LineAudioCache(directory: Directory.systemTemp), targetLang: 'en', pronouncer: _QuietPronouncer());

  final List<String> played = [];

  /// What the phone would read for each play without a file.
  final List<String> fallbacks = [];

  @override
  Future<void> warmUp() async {}

  @override
  Future<void> prepare(Iterable<CardAudio> audios) async {}

  @override
  Future<void> play(CardAudio? audio, {required String fallback, double rate = 1.0, Object? key, bool slowFallback = false}) async {
    played.add('${audio?.ref ?? '-'}@$rate');
    fallbacks.add(fallback);
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> release() async {}
}

class _QuietPronouncer extends Pronouncer {
  _QuietPronouncer() : super();

  @override
  Future<void> warmUp({required String targetLang, bool recording = false}) async {}

  @override
  Future<void> speakText(String text, {required String targetLang, bool slow = false, bool awaitDone = false}) async {}

  @override
  Future<void> stop() async {}

  @override
  Future<void> release() async {}
}

/// A recognizer that hears nothing: a recording never gets a word (the microphone is dead on the simulator and in
/// tests).
class SilentRecognizer implements SpeechRecognizer {
  SilentRecognizer({this.available = true});

  final bool available;

  @override
  bool get isReady => available;

  @override
  Future<bool> get hasPermission async => available;

  @override
  Future<bool> prepare() async => available;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) async => available ? const SpeechAttempt.silent() : const SpeechAttempt.unavailable();

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async {}
}

/// What the card did: answers, «Next» taps, questions to the judge.
class CardProbe {
  final List<SessionAnswer> answers = [];
  final List<String> judged = [];

  /// `hinted` of every question to the judge, in order.
  final List<bool> hinted = [];
  int nexts = 0;
  bool noMic = false;

  /// The microphones the card made — their locale and hint words.
  final List<SessionMic> mics = [];

  /// The judge's answer to the next question.
  SessionJudgeOutcome Function(String heard) verdict = (_) => const SessionJudgeOutcome(accepted: true, attempts: 1);

  /// While set and not completed, the judge holds its verdict — the card stays «waiting for the judge».
  Completer<void>? judgeGate;
}

CardEnv probeEnv(
  SessionCard card,
  CardProbe probe, {
  QuietVoice? voice,
  String role = 'Регистратор',
  bool micAvailable = true,
  SessionDay? day,
  PlanLevel level = PlanLevel.intermediate,
  bool noHints = false,
  List<FeedLine> feed = const [],
  String localeId = 'en_US',
  bool Function(PlanStage stage)? stageDone,
}) => CardEnv(
  card: card,
  voice: voice ?? QuietVoice(),
  targetLang: 'en',
  localeId: localeId,
  role: role,
  submit: probe.answers.add,
  next: () async => probe.nexts++,
  judge: (heard, {bool hinted = false}) async {
    probe.judged.add(heard);
    probe.hinted.add(hinted);
    final gate = probe.judgeGate;
    if (gate != null) await gate.future;
    return probe.verdict(heard);
  },
  makeMic: (expected, contextual) {
    final mic = SessionMic(
      recognizer: SilentRecognizer(available: micAvailable),
      localeId: localeId,
      expected: expected,
      contextualStrings: contextual,
    );
    probe.mics.add(mic);
    return mic;
  },
  reportNoMic: (v) => probe.noMic = v,
  openSettings: () async {},
  frameSentence: day?.frameSentence,
  termText: day?.termText,
  level: level,
  noHints: noHints,
  feed: feed,
  stageCards: day == null ? const [] : day.stages.firstWhere((s) => s.stage == card.stage).cards,
  scene: day?.scene,
  stageDone: stageDone,
);

class _Auth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01TEST', name: 'Тест');
}

/// The card on a full 390 × 1000 screen, animations off, Russian locale. Every call is a NEW card (its own key):
/// a second run of the same card within one test does not inherit the first run's answer — just like the session,
/// where cards are keyed by their position.
Future<void> pumpCard(WidgetTester tester, CardEnv env, {Size size = const Size(390, 1000)}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [authControllerProvider.overrideWith(_Auth.new)],
      child: MaterialApp(
        theme: buildAppTheme(),
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
        home: Scaffold(body: KeyedSubtree(key: UniqueKey(), child: Builder(builder: (_) => sessionCardFor(env)))),
      ),
    ),
  );
  await tester.pump();
}

/// Type [text] into the «what was heard» field: the recording starts with that text as an unchanging partial result.
Future<void> enterHeard(WidgetTester tester, String text) async {
  await tester.enterText(find.byKey(const ValueKey('session-debug-heard')), text);
  await tester.testTextInput.receiveAction(TextInputAction.done);
  await tester.pump();
}

/// Say [text] through the «what was heard» field and wait until the recording closes on the pause and goes to grading
/// (SESSION-2a §3: 1 s of silence, whatever was said).
Future<void> sayDebug(WidgetTester tester, String text, {Duration hold = const Duration(milliseconds: 1050)}) async {
  await enterHeard(tester, text);
  await tester.pump(hold);
}

/// Let the card's timers run out (autoplay, auto-advance).
Future<void> settleCard(WidgetTester tester) async {
  await tester.pump(const Duration(milliseconds: 700));
  await tester.pump(const Duration(milliseconds: 700));
}

/// Tap an option by its text.
Future<void> tapText(WidgetTester tester, String text) async {
  await tester.ensureVisible(find.text(text).last);
  await tester.tap(find.text(text).last);
  await tester.pump();
}

/// The button with this label exists and is enabled.
bool dockEnabled(WidgetTester tester, String label) {
  final finder = find.ancestor(of: find.text(label), matching: find.byType(GestureDetector));
  if (finder.evaluate().isEmpty) return false;
  return tester.widget<GestureDetector>(finder.first).onTap != null;
}

/// The session's sounds as a session opened them: [SessionSounds] loaded against a mocked
/// [AudioMixer.channel]; returns the names played, in order.
List<String> recordSessionSounds(WidgetTester tester) {
  final sounds = <String>[];
  final messenger = tester.binding.defaultBinaryMessenger;
  messenger.setMockMethodCallHandler(AudioMixer.channel, (call) async {
    if (call.method == 'playEffect') sounds.add('${(call.arguments as Map)['name']}');
    return null;
  });
  SessionSounds.resetForTest();
  unawaited(SessionSounds.load());
  addTearDown(() {
    SessionSounds.resetForTest();
    messenger.setMockMethodCallHandler(AudioMixer.channel, null);
  });
  return sounds;
}
