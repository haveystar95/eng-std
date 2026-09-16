import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/speech_coverage.dart';
import 'package:eng_std/data/plan/session/speech_stop.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/data/speech/speech_turn.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';

/// A recognizer the test drives: it opens, the test «says» partial results, `stop` hands back the last one.
class _DrivenRecognizer implements SpeechRecognizer {
  Completer<SpeechAttempt>? _pending;
  ValueChanged<String>? _onPartial;
  String _last = '';
  int stops = 0;

  @override
  bool get isReady => true;

  @override
  Future<bool> get hasPermission async => true;

  @override
  Future<bool> prepare() async => true;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) {
    _onPartial = onPartial;
    final completer = Completer<SpeechAttempt>();
    _pending = completer;
    return completer.future;
  }

  void say(String text) {
    _last = text;
    _onPartial?.call(text);
  }

  @override
  Future<void> stop() async {
    stops++;
    final pending = _pending;
    _pending = null;
    if (pending != null && !pending.isCompleted) pending.complete(SpeechAttempt.heard(_last));
  }

  @override
  Future<void> cancel() async {
    final pending = _pending;
    _pending = null;
    if (pending != null && !pending.isCompleted) pending.complete(const SpeechAttempt.silent());
  }
}

/// THE PHONE'S ROAD OF AN EARLY STOP (SESSION-1b′, item 6): the recognizer's partial results go through [SessionMic]
/// and [SpeechTurn]; once the card's [SessionMic.autoStop] says the partial result passes, an unchanged partial
/// result stops the recording after that pause — without waiting for silence.
void main() {
  final en = SpeechCoverage.articlesFor('en');

  ({SessionMic mic, _DrivenRecognizer recognizer, List<MicTurn> turns}) micFor(
    String expected,
    Duration? Function(String partial) autoStop,
  ) {
    final recognizer = _DrivenRecognizer();
    final turns = <MicTurn>[];
    final mic = SessionMic(recognizer: recognizer, localeId: 'en_US', expected: expected)
      ..onTurn = turns.add
      ..autoStop = autoStop;
    addTearDown(mic.dispose);
    return (mic: mic, recognizer: recognizer, turns: turns);
  }

  // CATCHES: a covered phrase that still waits for 2 s of silence (the phone felt slow) and a stop that ignores a
  // partial result still changing.
  testWidgets('voice: covered → stop 500 ms after the last change of the partial result', (tester) async {
    final m = micFor('lower back', (partial) => SpeechStop.voice(partial, (h) => SpeechCoverage.covers(h, 'lower back', 1.0, en)));
    unawaited(m.mic.tap());
    await tester.pump();
    expect(m.mic.state, MicState.listening);

    m.recognizer.say('lower');
    await tester.pump(const Duration(milliseconds: 600));
    expect(m.recognizer.stops, 0, reason: 'not covered — no early stop');

    m.recognizer.say('lower back');
    await tester.pump(const Duration(milliseconds: 300));
    m.recognizer.say('lower back please');
    await tester.pump(const Duration(milliseconds: 300));
    expect(m.turns, isEmpty, reason: 'the partial result changed — the 500 ms start over');
    await tester.pump(const Duration(milliseconds: 190));
    expect(m.turns, isEmpty);
    await tester.pump(const Duration(milliseconds: 20));
    await tester.pump();
    expect(m.recognizer.stops, 1);
    expect(m.turns.single.outcome, SpeechTurnOutcome.heard);
    expect(m.turns.single.transcript, 'lower back please');
  });

  testWidgets('voice: never covered — no early stop, the recording stays open', (tester) async {
    final m = micFor('lower back', (partial) => SpeechStop.voice(partial, (h) => SpeechCoverage.covers(h, 'lower back', 1.0, en)));
    unawaited(m.mic.tap());
    await tester.pump();
    m.recognizer.say('lower');
    await tester.pump(const Duration(milliseconds: 1900));
    expect(m.turns, isEmpty);
    expect(m.recognizer.stops, 0);
    expect(m.mic.state, MicState.listening, reason: 'only silence (or a tap) closes an uncovered recording');
    await m.mic.tap();
    await tester.pump();
    expect(m.turns.single.transcript, 'lower');
  });

  // CATCHES: the judge asked on the frame alone.
  testWidgets('judge: the frame and a word after it → stop after 800 ms', (tester) async {
    final m = micFor('It started', (partial) => SpeechStop.judge(partial, 'It started', 1.0, en));
    unawaited(m.mic.tap());
    await tester.pump();
    m.recognizer.say('It started');
    await tester.pump(const Duration(milliseconds: 1000));
    expect(m.turns, isEmpty, reason: 'the frame alone — keep listening');

    m.recognizer.say('It started last night');
    await tester.pump(const Duration(milliseconds: 790));
    expect(m.turns, isEmpty);
    await tester.pump(const Duration(milliseconds: 20));
    await tester.pump();
    expect(m.turns.single.transcript, 'It started last night');
  });
}
