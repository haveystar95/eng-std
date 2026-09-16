import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/speech_coverage.dart';
import 'package:eng_std/data/plan/session/speech_stop.dart';

/// WHEN TO STOP A RECORDING (SESSION-1b′, item 6) — pure functions over the recognizer's partial result: a voice
/// card stops 500 ms after the partial result already passes; a judge-graded card stops 800 ms after the frame and
/// at least one more word; otherwise null — the recording waits for silence.
void main() {
  final en = SpeechCoverage.articlesFor('en');

  bool Function(String) coverage(String expected, double min) =>
      (heard) => SpeechCoverage.covers(heard, expected, min, en);

  // CATCHES: a stop before full coverage (a phrase cut in the middle) and no stop at full coverage.
  test('voice: full coverage → stop after 500 ms; not yet covered → wait for silence', () {
    final word = coverage('lower back', SpeechCoverage.minFor('lower back', en));
    expect(SpeechStop.voice('lower', word), isNull);
    expect(SpeechStop.voice('lower back', word), const Duration(milliseconds: 500));
    expect(SpeechStop.voice('lowerback', word), SpeechStop.covered, reason: 'gluing counts as both words');
    expect(SpeechStop.voice('Lower back!', word), SpeechStop.covered, reason: 'normalized before matching');
    expect(SpeechStop.voice('', word), isNull);
    expect(SpeechStop.voice('   ', (_) => true), isNull, reason: 'nothing heard — nothing to stop on');

    final phrase = coverage('It hurts in his neck.', 0.7);
    expect(SpeechStop.voice('it hurts', phrase), isNull, reason: '2 of 5 words');
    expect(SpeechStop.voice('it hurts in', phrase), isNull, reason: '3 of 5 — below 70 %');
    expect(SpeechStop.voice('it hurts in his', phrase), SpeechStop.covered, reason: '4 of 5 — the moment of coverage');
  });

  // CATCHES: asking the judge on the frame alone (nothing for the slot yet) and on a slot without the frame.
  test('judge: the frame covered and at least one word after it → stop after 800 ms', () {
    expect(SpeechStop.judge('It started', 'It started', 1.0, en), isNull, reason: 'the frame alone — the slot is still to come');
    expect(SpeechStop.judge('It started last', 'It started', 1.0, en), const Duration(milliseconds: 800));
    expect(SpeechStop.judge('It started a', 'It started', 1.0, en), isNull, reason: 'an article is not the slot');
    expect(SpeechStop.judge('last night', 'It started', 1.0, en), isNull, reason: 'no frame');
    expect(SpeechStop.judge('started last night', 'It started', 1.0, en), isNull, reason: 'the frame is not covered');
    expect(SpeechStop.judge('Itstarted yesterday', 'It started', 1.0, en), SpeechStop.judged, reason: 'gluing in the frame');
    expect(SpeechStop.judge('', 'It started', 1.0, en), isNull);
  });

  // SESSION-1c: a judged frame without a slot (`speak_answer` x4) has nothing beyond it to wait for; the retelling has
  // no frame — a word heard is enough.
  // CATCHES: a no-slot answer that waits 2 s of silence after being said whole, a retelling that never stops early,
  // and a retelling stopped on silence alone.
  test('judge: a frame without a slot — covered is enough; no frame (the retelling) — any word', () {
    const noSlot = "He doesn't have a fever";
    expect(SpeechStop.judge("he doesn't have a fever", noSlot, 0.7, en, slot: false), SpeechStop.judged);
    expect(SpeechStop.judge('he', noSlot, 0.7, en, slot: false), isNull);
    expect(SpeechStop.judge("he doesn't have a fever", noSlot, 0.7, en), isNull, reason: 'with a slot the frame alone waits');
    expect(SpeechStop.judge('Отдыхать два дня', '', 1.0, const {}), SpeechStop.judged);
    expect(SpeechStop.judge('', '', 1.0, const {}), isNull);
    expect(SpeechStop.judge('  ', '', 1.0, const {}), isNull);
  });

  test('the pauses are the owner\'s numbers', () {
    expect(SpeechStop.covered, const Duration(milliseconds: 500));
    expect(SpeechStop.judged, const Duration(milliseconds: 800));
  });
}
