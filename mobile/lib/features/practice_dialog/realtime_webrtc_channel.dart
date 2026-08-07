import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

import 'dialog_models.dart';
import 'realtime_channel.dart';

/// Real transport: connects Flutter ↔ OpenAI Realtime over WebRTC with the ephemeral token from
/// [DialogStart]. Audio streams directly to OpenAI (never through our server); transcript + speaking
/// state arrive on the `oai-events` data channel.
///
/// Handshake (per the OpenAI Realtime WebRTC guide): capture the mic → add the track → open the
/// `oai-events` data channel → create an SDP offer → POST it to `/v1/realtime/calls` with the
/// ephemeral token → apply the SDP answer. The model, instructions (the lesson), target language and
/// transcription/VAD are all baked into the token server-side, so this client stays thin.
class WebRtcRealtimeChannel implements RealtimeChannel {
  WebRtcRealtimeChannel({Dio? http}) : _http = http ?? Dio();

  static const _callsEndpoint = 'https://api.openai.com/v1/realtime/calls';

  final Dio _http;
  final _events = StreamController<TranscriptEvent>.broadcast();
  final _phase = StreamController<DialogPhase>.broadcast();

  RTCPeerConnection? _pc;
  RTCDataChannel? _dc;
  MediaStream? _mic;
  bool _closed = false;
  bool _greeted = false;
  int _lastTs = 0;

  @override
  Stream<TranscriptEvent> get events => _events.stream;

  @override
  Stream<DialogPhase> get phase => _phase.stream;

  @override
  Future<void> connect(DialogStart start) async {
    _emitPhase(DialogPhase.connecting);

    // 1. Mic capture (permission already granted for voice fill) + route audio to the speaker.
    _mic = await navigator.mediaDevices.getUserMedia({'audio': true, 'video': false});

    // 2. Peer connection; play the bot's remote audio track automatically.
    final pc = await createPeerConnection({
      'iceServers': [
        {'urls': 'stun:stun.l.google.com:19302'},
      ],
      'sdpSemantics': 'unified-plan',
    });
    _pc = pc;
    pc.onConnectionState = (state) {
      if (state == RTCPeerConnectionState.RTCPeerConnectionStateClosed ||
          state == RTCPeerConnectionState.RTCPeerConnectionStateFailed ||
          state == RTCPeerConnectionState.RTCPeerConnectionStateDisconnected) {
        _emitPhase(DialogPhase.closed);
      }
    };
    for (final track in _mic!.getAudioTracks()) {
      await pc.addTrack(track, _mic!);
    }

    // 3. Events data channel. Once it opens, kick the bot to greet first (response.create) — the
    // greeting instruction lives in the backend's session prompt, so the user speaks nothing to start.
    final dc = await pc.createDataChannel('oai-events', RTCDataChannelInit());
    _dc = dc;
    dc.onMessage = (msg) => _onServerEvent(msg.text);
    dc.onDataChannelState = (state) {
      if (state == RTCDataChannelState.RTCDataChannelOpen) _greet();
    };

    // 4. Offer → POST to /v1/realtime/calls with the ephemeral token → answer.
    final offer = await pc.createOffer({});
    await pc.setLocalDescription(offer);

    final Response<String> resp;
    try {
      resp = await _http.post<String>(
        _callsEndpoint,
        data: offer.sdp,
        options: Options(
          contentType: 'application/sdp',
          responseType: ResponseType.plain,
          headers: {'Authorization': 'Bearer ${start.realtimeToken}'},
        ),
      );
    } on DioException {
      await close();
      rethrow; // the controller maps this to a network error
    }
    if (_closed) return;
    final answerSdp = resp.data;
    if (answerSdp == null || answerSdp.isEmpty) {
      await close();
      throw StateError('empty SDP answer from realtime/calls');
    }
    await pc.setRemoteDescription(RTCSessionDescription(answerSdp, 'answer'));

    // Force the earpiece → loudspeaker so the bot is audible in a conversation.
    await Helper.setSpeakerphoneOn(true);

    // Baseline once connected; server events flip it as the bot greets / the user speaks.
    _emitPhase(DialogPhase.listening);
  }

  /// Map OpenAI Realtime server events (JSON on the data channel) to feed lines + speaking state.
  void _onServerEvent(String raw) {
    if (_closed) return;
    final Map<String, dynamic> j;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic>) return;
      j = decoded;
    } catch (_) {
      return;
    }
    final type = (j['type'] as String?) ?? '';

    // Final transcripts → the feed.
    if (type == 'conversation.item.input_audio_transcription.completed') {
      final t = (j['transcript'] as String?)?.trim();
      if (t != null && t.isNotEmpty) _emitEvent(DialogRole.user, t);
      return;
    }
    if (type.contains('audio_transcript') && type.endsWith('.done')) {
      final t = (j['transcript'] as String?)?.trim();
      if (t != null && t.isNotEmpty) _emitEvent(DialogRole.assistant, t);
      return;
    }

    // Speaking ↔ listening state.
    switch (type) {
      case 'output_audio_buffer.started':
      case 'response.created':
        _emitPhase(DialogPhase.botSpeaking);
      case 'output_audio_buffer.stopped':
      case 'response.done':
      case 'input_audio_buffer.speech_started':
        _emitPhase(DialogPhase.listening);
    }
  }

  /// Ask the model to speak first, once, as soon as the events channel is open.
  void _greet() {
    if (_closed || _greeted) return;
    _greeted = true;
    _emitPhase(DialogPhase.botSpeaking);
    _dc?.send(RTCDataChannelMessage(jsonEncode({'type': 'response.create'})));
  }

  int _nextTs() {
    final now = DateTime.now().millisecondsSinceEpoch;
    _lastTs = now > _lastTs ? now : _lastTs + 1; // monotonic + distinct (server dedups by role,ts)
    return _lastTs;
  }

  void _emitPhase(DialogPhase p) {
    if (!_closed && !_phase.isClosed) _phase.add(p);
  }

  void _emitEvent(DialogRole role, String text) {
    if (!_closed && !_events.isClosed) {
      _events.add(TranscriptEvent(role: role, text: text, ts: _nextTs()));
    }
  }

  @override
  Future<void> close() async {
    if (_closed) return;
    _closed = true;
    try {
      await _dc?.close();
    } catch (_) {}
    try {
      for (final t in _mic?.getTracks() ?? const []) {
        await t.stop();
      }
      await _mic?.dispose();
    } catch (_) {}
    try {
      await _pc?.close();
    } catch (_) {}
    if (!_events.isClosed) await _events.close();
    if (!_phase.isClosed) await _phase.close();
  }
}
