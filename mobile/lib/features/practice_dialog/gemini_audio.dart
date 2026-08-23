import 'dart:async';
import 'dart:typed_data';

import 'package:flutter_pcm_sound/flutter_pcm_sound.dart';
// Reused only for its AVAudioSession output-port override (route to the loudspeaker); no WebRTC
// peer connection is involved on the Gemini path.
import 'package:flutter_webrtc/flutter_webrtc.dart' show Helper;
// record also exports an `IosAudioCategory` (for its own session mgmt); we use flutter_pcm_sound's.
import 'package:record/record.dart' hide IosAudioCategory;

import 'realtime_gemini_channel.dart';

/// Microphone → PCM16 mono 16kHz stream for the Gemini WS input, with acoustic echo cancellation.
///
/// AEC: the raw pipeline (unlike WebRTC) has no built-in echo suppression, so the bot's voice from
/// the loudspeaker would leak into the mic → junk user transcripts + false barge-ins. `echoCancel:
/// true` makes `record` use the iOS voice-processing audio unit, whose AEC references the device's
/// output mix (including the [PcmSoundPlayback] audio, since both share a playAndRecord session) and
/// cancels it — the same class of solution WebRTC used. Keeping the mic live (not gated) preserves
/// barge-in. NB: two separate audio units (capture vs playback) means this needs on-device
/// confirmation; if residual echo remains, the fallback is to duck/ignore the mic while playing —
/// which weakens barge-in — and is intentionally NOT applied here.
class RecordMicCapture implements MicCapture {
  final AudioRecorder _rec = AudioRecorder();

  static const _config = RecordConfig(
    encoder: AudioEncoder.pcm16bits,
    sampleRate: 16000,
    numChannels: 1,
    echoCancel: true,
    noiseSuppress: true,
    autoGain: true,
  );

  @override
  Stream<Uint8List> start() => Stream.fromFuture(_rec.startStream(_config)).asyncExpand((s) => s);

  @override
  Future<void> stop() async {
    try {
      await _rec.stop();
    } catch (_) {}
    await _rec.dispose();
  }
}

/// Streamed PCM16 mono 24kHz playback of the model's audio (push model: feed each chunk as it
/// arrives). Uses the `playAndRecord` category so it shares the capture session instead of forcing
/// `playback` (which would kill recording + AEC).
class PcmSoundPlayback implements PcmPlayback {
  bool _ready = false;
  bool _routedToSpeaker = false;

  @override
  Future<void> setup() async {
    await FlutterPcmSound.setup(
      sampleRate: 24000,
      channelCount: 1,
      iosAudioCategory: IosAudioCategory.playAndRecord,
    );
    FlutterPcmSound.setFeedCallback((_) {}); // push model — we feed on arrival, no pull needed
    _ready = true;
  }

  @override
  void play(Uint8List pcm16le24k) {
    if (!_ready || pcm16le24k.isEmpty) return;
    if (!_routedToSpeaker) {
      // Voice-processing (echoCancel) defaults playAndRecord to the earpiece (receiver), like a
      // call — force the loudspeaker so the bot is audible. Done on first playback (session active,
      // after the mic started); overrides only the output PORT, so the AEC is preserved. Reasserted
      // after each flush() re-setup.
      _routedToSpeaker = true;
      unawaited(Helper.setSpeakerphoneOn(true));
    }
    unawaited(FlutterPcmSound.feed(PcmArrayInt16(bytes: ByteData.sublistView(pcm16le24k))));
  }

  @override
  Future<void> flush() async {
    // No clear() API in flutter_pcm_sound — release() drops the queued native buffer immediately
    // (that's the barge-in stop), then we re-setup so the next turn can play.
    if (!_ready) return;
    _ready = false;
    _routedToSpeaker = false; // re-route to speaker on the next play after re-setup
    try {
      await FlutterPcmSound.release();
    } catch (_) {}
    await setup();
  }

  @override
  Future<void> dispose() async {
    _ready = false;
    try {
      await FlutterPcmSound.release();
    } catch (_) {}
  }
}
