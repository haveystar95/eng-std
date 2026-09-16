import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/live_line.dart';

/// ЖИВАЯ СТРОКА МИКРОФОНА (кадр 30-3): слова распознавателя → шалфей (совпало с ожидаемым текстом) / серый
/// (последнее, пока запись идёт) / чернила.
void main() {
  List<LiveTone> tones(String heard, String expected, {bool listening = true}) =>
      [for (final w in LiveLine.of(heard, expected, listening: listening)) w.tone];

  test('пустая строка — пусто', () {
    expect(LiveLine.of('  ', 'lower back', listening: true), isEmpty);
  });

  test('пока запись идёт, последнее слово серое, даже совпавшее', () {
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

  test('запись закрылась — строка замерла: серого нет', () {
    expect(tones('lower back', 'lower back', listening: false), [LiveTone.matched, LiveTone.matched]);
    expect(tones('lower neck', 'lower back', listening: false), [LiveTone.matched, LiveTone.plain]);
  });

  test('совпадения считаются мультимножеством — слово ожидаемого текста красится один раз', () {
    expect(tones('back back back', 'lower back', listening: false), [LiveTone.matched, LiveTone.plain, LiveTone.plain]);
  });

  test('слова сравниваются канонически: регистр, знаки, сокращения', () {
    expect(tones("He doesn't, have", "He doesn't have a fever.", listening: false), [
      LiveTone.matched,
      LiveTone.matched,
      LiveTone.matched,
    ]);
    expect(tones('It hurts in his SHOULDER.', 'It hurts in his shoulder.', listening: false), everyElement(LiveTone.matched));
  });

  test('текст слова — как написал распознаватель', () {
    final words = LiveLine.of('Lower back,', 'lower back', listening: false);
    expect([for (final w in words) w.text], ['Lower', 'back,']);
  });
}
