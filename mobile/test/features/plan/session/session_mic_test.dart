import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

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

/// RECORD UNTIL THE PAUSE, NOT UNTIL THE KEY (work order SESSION-2a §3): the recognizer's partial results go through
/// [SessionMic] and [SpeechTurn] to the screen only; the recording closes on 1 s of silence after the last word or on a
/// tap, and only then is the whole transcript handed to the card.
void main() {
  ({SessionMic mic, _DrivenRecognizer recognizer, List<MicTurn> turns}) micFor(String expected) {
    final recognizer = _DrivenRecognizer();
    final turns = <MicTurn>[];
    final mic = SessionMic(recognizer: recognizer, localeId: 'en_US', expected: expected)..onTurn = turns.add;
    addTearDown(mic.dispose);
    return (mic: mic, recognizer: recognizer, turns: turns);
  }

  // CATCHES: «Answer in your own words» accepting «smaller» mid-sentence and cutting the owner off (17.09).
  testWidgets('recording until the pause, not until the key: a covered key keeps the recording open', (tester) async {
    final m = micFor('smaller');
    unawaited(m.mic.tap());
    await tester.pump();
    expect(m.mic.state, MicState.listening);

    m.recognizer.say('I would like a smaller');
    await tester.pump(const Duration(milliseconds: 900));
    expect(m.turns, isEmpty, reason: 'the key is covered, but the speaker has not paused yet');
    expect(m.recognizer.stops, 0);

    m.recognizer.say('I would like a smaller room with a view');
    await tester.pump(const Duration(milliseconds: 990));
    expect(m.turns, isEmpty, reason: 'the pause is counted from the LAST word');
    await tester.pump(const Duration(milliseconds: 20));
    await tester.pump();
    expect(m.turns.single.outcome, SpeechTurnOutcome.heard);
    expect(m.turns.single.transcript, 'I would like a smaller room with a view', reason: 'the whole of it goes to grading');
  });

  // CATCHES: «Say what you heard» taking the first of two sentences.
  testWidgets('recording until the pause: two sentences with a short breath between them are one answer', (tester) async {
    final m = micFor('It is near the station. The rent is low.');
    unawaited(m.mic.tap());
    await tester.pump();
    m.recognizer.say('It is near the station.');
    await tester.pump(const Duration(milliseconds: 600));
    m.recognizer.say('It is near the station. The rent is low.');
    await tester.pump(const Duration(milliseconds: 1010));
    await tester.pump();
    expect(m.turns.single.transcript, 'It is near the station. The rent is low.');
  });

  testWidgets('recording until the pause: a tap closes it at once with what was heard', (tester) async {
    final m = micFor('lower back');
    unawaited(m.mic.tap());
    await tester.pump();
    m.recognizer.say('lower');
    await tester.pump(const Duration(milliseconds: 300));
    await m.mic.tap();
    await tester.pump();
    expect(m.recognizer.stops, 1);
    expect(m.turns.single.transcript, 'lower');
  });

  testWidgets('recording until the pause: silence before the first word never closes it', (tester) async {
    final m = micFor('lower back');
    unawaited(m.mic.tap());
    await tester.pump();
    await tester.pump(const Duration(seconds: 4));
    expect(m.turns, isEmpty);
    expect(m.mic.state, MicState.listening);
    await m.mic.tap();
    await tester.pump();
  });

  test('the session pause is about one second, with no floor after the first word', () {
    expect(SessionMic.turnConfig.silenceAfterSpeech.inMilliseconds, inInclusiveRange(900, 1000));
    expect(SessionMic.turnConfig.minWaitBeforeSilence, Duration.zero);
  });
}
