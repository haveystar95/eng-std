import 'dart:async';

import 'package:flutter/foundation.dart';

import 'package:eng_std/theme/feedback.dart' show SessionSounds;

import '../../../data/speech/speech_diagnostics.dart';
import '../../../data/speech/speech_recognizer.dart';
import '../../../data/speech/speech_turn.dart';

/// State of the card's microphone (canvas 30-3).
enum MicState {
  /// Idle — «tap to speak».
  idle,

  /// Recording is on: empty (caret) or text is coming in (live line).
  listening,

  /// Heard — pass: the button in sage with a check mark.
  heard,

  /// Didn't catch it (or the wrong thing was said) — «once more».
  missed,

  /// No microphone: no permission or the recognizer did not start — the «Microphone needed» screen.
  unavailable,
}

/// How one recording ended.
typedef MicTurn = ({String transcript, SpeechTurnOutcome outcome});

/// SESSION CARD MICROPHONE (work order SESSION-1b, canvas 30-3) — on top of the existing recording engine
/// ([SpeechTurn]: recording only on a tap, a length guard, gluing of recognizer chunks). Here only the button state
/// and the live line; the card computes the pass from [onTurn] and answers with [settle].
///
/// RECORD UNTIL THE PAUSE, NOT UNTIL THE KEY (work order SESSION-2a §3): a recording is closed only by a pause in the
/// speech ([turnConfig] — 1 s of silence after the last word) or by a tap on the button, and only then is the whole
/// of what was said handed to the card. The partial result is for the screen and never decides anything: an early
/// stop on the first covered key cut the owner mid-sentence («smaller» in «Answer in your own words», the first of
/// two sentences in «Say what you heard»). The pass rule did not change — the moment it is applied did.
///
/// Debug door [submitDebug] — the debug build's «what was heard» field on the simulator, where there is no
/// microphone: the text takes the same road — the pause, then the final transcript.
class SessionMic extends ChangeNotifier {
  SessionMic({
    required this._recognizer,
    this._diagnostics,
    required this.localeId,
    required this.expected,
    this.contextualStrings = const [],
  });

  final SpeechRecognizer _recognizer;
  final SpeechDiagnostics? _diagnostics;

  /// Recognition locale — `en_US`.
  final String localeId;

  /// What should be said — a hint to the engine and the reference for the live line (for «Your slot» a chip
  /// changes it).
  String expected;

  /// The card's words — `SFSpeechRecognitionRequest.contextualStrings`.
  final List<String> contextualStrings;

  /// The recording is closed — the card judges and calls [settle].
  void Function(MicTurn turn)? onTurn;

  /// ONE RULE FOR EVERY MICROPHONE OF THE APP (FIX-1 §3) — a second of silence after the speech, or a tap; the
  /// numbers live in [SpeechTurnConfig]'s own defaults, so the session, the collections trainer and the intro echo
  /// cannot drift apart again.
  static const SpeechTurnConfig turnConfig = SpeechTurnConfig();

  MicState _state = MicState.idle;
  String _partial = '';
  double _level = 0;
  bool _blockedInSettings = false;
  bool _closed = false;
  SpeechTurn? _turn;
  bool _disposed = false;
  Timer? _debugClose;

  MicState get state => _state;

  /// The recording is closed and awaits the verdict (the slot judge answers over the network): the line is frozen,
  /// the button does not pulse.
  bool get closed => _closed;

  /// What has been heard so far (live line) or the recording's result.
  String get partial => _partial;

  /// Loudness 0…1 — only while recording.
  double get level => _level;

  /// Permission denied for good: only «Settings» can help.
  bool get blockedInSettings => _blockedInSettings;

  bool get isListening => _state == MicState.listening;

  /// Tap on the button: idle / «once more» — start recording; recording — close it with what has been heard.
  Future<void> tap() async {
    switch (_state) {
      case MicState.listening:
        if (_closed) return;
        await _turn?.stop();
      case MicState.idle || MicState.missed:
        await _listen();
      case MicState.heard || MicState.unavailable:
        break;
    }
  }

  Future<void> _listen() async {
    _partial = '';
    _level = 0;
    _closed = false;
    _set(MicState.listening);
    SessionSounds.play(SessionSounds.micOn);
    final turn = SpeechTurn(_recognizer, config: turnConfig, diagnostics: _diagnostics);
    _turn = turn;
    SpeechTurnResult result;
    try {
      result = await turn.listen(
        expected: [if (expected.trim().isNotEmpty) expected],
        localeId: localeId,
        contextualStrings: contextualStrings,
        onPartial: (text) {
          if (_turn != turn) return;
          _partial = text;
          _notify();
        },
        // iOS reports decibels roughly from −2 to 10.
        onLevel: (db) {
          if (_turn != turn) return;
          _level = ((db + 2) / 12).clamp(0.0, 1.0);
          _notify();
        },
      );
    } catch (e) {
      result = const SpeechTurnResult(SpeechTurnOutcome.unavailable);
    }
    if (_disposed || _turn != turn) return;
    _turn = null;
    _level = 0;
    if (result.outcome == SpeechTurnOutcome.unavailable) {
      await _refreshPermission();
      if (_disposed) return;
      _set(MicState.unavailable);
      return;
    }
    _partial = result.transcript;
    _closed = true;
    _notify();
    onTurn?.call((transcript: result.transcript, outcome: result.outcome));
  }

  /// The card's verdict on a closed recording.
  void settle({required bool accepted}) {
    _closed = false;
    if (accepted) {
      _set(MicState.heard);
    } else {
      _set(MicState.missed);
    }
  }

  /// Return the button to idle (a new attempt after the judge's rejection, etc.).
  void reset() {
    _partial = '';
    _closed = false;
    _set(MicState.idle);
  }

  /// «Allow» on the «Microphone needed» screen: ask the system again.
  Future<bool> askAgain() async {
    final ok = await _recognizer.prepare();
    if (_disposed) return ok;
    if (ok) {
      _blockedInSettings = false;
      _set(MicState.idle);
    } else {
      await _refreshPermission();
    }
    return ok;
  }

  Future<void> _refreshPermission() async {
    final probe = await _diagnostics?.refresh(localeId);
    _blockedInSettings = probe?.blockedInSettings ?? false;
  }

  /// DEBUG FIELD «WHAT WAS HEARD» (debug build only): the text acts as a partial result that no longer changes: the
  /// recording closes after the pause of [turnConfig], then the final transcript goes to the card.
  void submitDebug(String text) {
    if (!kDebugMode) return;
    final heard = text.trim();
    if (heard.isEmpty || _state == MicState.heard) return;
    final turn = _turn;
    _turn = null;
    if (turn != null) unawaited(turn.cancel());
    _debugClose?.cancel();
    _partial = heard;
    _level = 0;
    _closed = false;
    _set(MicState.listening);
    SessionSounds.play(SessionSounds.micOn);
    _debugClose = Timer(turnConfig.silenceAfterSpeech, () {
      if (_disposed) return;
      _closed = true;
      _notify();
      onTurn?.call((transcript: heard, outcome: SpeechTurnOutcome.heard));
    });
  }

  void _set(MicState s) {
    if (_disposed) return;
    _state = s;
    notifyListeners();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _debugClose?.cancel();
    final turn = _turn;
    _turn = null;
    if (turn != null) unawaited(turn.cancel());
    super.dispose();
  }
}
