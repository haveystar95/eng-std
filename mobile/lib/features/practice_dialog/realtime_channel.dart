import 'dart:async';

import 'dialog_models.dart';

/// The audio/transport seam. In production a `WebRtcRealtimeChannel` (added in the final wiring
/// commit) connects Flutter ↔ OpenAI Realtime directly with the ephemeral token — audio never
/// transits our server. The [DialogController] consumes only this interface, so the scripted
/// [FakeRealtimeChannel] plays a full conversation offline for development and tests.
abstract interface class RealtimeChannel {
  /// Transcribed lines as they arrive (assistant replies + user ASR).
  Stream<TranscriptEvent> get events;

  /// The current phase: bot [DialogPhase.botSpeaking] ↔ [DialogPhase.listening], then
  /// [DialogPhase.closed] when the bot wraps up or the token TTL expires.
  Stream<DialogPhase> get phase;

  /// Open the session using the token from [start]. Emits [DialogPhase.connecting] until connected.
  Future<void> connect(DialogStart start);

  /// Tear down the audio session and streams.
  Future<void> close();
}

/// A scripted channel: weaves a natural back-and-forth out of the dialog's target words so the
/// coverage bar fills as the "user" speaks each one, then the bot wraps up. All content is English
/// (the practised language) — no Cyrillic literal lives in `lib/`. Beat durations are injectable so
/// the integration test can play the whole conversation in a few virtual seconds.
class FakeRealtimeChannel implements RealtimeChannel {
  FakeRealtimeChannel({
    this.connectDelay = const Duration(milliseconds: 400),
    this.botBeat = const Duration(milliseconds: 3200),
    this.listenBeat = const Duration(milliseconds: 3000),
    this.thinkGap = const Duration(milliseconds: 500),
  });

  final Duration connectDelay, botBeat, listenBeat, thinkGap;

  final _events = StreamController<TranscriptEvent>.broadcast();
  final _phase = StreamController<DialogPhase>.broadcast();
  final List<Timer> _timers = [];
  bool _closed = false;
  int _ts = 1; // monotonic, distinct per event (server dedups by ts)

  @override
  Stream<TranscriptEvent> get events => _events.stream;

  @override
  Stream<DialogPhase> get phase => _phase.stream;

  @override
  Future<void> connect(DialogStart start) async {
    _emitPhase(DialogPhase.connecting);
    final words = start.targetWords;

    var t = connectDelay;
    _schedule(t, () {
      _emitPhase(DialogPhase.botSpeaking);
      _emitEvent(DialogRole.assistant, _greeting);
    });
    t += botBeat;

    for (var i = 0; i < words.length; i++) {
      final w = words[i].text;
      _schedule(t, () => _emitPhase(DialogPhase.listening));
      t += listenBeat;
      _schedule(t, () => _emitEvent(DialogRole.user, _userLine(w, i)));
      t += thinkGap;
      _schedule(t, () {
        _emitPhase(DialogPhase.botSpeaking);
        _emitEvent(DialogRole.assistant, _ackLine(w, i));
      });
      t += botBeat;
    }

    _schedule(t, () {
      _emitPhase(DialogPhase.botSpeaking);
      _emitEvent(DialogRole.assistant, _wrapUp);
    });
    t += botBeat;
    _schedule(t, () => _emitPhase(DialogPhase.closed));
  }

  void _schedule(Duration at, void Function() action) {
    _timers.add(
      Timer(at, () {
        if (!_closed) action();
      }),
    );
  }

  void _emitPhase(DialogPhase p) {
    if (!_closed && !_phase.isClosed) _phase.add(p);
  }

  void _emitEvent(DialogRole role, String text) {
    if (!_closed && !_events.isClosed) {
      _events.add(TranscriptEvent(role: role, text: text, ts: _ts++));
    }
  }

  @override
  Future<void> close() async {
    _closed = true;
    for (final t in _timers) {
      t.cancel();
    }
    _timers.clear();
    await _events.close();
    await _phase.close();
  }

  // ── Scripted lines (English — the practised language) ──

  static const _greeting = "Hi! Let's have a quick chat. Tell me what you'd like to do today.";
  static const _wrapUp = "Great talking with you — you handled that really well. Let's stop here.";

  static String _userLine(String word, int i) {
    switch (i % 4) {
      case 0:
        return "I think I'd say $word here.";
      case 1:
        return "Let me try — $word, right?";
      case 2:
        return "Okay, so $word is what I need.";
      default:
        return "Got it, I can use $word for that.";
    }
  }

  static String _ackLine(String word, int i) {
    switch (i % 3) {
      case 0:
        return "Nice — $word works perfectly there.";
      case 1:
        return "Exactly, $word is spot on. What next?";
      default:
        return "Good use of $word. Keep going.";
    }
  }
}
