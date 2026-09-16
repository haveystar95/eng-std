import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/speech_coverage.dart';

/// SPEECH COVERAGE — the same examples as `backend2/tests/Unit/Plan/Session/SpeechCoverageTest.php`: all words of a
/// string of ≤ 2 significant words, 70 % of a longer one, as a multiset, the target package's articles forgiven —
/// and nothing for a language without articles. The server's `en` package — articles a/an/the; the `ru` package
/// names none. Plus the client's gluing (SESSION-1b′, item 6).
void main() {
  final en = SpeechCoverage.articlesFor('en');
  final ru = SpeechCoverage.articlesFor('ru');

  test('all words of a two-word string, most of a longer one', () {
    expect(SpeechCoverage.minFor('a headache', en), 1.0);
    expect(SpeechCoverage.minFor('the X-ray', en), 1.0);
    expect(SpeechCoverage.minFor('I have a fever', en), 0.7);
    expect(SpeechCoverage.countedWords('I have a fever.', en), 3);
    // Short: one word missing — a miss.
    expect(SpeechCoverage.covers('sick', 'sick note', 1.0, en), isFalse);
    expect(SpeechCoverage.covers('Sick note!', 'sick note', 1.0, en), isTrue);
    // Long: 3 of 3 significant words; 2 of 3 is below 0.7.
    expect(SpeechCoverage.covers('i have fever', 'I have a fever.', 0.7, en), isTrue);
    expect(SpeechCoverage.covers('have fever', 'I have a fever.', 0.7, en), isFalse);
    // 7 of 10 — exactly the threshold.
    expect(
      SpeechCoverage.covers('one two three four five six seven', 'one two three four five six seven eight nine ten', 0.7, en),
      isTrue,
    );
    expect(SpeechCoverage.covers('whatever', '', 0.7, en), isFalse);
  });

  // Catches a set instead of a multiset: «no» said once does not cover a string where it occurs three times.
  test('expected words count as a multiset, each heard word once', () {
    expect(SpeechCoverage.covers('no', 'no no no', 0.7, en), isFalse);
    expect(SpeechCoverage.covers('very good day', 'very very good day', 0.7, en), isTrue);
    expect(SpeechCoverage.covers('very good', 'very very very good', 0.7, en), isFalse);
  });

  // Catches a hard-coded a/an/the: the Russian package has no articles, and its «a» is an ordinary word.
  test('the target package\'s articles are forgiven, nothing for a language without articles', () {
    expect(SpeechCoverage.covers('headache', 'a headache', 1.0, en), isTrue);
    expect(SpeechCoverage.minFor('a headache', ru), 1.0);
    expect(SpeechCoverage.countedWords('a headache', ru), 2);
    expect(SpeechCoverage.covers('headache', 'a headache', 1.0, ru), isFalse);
    expect(SpeechCoverage.minFor('the back pain', en), 1.0);
    expect(SpeechCoverage.minFor('the back pain', ru), 0.7);
  });

  test('the slot value in a row, articles do not count', () {
    expect(SpeechCoverage.containsSequence('Yes, I have a bad headache since morning', 'a bad headache', en), isTrue);
    expect(SpeechCoverage.containsSequence('I have bad the headache', 'bad headache', en), isTrue);
    expect(SpeechCoverage.containsSequence('I have a headache that is bad', 'bad headache', en), isFalse);
    expect(SpeechCoverage.containsSequence('I have a headache', 'the', en), isFalse);
    expect(SpeechCoverage.containsSequence('headache', 'bad headache', en), isFalse);
    expect(SpeechCoverage.containsSequence('I have a headache', 'a headache', ru), isTrue);
    expect(SpeechCoverage.containsSequence('I have headache', 'a headache', ru), isFalse);
  });

  test('normalization as on the server: case, marks, hyphens, contractions', () {
    expect(SpeechCoverage.words('X-ray?'), ['x', 'ray']);
    expect(SpeechCoverage.words("He doesn't have a fever."), ['he', 'does', 'not', 'have', 'a', 'fever']);
    expect(SpeechCoverage.words("He’s been resting"), ['he', 'has', 'been', 'resting']);
    expect(SpeechCoverage.words("It's my back"), ['it', 'is', 'my', 'back']);
    expect(SpeechCoverage.words('Follow-up   appointment!'), ['follow', 'up', 'appointment']);
    expect(SpeechCoverage.covers('he does not have fever', "He doesn't have a fever.", 0.7, en), isTrue);
  });

  // RULE (SESSION-1b′, item 6): the recognizer sometimes drops the space between two words («workschedule»); a heard
  // token that is not an expected word but equals two ADJACENT expected words glued together counts as both.
  // CATCHES: a phrase said in full and not passed because of a lost space; a split that invents words out of order.
  group('gluing', () {
    test('a glued pair of adjacent expected words counts as both', () {
      expect(SpeechCoverage.unglue('workschedule', ['my', 'work', 'schedule']), ['work', 'schedule']);
      expect(SpeechCoverage.unglue('schedulework', ['my', 'work', 'schedule']), isNull, reason: 'not in the expected order');
      expect(SpeechCoverage.unglue('myschedule', ['my', 'work', 'schedule']), isNull, reason: 'not adjacent');
      expect(SpeechCoverage.heardWords('can I change my workschedule', 'Can I change my work schedule?'), [
        'can',
        'i',
        'change',
        'my',
        'work',
        'schedule',
      ]);
      expect(SpeechCoverage.covers('my workschedule', 'my work schedule', 1.0, en), isTrue);
      expect(SpeechCoverage.covers('lowerback', 'lower back', 1.0, en), isTrue);
      expect(SpeechCoverage.coverageOf('Can I change my workschedule', 'Can I change my work schedule?', en), 1.0);
    });

    test('normalized before ungluing: case and marks do not matter; an expected word is never split', () {
      expect(SpeechCoverage.covers('WorkSchedule!', 'work schedule', 1.0, en), isTrue);
      expect(SpeechCoverage.heardWords('backache', 'back ache'), ['back', 'ache']);
      expect(SpeechCoverage.heardWords('it hurts', 'it hurts'), ['it', 'hurts']);
      // «in» is itself an expected word — it stays one word.
      expect(SpeechCoverage.heardWords('in', 'i n in'), ['in']);
      expect(SpeechCoverage.covers('xray', 'X-ray', 1.0, en), isTrue, reason: 'x + ray glued is the hyphenated word');
    });

    test('words over the expected text — the judge-graded stop counts them', () {
      expect(SpeechCoverage.extraWords('It started last night', 'It started', en), 2);
      expect(SpeechCoverage.extraWords('It started', 'It started', en), 0);
      expect(SpeechCoverage.extraWords('It started a', 'It started', en), 0, reason: 'an article is not a word said');
      expect(SpeechCoverage.extraWords('Itstarted yesterday', 'It started', en), 1, reason: 'gluing is unglued first');
    });
  });
}
