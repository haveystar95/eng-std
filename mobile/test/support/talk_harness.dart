/// THE TALK ON THE REAL SCREEN (наряд CLIENT-CONV-1a, кадры 37-5…37-12): a server document → the
/// real [TalkView] → a person's taps → what the phone asked the server for.
///
/// The documents are SNAPSHOTS OF THE LIVE SERVER (`../backend2/docs/fixtures/conversation-*.json`,
/// taken off `wordtrainer_e2e_test` with `docs/research/client-conv-1a/tools/dump-talk.php`), not
/// hand-written JSON: a talk the client can draw is a talk the server actually sends.
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/pronouncer.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_screen.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/data/speech/speech_turn.dart';
import 'package:eng_std/features/plan/session/session_voice.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'session_harness.dart' show SilentRecognizer;

Map<String, dynamic> talkFixtureJson(String name) =>
    jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;

PlanConversation talkFixture(String name) => PlanConversation.fromJson(talkFixtureJson(name));

/// The same document with [edit] applied before it is parsed — a state the live run did not leave
/// behind («Без подсказок», a ribbon one move shorter).
PlanConversation talkFixtureEdited(String name, void Function(Map<String, dynamic> json) edit) {
  final json = talkFixtureJson(name);
  edit(json);
  return PlanConversation.fromJson(json);
}

/// What the screen asked the server for, and what it was answered.
class TalkProbe {
  final List<({String kind, String? heard})> moves = [];
  int starts = 0;
  int reads = 0;
  bool again = false;
  bool hints = true;

  /// The document each call answers with; the last one repeats.
  final List<PlanConversation> documents = [];

  /// Thrown by the NEXT move instead of an answer; cleared once thrown.
  Object? failMove;

  /// Thrown by the next read.
  Object? failRead;
  int _at = 0;

  PlanConversation get _next {
    final doc = documents[_at < documents.length ? _at : documents.length - 1];
    _at++;
    return doc;
  }
}

class FakeTalkBackend implements ConversationBackend {
  FakeTalkBackend(this.probe);

  final TalkProbe probe;

  @override
  Future<PlanConversation> start(String planId, int day, {required bool again, required bool hints}) async {
    probe.starts++;
    probe.again = again;
    probe.hints = hints;
    return probe._next;
  }

  @override
  Future<PlanConversation> move(String planId, String conversationId, {required String kind, String? heard}) async {
    probe.moves.add((kind: kind, heard: heard));
    final fail = probe.failMove;
    if (fail != null) {
      probe.failMove = null;
      throw fail;
    }
    return probe._next;
  }

  @override
  Future<PlanConversation> read(String planId, String conversationId) async {
    probe.reads++;
    final fail = probe.failRead;
    if (fail != null) {
      probe.failRead = null;
      throw fail;
    }
    return probe._next;
  }
}

/// A VOICE THAT HOLDS THE LINE UNTIL IT IS LET GO — the role is «speaking» for as long as the test
/// needs it to be, so the tap that cuts it off (кадр 37-9) has something to cut.
class HeldVoice extends SessionVoice {
  HeldVoice() : super(lines: LineAudioCache(directory: Directory.systemTemp), targetLang: 'en', pronouncer: _QuietPronouncer());

  final List<String> played = [];
  Completer<void>? _holding;

  /// True while a line is sounding.
  bool get speaking => _holding != null && !_holding!.isCompleted;

  /// Let the current line finish by itself.
  void finish() {
    _holding?.complete();
    _holding = null;
  }

  @override
  Future<void> warmUp() async {}

  @override
  Future<void> prepare(Iterable<CardAudio> audios) async {}

  @override
  Future<void> play(CardAudio? audio, {required String fallback, double rate = 1.0, Object? key, bool slowFallback = false}) async {
    played.add('${key ?? audio?.ref ?? fallback}');
    playing.value = key ?? audio?.ref ?? fallback;
    final hold = Completer<void>();
    _holding = hold;
    await hold.future;
    playing.value = null;
  }

  @override
  Future<void> stop() async {
    _holding?.complete();
    _holding = null;
    playing.value = null;
  }

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

/// The talk on a full 390 × 844 screen, animations off, Russian interface. Returns the controller and
/// the microphones the screen made.
typedef TalkStand = ({ConversationController talk, HeldVoice voice, List<SessionMic> mics});

Future<TalkStand> pumpTalk(
  WidgetTester tester,
  TalkProbe probe, {
  Map<String, String> phraseTexts = const {},
  bool hints = true,
  bool open = true,
  VoidCallback? onSummary,
}) async {
  tester.view.physicalSize = const Size(390, 844) * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  final voice = HeldVoice();
  final mics = <SessionMic>[];
  final talk = ConversationController(
    backend: FakeTalkBackend(probe),
    planId: 'ulid-plan',
    day: 1,
    voice: voice,
    hints: hints,
  );
  addTearDown(talk.dispose);
  await tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(),
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
      home: Scaffold(
        backgroundColor: AppColors.ground,
        body: SafeArea(
          bottom: false,
          child: TalkView(
            controller: talk,
            scene: null,
            voice: voice,
            phraseTexts: phraseTexts,
            makeMic: () {
              final mic = SessionMic(
                recognizer: SilentRecognizer(),
                localeId: 'en_US',
                expected: '',
                config: const SpeechTurnConfig(silenceAfterSpeech: ConversationController.silenceClosesTurn),
              );
              mics.add(mic);
              return mic;
            },
            onSummary: onSummary ?? () {},
            onClose: () {},
          ),
        ),
      ),
    ),
  );
  if (open) {
    unawaited(talk.open());
    await tester.pump();
    await tester.pump();
  }
  return (talk: talk, voice: voice, mics: mics);
}

/// The role's line finishes by itself — and the screen is given the frame it takes to notice.
Future<void> finishLine(WidgetTester tester, TalkStand stand) async {
  stand.voice.finish();
  await tester.pump();
  await tester.pump();
}

/// Let the microphone's turn run out: a silent recogniser closes a recording on its own guard, not
/// on the next frame.
Future<void> settleTalk(WidgetTester tester) async {
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(seconds: 5));
  }
  await tester.pump();
}
