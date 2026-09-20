import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/speech_match.dart';

/// THE ONE RULE OF SPOKEN GRADING, ON THE PHONE — the same examples as `backend2/tests/Unit/Shared/SpeechMatchTest.php`
/// (work order FIX-2, item 2): `repeat` asks every content word in its order, `free` a share of the key as a multiset.
/// The lists are the SERVER'S and arrive with the day; here they are written out as the `en` and `ru` packs send them,
/// so a drift between the two sides fails on one side or the other.
void main() {
  /// English as the day's `speech` block sends it — the pack's own lists, nothing invented in Dart.
  const en = SpeechRules(
    unstressed: {
      'a', 'an', 'the',
      'to', 'of', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'into', 'about', 'over', 'under', 'after',
      'before', 'through', 'up', 'down', 'out', 'off', 'as', 'than',
      'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did', 'have', 'has', 'had',
      'will', 'would', 'shall', 'should', 'can', 'could', 'may', 'might', 'must',
    },
    articles: {'a', 'an', 'the'},
    abbreviations: ['a.m.', 'p.m.', 'e.g.', 'i.e.', 'etc.', 'vs.', 'Mr.', 'Mrs.', 'Ms.', 'Dr.', 'St.'],
    numberWords: {'one': '1', 'two': '2', 'three': '3', 'five': '5', 'ten': '10'},
  );

  /// Russian names no articles: its «a» is an ordinary word.
  const ru = SpeechRules();

  // Canon: «нормализация — регистр, знаки, сокращения, числа словом/цифрой, сокращённые формы (I'd = I would)».
  // Catches a comparison done on the raw strings: the two readings below are the SAME sentence said by two
  // recognisers, and the owner's live day asked for exactly this one.
  test('repeat: a contraction, an abbreviation and a number written either way are one sentence', () {
    expect(SpeechMatch.repeated('I would like the three pm appointment', "I'd like the 3 p.m. appointment", en), isTrue);
    expect(SpeechMatch.repeated("I'd like the 3 p.m. appointment", 'I would like the three pm appointment', en), isTrue);
    expect(SpeechMatch.words('3 p.m.', en), ['3', 'pm']);
    expect(SpeechMatch.words('three PM', en), ['3', 'pm']);
  });

  // Canon: «все смысловые слова на месте и по порядку; служебные слова пакета не считаются». Catches the share that
  // passed «He has a rush» for «He has a rash» on the owner's phone, and a rule that would fail a dropped article.
  test('repeat: one content word wrong is a miss, an eaten article is not', () {
    expect(SpeechMatch.repeated('He has a rash', 'He has a rash.', en), isTrue);
    expect(SpeechMatch.repeated('He has a rush', 'He has a rash.', en), isFalse);
    expect(SpeechMatch.repeated('He has rash', 'He has a rash.', en), isTrue);
    expect(SpeechMatch.repeated('Um, it hurts in his lower back, I think', 'It hurts in his lower back.', en), isTrue);
    expect(SpeechMatch.repeated('his lower back hurts', 'It hurts in his lower back.', en), isFalse);
    expect(SpeechMatch.repeated('It hurts in his back lower', 'It hurts in his lower back.', en), isFalse);
  });

  // Canon: «ручка ослабления — 0». Catches a handle wired to nothing and a default that is not zero.
  test('repeat: nothing is dropped by default, and exactly as much as the handle allows', () {
    expect(SpeechMatch.repeated('It hurts in his back', 'It hurts in his lower back.', en), isFalse);
    const loose = SpeechRules(unstressed: {'in', 'his'}, repeatMisses: 1);
    expect(SpeechMatch.repeated('It hurts in his back', 'It hurts in his lower back.', loose), isTrue);
    expect(SpeechMatch.repeated('It hurts', 'It hurts in his lower back.', loose), isFalse);
  });

  // A language nobody has written a pack for forgives nothing; a line of nothing but function words is asked whole.
  test('repeat: only what the pack names is forgiven', () {
    expect(SpeechMatch.repeated('He has rash', 'He has a rash.', ru), isFalse);
    expect(SpeechMatch.repeated('He has a rash', 'He has a rash.', ru), isTrue);
    expect(SpeechMatch.repeated('in the', 'In the ___.', en), isTrue);
    expect(SpeechMatch.repeated('the', 'In the ___.', en), isFalse);
  });

  // The two habits of a recogniser, forgiven in BOTH modes — the client may never be stricter than the server.
  test('a guessed boundary and a dropped trailing sibilant are forgiven in both modes', () {
    expect(SpeechMatch.repeated('I see withoututilities', 'I see without utilities', ru), isTrue);
    expect(SpeechMatch.covers('I see withoututilities', 'I see without utilities', 0.7, ru), isTrue);
    expect(SpeechMatch.repeated('salary expectation', 'salary expectations', ru), isTrue);
    expect(SpeechMatch.covers('salary expectation', 'salary expectations', 1.0, ru), isTrue);
  });

  test('the mode comes off the wire, and anything unknown is the looser one', () {
    expect(SpeechMode.fromWire('repeat'), SpeechMode.repeat);
    expect(SpeechMode.fromWire('free'), SpeechMode.free);
    expect(SpeechMode.fromWire(null), SpeechMode.free);
    expect(SpeechMode.fromWire('whatever a later server sends'), SpeechMode.free);
    // The same attempt, the two modes: one wrong word out of six is a miss when the line is on the screen and a
    // pass when the learner is saying their own sentence — which is the whole point of having two.
    expect(SpeechMatch.said('It hurts in his lower beck', 'It hurts in his lower back.', SpeechMode.repeat, en), isFalse);
    expect(SpeechMatch.said('It hurts in his lower beck', 'It hurts in his lower back.', SpeechMode.free, en), isTrue);
  });

  test('all words of a two-word string, most of a longer one', () {
    expect(SpeechMatch.minFor('a headache', en), 1.0);
    expect(SpeechMatch.minFor('the X-ray', en), 1.0);
    expect(SpeechMatch.minFor('I have a fever', en), 0.7);
    expect(SpeechMatch.countedWords('I have a fever.', en), 3);
    // Short: one word missing — a miss.
    expect(SpeechMatch.covers('sick', 'sick note', 1.0, en), isFalse);
    expect(SpeechMatch.covers('Sick note!', 'sick note', 1.0, en), isTrue);
    // Long: 3 of 3 significant words; 2 of 3 is below 0.7.
    expect(SpeechMatch.covers('i have fever', 'I have a fever.', 0.7, en), isTrue);
    expect(SpeechMatch.covers('have fever', 'I have a fever.', 0.7, en), isFalse);
    // 7 of 10 — exactly the threshold.
    expect(
      SpeechMatch.covers('one two three four five six seven', 'one two three four five six seven eight nine ten', 0.7, en),
      isTrue,
    );
    expect(SpeechMatch.covers('whatever', '', 0.7, en), isFalse);
  });

  // Catches a set instead of a multiset: «no» said once does not cover a string where it occurs three times.
  test('expected words count as a multiset, each heard word once', () {
    expect(SpeechMatch.covers('no', 'no no no', 0.7, en), isFalse);
    expect(SpeechMatch.covers('very good day', 'very very good day', 0.7, en), isTrue);
    expect(SpeechMatch.covers('very good', 'very very very good', 0.7, en), isFalse);
  });

  // Catches a hard-coded a/an/the: the Russian package has no articles, and its «a» is an ordinary word.
  test('the target package\'s articles are forgiven, nothing for a language without articles', () {
    expect(SpeechMatch.covers('headache', 'a headache', 1.0, en), isTrue);
    expect(SpeechMatch.minFor('a headache', ru), 1.0);
    expect(SpeechMatch.countedWords('a headache', ru), 2);
    expect(SpeechMatch.covers('headache', 'a headache', 1.0, ru), isFalse);
    expect(SpeechMatch.minFor('the back pain', en), 1.0);
    expect(SpeechMatch.minFor('the back pain', ru), 0.7);
  });

  test('the slot value in a row, articles do not count', () {
    expect(SpeechMatch.containsSequence('Yes, I have a bad headache since morning', 'a bad headache', en), isTrue);
    expect(SpeechMatch.containsSequence('I have bad the headache', 'bad headache', en), isTrue);
    expect(SpeechMatch.containsSequence('I have a headache that is bad', 'bad headache', en), isFalse);
    expect(SpeechMatch.containsSequence('I have a headache', 'the', en), isFalse);
    expect(SpeechMatch.containsSequence('headache', 'bad headache', en), isFalse);
    expect(SpeechMatch.containsSequence('I have a headache', 'a headache', ru), isTrue);
    expect(SpeechMatch.containsSequence('I have headache', 'a headache', ru), isFalse);
  });

  test('normalization as on the server: case, marks, hyphens, contractions', () {
    expect(SpeechMatch.words('X-ray?'), ['x', 'ray']);
    expect(SpeechMatch.words("He doesn't have a fever."), ['he', 'does', 'not', 'have', 'a', 'fever']);
    expect(SpeechMatch.words("He’s been resting"), ['he', 'has', 'been', 'resting']);
    expect(SpeechMatch.words("It's my back"), ['it', 'is', 'my', 'back']);
    expect(SpeechMatch.words('Follow-up   appointment!'), ['follow', 'up', 'appointment']);
    expect(SpeechMatch.covers('he does not have fever', "He doesn't have a fever.", 0.7, en), isTrue);
  });

  // RULE (SESSION-1b′, item 6): the recognizer sometimes drops the space between two words («workschedule»); a heard
  // token that is not an expected word but equals two ADJACENT expected words glued together counts as both.
  // CATCHES: a phrase said in full and not passed because of a lost space; a split that invents words out of order.
  group('gluing', () {
    test('a glued pair of adjacent expected words counts as both', () {
      expect(SpeechMatch.unglue('workschedule', ['my', 'work', 'schedule']), ['work', 'schedule']);
      expect(SpeechMatch.unglue('schedulework', ['my', 'work', 'schedule']), isNull, reason: 'not in the expected order');
      expect(SpeechMatch.unglue('myschedule', ['my', 'work', 'schedule']), isNull, reason: 'not adjacent');
      expect(SpeechMatch.heardWords('can I change my workschedule', 'Can I change my work schedule?'), [
        'can',
        'i',
        'change',
        'my',
        'work',
        'schedule',
      ]);
      expect(SpeechMatch.covers('my workschedule', 'my work schedule', 1.0, en), isTrue);
      expect(SpeechMatch.covers('lowerback', 'lower back', 1.0, en), isTrue);
      expect(SpeechMatch.coverageOf('Can I change my workschedule', 'Can I change my work schedule?', en), 1.0);
    });

    test('normalized before ungluing: case and marks do not matter; an expected word is never split', () {
      expect(SpeechMatch.covers('WorkSchedule!', 'work schedule', 1.0, en), isTrue);
      expect(SpeechMatch.heardWords('backache', 'back ache'), ['back', 'ache']);
      expect(SpeechMatch.heardWords('it hurts', 'it hurts'), ['it', 'hurts']);
      // «in» is itself an expected word — it stays one word.
      expect(SpeechMatch.heardWords('in', 'i n in'), ['in']);
      expect(SpeechMatch.covers('xray', 'X-ray', 1.0, en), isTrue, reason: 'x + ray glued is the hyphenated word');
    });

    test('words over the expected text — the judge-graded stop counts them', () {
      expect(SpeechMatch.extraWords('It started last night', 'It started', en), 2);
      expect(SpeechMatch.extraWords('It started', 'It started', en), 0);
      expect(SpeechMatch.extraWords('It started a', 'It started', en), 0, reason: 'an article is not a word said');
      expect(SpeechMatch.extraWords('Itstarted yesterday', 'It started', en), 1, reason: 'gluing is unglued first');
    });
  });
}
