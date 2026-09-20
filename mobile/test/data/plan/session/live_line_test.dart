import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/live_line.dart';

/// THE MICROPHONE'S LIVE LINE (canvas 30-3): the recognizer's words → sage (matched the expected text) / gray (the
/// last one, while recording) / ink.
void main() {
  List<LiveTone> tones(String heard, String expected, {bool listening = true}) =>
      [for (final w in LiveLine.of(heard, expected, listening: listening)) w.tone];

  test('an empty line — nothing', () {
    expect(LiveLine.of('  ', 'lower back', listening: true), isEmpty);
  });

  test('while recording the last word is gray, even when it matched', () {
    expect(tones('lower back', 'lower back'), [LiveTone.matched, LiveTone.pending]);
    expect(tones('I have pain in my lower back', 'lower back'), [
      LiveTone.plain,
      LiveTone.plain,
      LiveTone.plain,
      LiveTone.plain,
      LiveTone.plain,
      LiveTone.matched,
      LiveTone.pending,
    ]);
  });

  test('the recording closed — the line is frozen: no gray', () {
    expect(tones('lower back', 'lower back', listening: false), [LiveTone.matched, LiveTone.matched]);
    expect(tones('lower neck', 'lower back', listening: false), [LiveTone.matched, LiveTone.plain]);
  });

  test('matches count as a multiset — an expected word is painted once', () {
    expect(tones('back back back', 'lower back', listening: false), [LiveTone.matched, LiveTone.plain, LiveTone.plain]);
  });

  test('words are compared canonically: case, marks, contractions', () {
    expect(tones("He doesn't, have", "He doesn't have a fever.", listening: false), [
      LiveTone.matched,
      LiveTone.matched,
      LiveTone.matched,
    ]);
    expect(tones('It hurts in his SHOULDER.', 'It hurts in his shoulder.', listening: false), everyElement(LiveTone.matched));
  });

  test('a word\'s text — as the recognizer wrote it', () {
    final words = LiveLine.of('Lower back,', 'lower back', listening: false);
    expect([for (final w in words) w.text], ['Lower', 'back,']);
  });

  // RULE (SESSION-1b′, item 6; work order FIX-2 §2): a token the recogniser glued out of the line's own words is
  // shown as those words — as many as it holds, in whatever order the line has them, exactly as the server's
  // boundary pass reads it.
  // CATCHES: «workschedule» painted as one ink word while the pass counts it as two, and a live line that splits
  // differently from the rule that grades it.
  test('gluing — two separate words on the line, each matched', () {
    final words = LiveLine.of('can I change my workschedule', 'Can I change my work schedule?', listening: false);
    expect([for (final w in words) w.text], ['can', 'I', 'change', 'my', 'work', 'schedule']);
    expect([for (final w in words) w.tone], everyElement(LiveTone.matched));

    final live = LiveLine.of('my workschedule', 'my work schedule', listening: true);
    expect([for (final w in live) w.text], ['my', 'work', 'schedule']);
    expect([for (final w in live) w.tone], [LiveTone.matched, LiveTone.pending, LiveTone.pending],
        reason: 'the glued last word is still being refined — both halves gray');
    // The order the line has them in is not asked for: the recogniser glues what it hears, not what is written.
    expect([for (final w in LiveLine.of('schedulework', 'my work schedule', listening: false)) w.text],
        ['schedule', 'work']);
    expect([for (final w in LiveLine.of('carpet', 'my work schedule', listening: false)) w.text], ['carpet'],
        reason: 'no piece is a word of the line — left as heard');
  });
}
