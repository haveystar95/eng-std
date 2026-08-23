import 'dart:async';

import 'dialog_models.dart';
import 'gemini_audio.dart';
import 'realtime_channel.dart';
import 'realtime_gemini_channel.dart';
import 'realtime_webrtc_channel.dart';

/// Pick the transport for a dialog by its [DialogStart.provider]: `gemini` → Gemini Live WebSocket
/// (with the real mic/PCM audio + AEC), anything else (incl. the default `openai`) → the OpenAI
/// WebRTC channel. Pure and testable — constructing the audio impls does not touch the platform
/// until the channel connects.
RealtimeChannel channelForProvider(String provider) {
  switch (provider) {
    case 'gemini':
      return GeminiLiveChannel(mic: RecordMicCapture.new, playback: PcmSoundPlayback.new);
    case 'openai':
    default:
      return WebRtcRealtimeChannel();
  }
}

/// A transparent [RealtimeChannel] that defers the provider choice until [connect], where the
/// [DialogStart] (and thus its provider) is known. Lets the controller keep taking a single channel
/// instance while still selecting OpenAI vs Gemini per dialog — the OpenAI path is unchanged, just
/// forwarded. Streams from the chosen inner channel are piped through unchanged.
class DispatchingRealtimeChannel implements RealtimeChannel {
  DispatchingRealtimeChannel({RealtimeChannel Function(String provider)? select})
    : _select = select ?? channelForProvider;

  final RealtimeChannel Function(String provider) _select;

  final _events = StreamController<TranscriptEvent>.broadcast();
  final _phase = StreamController<DialogPhase>.broadcast();
  RealtimeChannel? _inner;
  StreamSubscription<TranscriptEvent>? _eSub;
  StreamSubscription<DialogPhase>? _pSub;
  bool _closed = false;

  @override
  Stream<TranscriptEvent> get events => _events.stream;

  @override
  Stream<DialogPhase> get phase => _phase.stream;

  @override
  Future<void> connect(DialogStart start) async {
    final inner = _select(start.provider);
    _inner = inner;
    _eSub = inner.events.listen((e) {
      if (!_events.isClosed) _events.add(e);
    });
    _pSub = inner.phase.listen((p) {
      if (!_phase.isClosed) _phase.add(p);
    });
    await inner.connect(start);
  }

  @override
  Future<void> close() async {
    if (_closed) return;
    _closed = true;
    await _eSub?.cancel();
    await _pSub?.cancel();
    await _inner?.close();
    if (!_events.isClosed) await _events.close();
    if (!_phase.isClosed) await _phase.close();
  }
}
