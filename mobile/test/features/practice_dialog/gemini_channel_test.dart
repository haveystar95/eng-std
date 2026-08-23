import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/practice_dialog/dialog_models.dart';
import 'package:eng_std/features/practice_dialog/realtime_channel.dart';
import 'package:eng_std/features/practice_dialog/realtime_channel_factory.dart';
import 'package:eng_std/features/practice_dialog/realtime_gemini_channel.dart';
import 'package:eng_std/features/practice_dialog/realtime_webrtc_channel.dart';

/// The Gemini transport is additive: the factory picks it by `provider`, the dispatcher forwards
/// transparently, and its server-message mapping lands both roles' transcripts + barge-in. The
/// OpenAI/WebRTC path stays the default.
void main() {
  group('channelForProvider', () {
    test('gemini → GeminiLiveChannel', () {
      expect(channelForProvider('gemini'), isA<GeminiLiveChannel>());
    });
    test('openai → WebRtcRealtimeChannel', () {
      expect(channelForProvider('openai'), isA<WebRtcRealtimeChannel>());
    });
    test('unknown/default → WebRtcRealtimeChannel (OpenAI path unchanged)', () {
      expect(channelForProvider('something-else'), isA<WebRtcRealtimeChannel>());
    });
  });

  test('DispatchingRealtimeChannel selects by provider and forwards the inner streams', () async {
    final inner = _ImmediateChannel();
    String? picked;
    final dispatcher = DispatchingRealtimeChannel(
      select: (p) {
        picked = p;
        return inner;
      },
    );

    final events = <TranscriptEvent>[];
    final phases = <DialogPhase>[];
    dispatcher.events.listen(events.add);
    dispatcher.phase.listen(phases.add);

    await dispatcher.connect(_start(provider: 'gemini'));
    await Future<void>.delayed(Duration.zero); // let the broadcast deliver

    expect(picked, 'gemini');
    expect(events.map((e) => e.text), contains('hi from inner'));
    expect(phases, contains(DialogPhase.botSpeaking));
    await dispatcher.close();
  });

  group('geminiSetupMessage', () {
    test('forwards the backend BidiGenerateContentSetup from connection AS-IS', () {
      final setup = {
        'model': 'models/gemini-live',
        'generationConfig': {
          'responseModalities': ['AUDIO'],
        },
        'systemInstruction': {
          'parts': [
            {'text': 'You are a boxing coach. Use these words: jab, hook.'},
          ],
        },
        'inputAudioTranscription': <String, dynamic>{},
        'outputAudioTranscription': <String, dynamic>{},
      };
      final msg = geminiSetupMessage(_start(provider: 'gemini', sessionSetup: setup));
      // First WS message is exactly {setup: <backend setup>}, unmodified — no client lesson render.
      expect(msg.keys, ['setup']);
      expect(msg['setup'], same(setup));
      expect(msg['setup'], equals(setup));
    });

    test('falls back to a minimal audio+transcription setup when none is provided', () {
      final msg = geminiSetupMessage(_start(provider: 'gemini'));
      final setup = msg['setup'] as Map<String, dynamic>;
      expect((setup['generationConfig'] as Map)['responseModalities'], ['AUDIO']);
      expect(setup.containsKey('inputAudioTranscription'), isTrue);
      expect(setup.containsKey('outputAudioTranscription'), isTrue);
    });
  });

  group('parseGeminiServerMessage', () {
    test('user speech → userText (listening side)', () {
      final fx = parseGeminiServerMessage({
        'serverContent': {
          'inputTranscription': {'text': 'i need to withdraw money'},
        },
      });
      expect(fx.userText, 'i need to withdraw money');
      expect(fx.modelText, isNull);
    });

    test('model speech → modelText (bot side)', () {
      final fx = parseGeminiServerMessage({
        'serverContent': {
          'outputTranscription': {'text': 'Sure, let us practice.'},
        },
      });
      expect(fx.modelText, 'Sure, let us practice.');
      expect(fx.userText, isNull);
    });

    test('interrupted flag = barge-in', () {
      final fx = parseGeminiServerMessage({
        'serverContent': {'interrupted': true},
      });
      expect(fx.interrupted, isTrue);
    });

    test('turnComplete flag', () {
      final fx = parseGeminiServerMessage({
        'serverContent': {'turnComplete': true},
      });
      expect(fx.turnComplete, isTrue);
    });

    test('output audio inlineData is decoded to PCM bytes', () {
      final pcm = Uint8List.fromList([1, 2, 3, 4, 250, 12]);
      final fx = parseGeminiServerMessage({
        'serverContent': {
          'modelTurn': {
            'parts': [
              {
                'inlineData': {'mimeType': 'audio/pcm;rate=24000', 'data': base64Encode(pcm)},
              },
            ],
          },
        },
      });
      expect(fx.audio, isNotNull);
      expect(fx.audio, equals(pcm));
    });

    test('a message with no serverContent yields no effects', () {
      final fx = parseGeminiServerMessage({'setupComplete': <String, dynamic>{}});
      expect(fx.userText, isNull);
      expect(fx.modelText, isNull);
      expect(fx.audio, isNull);
      expect(fx.interrupted, isFalse);
      expect(fx.turnComplete, isFalse);
    });
  });

  group('GeminiOutputAggregator — agent transcription is one line per reply', () {
    test('a sequence of fragments becomes one reply; ts = the first fragment', () {
      final agg = GeminiOutputAggregator();
      agg.add('I think ', 100);
      agg.add('you should ', 101);
      agg.add('throw a punch.', 102);
      final reply = agg.take();
      expect(reply, isNotNull);
      expect(reply!.text, 'I think you should throw a punch.');
      expect(reply.ts, 100); // reply start, not the last fragment
      expect(agg.take(), isNull); // drained after take
    });

    test('interrupted mid-reply yields one event with the partial text', () {
      final agg = GeminiOutputAggregator();
      agg.add('Let me tell you about ', 200);
      agg.add('spar', 201);
      final partial = agg.take(); // interrupted → flush what we have
      expect(partial, isNotNull);
      expect(partial!.text, 'Let me tell you about spar');
      expect(partial.ts, 200);
    });

    test('fragments with no boundary spaces are joined WITH spaces', () {
      final agg = GeminiOutputAggregator();
      agg.add('What are', 1);
      agg.add('your', 2);
      agg.add('salary', 3);
      agg.add('expectations?', 4);
      expect(agg.take()!.text, 'What are your salary expectations?');
    });

    test('fragments that already carry spaces are not double-spaced', () {
      final a = GeminiOutputAggregator()
        ..add('Hello ', 1)
        ..add('there', 2);
      expect(a.take()!.text, 'Hello there');
      final b = GeminiOutputAggregator()
        ..add('Hello', 1)
        ..add(' there', 2);
      expect(b.take()!.text, 'Hello there');
    });

    test('no space is inserted before trailing punctuation or a contraction', () {
      final a = GeminiOutputAggregator()
        ..add('Nice', 1)
        ..add('.', 2);
      expect(a.take()!.text, 'Nice.');
      final b = GeminiOutputAggregator()
        ..add('It', 1)
        ..add("'s", 2)
        ..add('good', 3);
      expect(b.take()!.text, "It's good");
    });

    test('take with nothing buffered is null', () {
      expect(GeminiOutputAggregator().take(), isNull);
    });
  });
}

DialogStart _start({required String provider, Map<String, dynamic>? sessionSetup}) => DialogStart(
  dialogId: 'x',
  realtimeToken: 'tok',
  expiresAt: DateTime(2026, 8, 7, 12),
  model: 'm',
  targetWords: const [],
  durationSeconds: 200,
  provider: provider,
  sessionSetup: sessionSetup,
);

/// A channel that emits one phase + one transcript on connect, to prove the dispatcher forwards.
class _ImmediateChannel implements RealtimeChannel {
  final _e = StreamController<TranscriptEvent>.broadcast();
  final _p = StreamController<DialogPhase>.broadcast();

  @override
  Stream<TranscriptEvent> get events => _e.stream;
  @override
  Stream<DialogPhase> get phase => _p.stream;

  @override
  Future<void> connect(DialogStart start) async {
    _p.add(DialogPhase.botSpeaking);
    _e.add(TranscriptEvent(role: DialogRole.assistant, text: 'hi from inner', ts: 1));
  }

  @override
  Future<void> close() async {
    await _e.close();
    await _p.close();
  }
}
