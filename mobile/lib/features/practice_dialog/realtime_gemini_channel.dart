import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'dialog_models.dart';
import 'realtime_channel.dart';

/// The Gemini Live API transport (WebSocket), a second [RealtimeChannel] alongside the OpenAI/WebRTC
/// one — selected by [DialogStart.provider]. The shared pipeline (controller, coverage, finish, UI)
/// is unchanged: this only maps Gemini's server messages to the same [TranscriptEvent] / [DialogPhase].
///
/// Protocol (verified against ai.google.dev, Aug 2026): connect to the BidiGenerateContent WS with an
/// ephemeral token (`?access_token=`); send a `setup` with `responseModalities:["AUDIO"]` and both
/// `inputAudioTranscription`/`outputAudioTranscription`; stream mic PCM16/16kHz mono as
/// `realtimeInput.audio` (`mimeType audio/pcm;rate=16000`); play `serverContent.modelTurn.parts[]
/// .inlineData.data` (PCM16/24kHz); read transcripts from `serverContent.input/outputTranscription
/// .text`; flush playback on `serverContent.interrupted` (barge-in).
///
/// Audio I/O is behind [MicCapture]/[PcmPlayback] so this file needs no extra packages; the concrete
/// `record`/`flutter_pcm_sound` impls are injected by the factory once their deps are approved.
class GeminiLiveChannel implements RealtimeChannel {
  GeminiLiveChannel({
    MicCapture Function()? mic,
    PcmPlayback Function()? playback,
    Future<WebSocket> Function(String url, Map<String, dynamic>? headers)? connect,
  })  : _micFactory = mic ?? _silentMic,
        _playbackFactory = playback ?? _silentPlayback,
        _connect = connect ?? _defaultConnect;

  // Ephemeral tokens are ONLY accepted on the Constrained endpoint on v1alpha (the standard
  // BidiGenerateContent endpoint supports API keys / OAuth only → 1008 "unregistered caller").
  static const _endpoint =
      'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained';

  static MicCapture _silentMic() => const _SilentMic();
  static PcmPlayback _silentPlayback() => const _SilentPlayback();
  static Future<WebSocket> _defaultConnect(String url, Map<String, dynamic>? headers) =>
      WebSocket.connect(url, headers: headers);

  // Built lazily in connect() — constructing the real mic/player touches the platform, so the
  // factory can hand out a channel without that cost (and unit tests can construct it freely).
  final MicCapture Function() _micFactory;
  final PcmPlayback Function() _playbackFactory;
  final Future<WebSocket> Function(String url, Map<String, dynamic>? headers) _connect;

  MicCapture? _mic;
  PcmPlayback? _playback;

  final _events = StreamController<TranscriptEvent>.broadcast();
  final _phase = StreamController<DialogPhase>.broadcast();

  WebSocket? _ws;
  StreamSubscription<Uint8List>? _micSub;
  bool _closed = false;
  int _lastTs = 0;

  // Gemini streams the agent's transcription in fragments; buffer them and emit ONE feed/upload
  // line per reply (on turnComplete or interrupted) so the server scores phrase coverage correctly.
  final GeminiOutputAggregator _outAgg = GeminiOutputAggregator();

  @override
  Stream<TranscriptEvent> get events => _events.stream;

  @override
  Stream<DialogPhase> get phase => _phase.stream;

  @override
  Future<void> connect(DialogStart start) async {
    _emitPhase(DialogPhase.connecting);
    _mic = _micFactory();
    _playback = _playbackFactory();

    // The backend sends the exact WS endpoint (bare-token path); fall back to the known one. Auth is
    // the ephemeral token as an `access_token` query param — NOT percent-encoded: the token name
    // ("auth_tokens/…") is query-safe and encoding its `/` to %2F makes Gemini reject it (1008,
    // "unregistered caller").
    // Use the backend endpoint only if it already targets the Constrained (ephemeral-capable) WS;
    // otherwise use the correct one (the backend currently sends the non-ephemeral endpoint).
    final backendEp = start.endpoint;
    final base = (backendEp != null && backendEp.contains('Constrained')) ? backendEp : _endpoint;
    final tok = start.realtimeToken;
    // Auth = ephemeral token as the `access_token` query param (the documented method for the
    // Constrained endpoint). The token name is URL-safe, so appended as-is.
    final sep = base.contains('?') ? '&' : '?';
    final wsUrl = '$base${sep}access_token=$tok';
    final ws = await _connect(wsUrl, null);
    _ws = ws;

    // The backend ships a ready BidiGenerateContentSetup (lesson + model + modalities +
    // transcription) in `connection.setup`; send it AS-IS. No lesson rendering on the client.
    ws.add(jsonEncode(geminiSetupMessage(start)));

    ws.listen(
      _onMessage,
      onDone: () {
        _flushAgentReply(); // upload the last reply before the session closes
        _emitPhase(DialogPhase.closed);
      },
      onError: (_) {
        _flushAgentReply();
        _emitPhase(DialogPhase.closed);
      },
      cancelOnError: true,
    );

    await _playback!.setup();
    _micSub = _mic!.start().listen((chunk) {
      if (_closed || _ws == null) return;
      _ws!.add(jsonEncode({
        'realtimeInput': {
          'audio': {'data': base64Encode(chunk), 'mimeType': 'audio/pcm;rate=16000'},
        },
      }));
    });

    _emitPhase(DialogPhase.listening);
  }

  void _onMessage(dynamic data) {
    if (_closed) return;
    final Map<String, dynamic> json;
    try {
      final text = data is String ? data : utf8.decode((data as List).cast<int>());
      final decoded = jsonDecode(text);
      if (decoded is! Map<String, dynamic>) return;
      json = decoded;
    } catch (_) {
      return;
    }

    final fx = parseGeminiServerMessage(json);
    if (fx.userText != null) {
      _emitEvent(DialogRole.user, fx.userText!);
      _emitPhase(DialogPhase.listening);
    }
    if (fx.modelText != null) {
      _outAgg.add(fx.modelText!, _nextTs()); // buffer the fragment; emit the whole reply on turn end
      _emitPhase(DialogPhase.botSpeaking);
    }
    if (fx.audio != null) {
      _emitPhase(DialogPhase.botSpeaking);
      _playback?.play(fx.audio!);
    }
    if (fx.interrupted) {
      _flushAgentReply(); // emit whatever was said before the barge-in as one line
      _playback?.flush(); // and drop the queued bot audio
      _emitPhase(DialogPhase.listening);
    } else if (fx.turnComplete) {
      _flushAgentReply(); // the reply is complete → one feed/upload line, ts = its start
      _emitPhase(DialogPhase.listening);
    }
  }

  /// Emit the buffered agent reply as a single [TranscriptEvent] (ts = when the reply began).
  void _flushAgentReply() {
    final reply = _outAgg.take();
    if (reply != null) _emitEvent(DialogRole.assistant, reply.text, ts: reply.ts);
  }

  int _nextTs() {
    final now = DateTime.now().millisecondsSinceEpoch;
    _lastTs = now > _lastTs ? now : _lastTs + 1;
    return _lastTs;
  }

  void _emitPhase(DialogPhase p) {
    if (!_closed && !_phase.isClosed) _phase.add(p);
  }

  void _emitEvent(DialogRole role, String text, {int? ts}) {
    final t = text.trim();
    if (t.isEmpty) return;
    if (!_closed && !_events.isClosed) {
      _events.add(TranscriptEvent(role: role, text: t, ts: ts ?? _nextTs()));
    }
  }

  @override
  Future<void> close() async {
    if (_closed) return;
    _closed = true;
    await _micSub?.cancel();
    try {
      await _mic?.stop();
    } catch (_) {}
    try {
      await _playback?.dispose();
    } catch (_) {}
    try {
      await _ws?.close();
    } catch (_) {}
    if (!_events.isClosed) await _events.close();
    if (!_phase.isClosed) await _phase.close();
  }
}

/// The first WS client message. When the backend supplied a ready `BidiGenerateContentSetup` in
/// `connection.setup`, it is forwarded verbatim under the `setup` key — the client never renders the
/// lesson. The fallback (no server setup, e.g. a dev/test connection) is a minimal audio + dual
/// transcription setup so the channel is still usable.
Map<String, dynamic> geminiSetupMessage(DialogStart start) {
  final provided = start.sessionSetup ?? (start.connection?['setup'] as Map<String, dynamic>?);
  if (provided is Map<String, dynamic>) {
    return {'setup': provided}; // as-is, unmodified — the backend rendered the lesson
  }
  return {
    'setup': {
      'model': start.model,
      'generationConfig': {'responseModalities': ['AUDIO']},
      'inputAudioTranscription': <String, dynamic>{},
      'outputAudioTranscription': <String, dynamic>{},
    },
  };
}

/// The effects a single Gemini `serverContent` message carries. Pure + testable: the channel turns
/// these into feed lines, playback and phase changes.
class GeminiServerEffects {
  final String? userText; // serverContent.inputTranscription.text (the user's speech)
  final String? modelText; // serverContent.outputTranscription.text (the bot's speech)
  final Uint8List? audio; // decoded PCM16/24kHz from modelTurn.parts[].inlineData.data
  final bool interrupted; // serverContent.interrupted (barge-in)
  final bool turnComplete; // serverContent.turnComplete

  const GeminiServerEffects({
    this.userText,
    this.modelText,
    this.audio,
    this.interrupted = false,
    this.turnComplete = false,
  });
}

/// Parse one Gemini Live server message into [GeminiServerEffects]. Tolerant of partial messages
/// (any subset of the fields may be present). Pure — no side effects — so it is unit-tested directly.
GeminiServerEffects parseGeminiServerMessage(Map<String, dynamic> json) {
  final sc = json['serverContent'];
  if (sc is! Map<String, dynamic>) return const GeminiServerEffects();

  String? textOf(dynamic node) {
    if (node is Map && node['text'] is String) {
      final t = (node['text'] as String).trim();
      return t.isEmpty ? null : t;
    }
    return null;
  }

  Uint8List? audio;
  final modelTurn = sc['modelTurn'];
  if (modelTurn is Map && modelTurn['parts'] is List) {
    for (final part in modelTurn['parts'] as List) {
      if (part is Map && part['inlineData'] is Map) {
        final inline = part['inlineData'] as Map;
        final d = inline['data'];
        if (d is String && d.isNotEmpty) {
          try {
            audio = base64Decode(d);
          } catch (_) {/* skip a malformed chunk */}
          break;
        }
      }
    }
  }

  return GeminiServerEffects(
    userText: textOf(sc['inputTranscription']),
    modelText: textOf(sc['outputTranscription']),
    audio: audio,
    interrupted: sc['interrupted'] == true,
    turnComplete: sc['turnComplete'] == true,
  );
}

/// Buffers the agent's streamed transcription fragments into one reply. Gemini sends
/// `outputTranscription.text` in chunks; the channel accumulates them and emits a single
/// [TranscriptEvent] per reply (on turnComplete / interrupted) — one feed line, one /transcripts
/// line — so server-side phrase coverage isn't broken by fragmentation. The event's ts is the
/// reply's START (the first fragment's time).
class GeminiOutputAggregator {
  final StringBuffer _buf = StringBuffer();
  int? _startTs;
  bool _endsWithSpace = false;

  // Leading characters that must NOT get a space inserted before them (sentence/clause punctuation,
  // closing brackets/quotes, and the apostrophe so contractions like "I" + "'m" stay "I'm").
  static const _noSpaceBefore = {
    '.', ',', '!', '?', ';', ':', ')', ']', '}', "'", '"', '…', '’', '”', '»', '%',
  };

  static bool _isWs(String c) => c == ' ' || c == '\t' || c == '\n' || c == '\r';

  void add(String chunk, int ts) {
    if (chunk.isEmpty) return;
    _startTs ??= ts; // keep the first fragment's time as the reply start
    // Gemini's fragments have no boundary spaces ("What are" + "your" + "salary"), so glue them
    // with a space UNLESS the seam already has whitespace or the next char is trailing punctuation.
    final first = chunk.substring(0, 1);
    if (_buf.isNotEmpty && !_endsWithSpace && !_isWs(first) && !_noSpaceBefore.contains(first)) {
      _buf.write(' ');
    }
    _buf.write(chunk);
    _endsWithSpace = _isWs(chunk.substring(chunk.length - 1));
  }

  bool get isEmpty => _buf.isEmpty;

  /// Take the accumulated reply (text + start ts) and reset, or null when nothing is buffered.
  ({String text, int ts})? take() {
    if (_buf.isEmpty) return null;
    final out = (text: _buf.toString(), ts: _startTs ?? 0);
    _buf.clear();
    _startTs = null;
    _endsWithSpace = false;
    return out;
  }
}

// ── Audio I/O seam (concrete impls added with the record / flutter_pcm_sound deps) ──

/// Streams microphone audio as PCM16 mono 16kHz chunks (Gemini's required input format).
abstract interface class MicCapture {
  Stream<Uint8List> start();
  Future<void> stop();
}

/// Plays streamed PCM16 mono 24kHz chunks (Gemini's output format); [flush] drops queued audio on
/// a barge-in.
abstract interface class PcmPlayback {
  Future<void> setup();
  void play(Uint8List pcm16le24k);
  Future<void> flush();
  Future<void> dispose();
}

/// No-audio defaults so the channel compiles and its WS + mapping are testable before the audio
/// packages land. Selecting Gemini with these connects + transcribes but is silent.
class _SilentMic implements MicCapture {
  const _SilentMic();
  @override
  Stream<Uint8List> start() => const Stream<Uint8List>.empty();
  @override
  Future<void> stop() async {}
}

class _SilentPlayback implements PcmPlayback {
  const _SilentPlayback();
  @override
  Future<void> setup() async {}
  @override
  void play(Uint8List pcm16le24k) {}
  @override
  Future<void> flush() async {}
  @override
  Future<void> dispose() async {}
}
