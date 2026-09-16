import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/speech_coverage.dart';

/// ПОКРЫТИЕ РЕЧИ — те же примеры, что `backend2/tests/Unit/Plan/Session/SpeechCoverageTest.php`: всё у строки
/// из ≤ 2 значащих слов, 70 % у длинной, мультимножеством, артикли пакета цели прощаются — и ничего у языка
/// без артиклей. Пакет `en` сервера — артикли a/an/the; пакет `ru` артиклей не называет.
void main() {
  final en = SpeechCoverage.articlesFor('en');
  final ru = SpeechCoverage.articlesFor('ru');

  test('всё у строки из двух значащих слов, большую часть у длинной', () {
    expect(SpeechCoverage.minFor('a headache', en), 1.0);
    expect(SpeechCoverage.minFor('the X-ray', en), 1.0);
    expect(SpeechCoverage.minFor('I have a fever', en), 0.7);
    expect(SpeechCoverage.countedWords('I have a fever.', en), 3);
    // Короткая: одно слово мимо — промах.
    expect(SpeechCoverage.covers('sick', 'sick note', 1.0, en), isFalse);
    expect(SpeechCoverage.covers('Sick note!', 'sick note', 1.0, en), isTrue);
    // Длинная: 3 из 3 значащих, 2 из 3 — ниже 0.7.
    expect(SpeechCoverage.covers('i have fever', 'I have a fever.', 0.7, en), isTrue);
    expect(SpeechCoverage.covers('have fever', 'I have a fever.', 0.7, en), isFalse);
    // 7 из 10 — ровно порог.
    expect(
      SpeechCoverage.covers('one two three four five six seven', 'one two three four five six seven eight nine ten', 0.7, en),
      isTrue,
    );
    expect(SpeechCoverage.covers('whatever', '', 0.7, en), isFalse);
  });

  // Ловит множество вместо мультимножества: «no», сказанное раз, не покрывает строку, где оно трижды.
  test('ожидаемые слова считаются мультимножеством, каждое услышанное — один раз', () {
    expect(SpeechCoverage.covers('no', 'no no no', 0.7, en), isFalse);
    expect(SpeechCoverage.covers('very good day', 'very very good day', 0.7, en), isTrue);
    expect(SpeechCoverage.covers('very good', 'very very very good', 0.7, en), isFalse);
  });

  // Ловит зашитые a/an/the: у русского пакета артиклей нет, и его «a» — обычное слово.
  test('артикли пакета цели прощаются, у языка без артиклей — ничего', () {
    expect(SpeechCoverage.covers('headache', 'a headache', 1.0, en), isTrue);
    expect(SpeechCoverage.minFor('a headache', ru), 1.0);
    expect(SpeechCoverage.countedWords('a headache', ru), 2);
    expect(SpeechCoverage.covers('headache', 'a headache', 1.0, ru), isFalse);
    expect(SpeechCoverage.minFor('the back pain', en), 1.0);
    expect(SpeechCoverage.minFor('the back pain', ru), 0.7);
  });

  test('значение окна подряд, артикли не в счёт', () {
    expect(SpeechCoverage.containsSequence('Yes, I have a bad headache since morning', 'a bad headache', en), isTrue);
    expect(SpeechCoverage.containsSequence('I have bad the headache', 'bad headache', en), isTrue);
    expect(SpeechCoverage.containsSequence('I have a headache that is bad', 'bad headache', en), isFalse);
    expect(SpeechCoverage.containsSequence('I have a headache', 'the', en), isFalse);
    expect(SpeechCoverage.containsSequence('headache', 'bad headache', en), isFalse);
    expect(SpeechCoverage.containsSequence('I have a headache', 'a headache', ru), isTrue);
    expect(SpeechCoverage.containsSequence('I have headache', 'a headache', ru), isFalse);
  });

  test('нормализация как на сервере: регистр, знаки, дефисы, сокращения', () {
    expect(SpeechCoverage.words('X-ray?'), ['x', 'ray']);
    expect(SpeechCoverage.words("He doesn't have a fever."), ['he', 'does', 'not', 'have', 'a', 'fever']);
    expect(SpeechCoverage.words("He’s been resting"), ['he', 'has', 'been', 'resting']);
    expect(SpeechCoverage.words("It's my back"), ['it', 'is', 'my', 'back']);
    expect(SpeechCoverage.words('Follow-up   appointment!'), ['follow', 'up', 'appointment']);
    expect(SpeechCoverage.covers('he does not have fever', "He doesn't have a fever.", 0.7, en), isTrue);
    expect(SpeechCoverage.covers('xray', 'X-ray', 1.0, en), isFalse);
  });
}
