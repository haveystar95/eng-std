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
    // The whole of the pack's table, as the day sends it (наряд FIX-3 §4) — the phone folds numbers by the server's rule.
    numberWords: {
      'zero': '0', 'one': '1', 'two': '2', 'three': '3', 'four': '4', 'five': '5', 'six': '6', 'seven': '7',
      'eight': '8', 'nine': '9', 'ten': '10', 'eleven': '11', 'twelve': '12', 'thirteen': '13', 'fourteen': '14',
      'fifteen': '15', 'sixteen': '16', 'seventeen': '17', 'eighteen': '18', 'nineteen': '19', 'twenty': '20',
      'thirty': '30', 'forty': '40', 'fifty': '50', 'sixty': '60', 'seventy': '70', 'eighty': '80', 'ninety': '90',
      'hundred': '100', 'thousand': '1000', 'million': '1000000',
    },
    numberJoiners: {'and'},
  );

  /// Russian names no articles: its «a» is an ordinary word.
  const ru = SpeechRules();

  // THE MOMENT A RECORDING CLOSES, NOT ITS GRADE (правка прохода 21.09, наряд CLIENT-CONV-1b): «all content words heard»
  // decides between the short and the long pause. The content words are the line's words minus the pack's unstressed
  // ones, as a multiset, in any order; a line of unstressed words only waits for all of them.
  // CATCHES: a pause that stays long after the line was said (the unstressed «in his» still waited for), and one that
  // turns short while a content word is still missing.
  test('heardAll: every content word of the line, in any order; the unstressed ones are not waited for', () {
    expect(SpeechMatch.heardAll('It hurts his lower back', 'It hurts in his lower back.', en), isTrue, reason: '«in» is unstressed');
    expect(SpeechMatch.heardAll('It hurts in lower back', 'It hurts in his lower back.', en), isFalse,
        reason: '«his» is not in the pack\'s unstressed list — it is waited for');
    expect(SpeechMatch.heardAll('his lower back it hurts', 'It hurts in his lower back.', en), isTrue, reason: 'order does not matter here');
    expect(SpeechMatch.heardAll('It hurts in his lower', 'It hurts in his lower back.', en), isFalse, reason: '«back» is still ahead');
    expect(SpeechMatch.heardAll('', 'It hurts in his lower back.', en), isFalse);
    expect(SpeechMatch.heardAll('He has had it', 'He has had it.', en), isTrue, reason: 'a line of unstressed words waits for them all');
    expect(SpeechMatch.heardAll('He has', 'He has had it.', en), isFalse);
    expect(SpeechMatch.heardAll('three three', 'three days, three nights', en), isFalse, reason: 'a multiset: each heard word once');
  });

  // Canon (наряд FIX-3 §4, зеркало сервера `SpokenNumbers`): «и ожидаемый, и услышанный текст перед сравнением
  // приводятся к цифрам: слова-числа → число, составные складываются, дефис = пробел». The same cases as
  // `backend2/tests/Unit/Shared/SpeechMatchTest.php`, so the two sides never judge a number differently — the owner's
  // gym day failed «I will rest for 45 seconds» against «I'll rest for forty-five seconds» twice.
  // CATCHES a fold word by word («forty-five» → «40 5»), a fold on one side only, «and» swallowed between two numbers
  // it does not join, and a pack whose joiners the phone ignores.
  test('numbers: the words of ONE number are that number, on both sides', () {
    expect(SpeechMatch.words("I'll rest for forty-five seconds.", en), ['i', 'will', 'rest', 'for', '45', 'seconds']);
    expect(SpeechMatch.words('I will rest for 45 seconds', en), ['i', 'will', 'rest', 'for', '45', 'seconds']);
    expect(SpeechMatch.words('twenty one', en), ['21']);
    expect(SpeechMatch.words('Take one minute, then twenty-one reps', en), ['take', '1', 'minute', 'then', '21', 'reps']);
    expect(SpeechMatch.words('a hundred dollars', en), ['100', 'dollars']);
    expect(SpeechMatch.words('one hundred twenty-five', en), ['125']);
    expect(SpeechMatch.words('two thousand five hundred', en), ['2500']);
    expect(SpeechMatch.words('one hundred and twenty', en), ['120']);
    expect(SpeechMatch.words('two thousand and five', en), ['2005']);
    expect(SpeechMatch.words('one million', en), ['1000000']);
    expect(SpeechMatch.words('ten five', en), ['10', '5']);
    expect(SpeechMatch.words('twenty twelve', en), ['20', '12']);
    expect(SpeechMatch.words('two three', en), ['2', '3']);
    expect(SpeechMatch.words('five and six', en), ['5', 'and', '6']);
    expect(SpeechMatch.words('two hundred and a thousand', en), ['200', 'and', '1000']);
    expect(SpeechMatch.words('a bar', en), ['a', 'bar']);
    expect(SpeechMatch.repeated("I'll rest for forty-five seconds.", 'I will rest for 45 seconds', en), isTrue);
    expect(SpeechMatch.repeated('I will rest for 45 seconds', "I'll rest for forty-five seconds.", en), isTrue);
    expect(SpeechMatch.repeated('I will rest for 40 seconds', "I'll rest for forty-five seconds.", en), isFalse);
    expect(SpeechMatch.repeated('It costs 120 dollars', 'It costs one hundred and twenty dollars.', en), isTrue);
    // A language whose pack names no number words folds nothing.
    expect(SpeechMatch.words('twenty one', ru), ['twenty', 'one']);
  });

  // Canon (наряд LANG-1 §4): English «and» joins a number only after a scale — `number_joiners`; the joiner after a tens
  // word is a list of its own, `number_tens_joiners`, optional on the wire, and English sends none. So two numbers said
  // with «and» between them stay two, as the server reads them. CATCHES the phone reading «between twenty and one
  // hundred dollars» as 2100 (the first cut of LANG-1), a block without the new key failing to parse or reading one, and
  // a tens joiner the phone receives and does not hand to the fold.
  test('numbers: «and» after a tens word starts the next number; the tens joiners come off the wire, absent is none', () {
    final day = SpeechRules.fromJson({
      'unstressed_words': ['a', 'an', 'the'],
      'articles': ['a', 'an', 'the'],
      'abbreviations': <String>[],
      'number_words': en.numberWords,
      'number_joiners': ['and'],
      'repeat_misses': 0,
    });
    expect(day.numberTensJoiners, isEmpty);
    expect(SpeechMatch.words('It costs between twenty and one hundred dollars', day), [
      'it',
      'costs',
      'between',
      '20',
      'and',
      '100',
      'dollars',
    ]);
    expect(SpeechMatch.words('twenty and five', en), ['20', 'and', '5']);
    expect(SpeechMatch.repeated('It costs between 20 and 100 dollars', 'It costs between twenty and one hundred dollars.', en), isTrue);

    final es = SpeechRules.fromJson({
      'number_words': {'treinta': '30', 'uno': '1', 'ciento': '100'},
      'number_joiners': <String>[],
      'number_tens_joiners': ['y'],
    });
    expect(es.numberTensJoiners, {'y'});
    expect(SpeechMatch.words('ciento treinta y uno', es), ['131']);
    expect(SpeechMatch.words('ciento y uno', es), ['100', 'y', '1']);
  });

  // Canon (наряд LANG-1 §4): a target whose pack writes no numbers — or has no pack yet — still gets its day. The server
  // serialises an EMPTY `number_words` map as `[]` (what `json_encode` makes of an empty PHP array), not `{}`; the phone
  // reads it as «no number words». CATCHES the cast that threw on `[]` and took the whole day down with it — now that
  // seven targets exist, not only English, whose pack always wrote its numbers.
  test('numbers: a block with no number words — `[]` on the wire — parses and folds nothing', () {
    final day = SpeechRules.fromJson({
      'unstressed_words': <dynamic>[],
      'articles': <dynamic>[],
      'abbreviations': <dynamic>[],
      'number_words': <dynamic>[],
      'number_joiners': <dynamic>[],
      'repeat_misses': 1,
    });
    expect(day.numberWords, isEmpty);
    expect(day.repeatMisses, 1);
    expect(SpeechMatch.words('zwanzig eins', day), ['zwanzig', 'eins']);
  });

  /// A PACK WRITTEN THE WAY ITS LANGUAGE WRITES — [the `speech` keys, a text, its comparable words] — identical, row for
  /// row, to `speechPackAsWritten()` of `backend2/tests/Unit/Plan/LanguagePackTest.php`: the server hands its lists down
  /// in the canonical form of the text (`LanguagePack::speech()`), and the phone reads them in that form too.
  const de = {
    'number_words': {'dreißig': '30', 'Zwei': '2'},
  };
  const fr = {
    'number_words': {'quatre-vingt-dix': '90', 'vingt': '20', 'un': '1'},
    'number_tens_joiners': ['et'],
  };
  const ro = {
    'number_words': {'douăzeci': '20', 'unu': '1'},
    'number_tens_joiners': ['şi'],
  };
  const asWritten = <(String, Map<String, Object>, String, String)>[
    ('de «dreißig» in the pack, «dreißig» said', de, 'dreißig', '30'),
    ('de «dreißig» in the pack, «Dreissig» said', de, 'Dreissig Euro', '30 euro'),
    ('de «Zwei» in the pack', de, 'zwei', '2'),
    ('fr «quatre-vingt-dix» in the pack', fr, 'quatre-vingt-dix', '90'),
    ('fr «quatre-vingt-dix» said apart', fr, 'quatre vingt dix', '90'),
    ('fr «et» after the tens', fr, 'vingt et un', '21'),
    ('ro «şi» in the pack, «și» said', ro, 'douăzeci și unu', '21'),
    ('ro «şi» in the pack, «şi» said', ro, 'douăzeci şi unu', '21'),
    (
      'es capitals in the pack',
      {
        'number_words': {'Treinta': '30', 'uno': '1'},
        'number_tens_joiners': ['Y'],
      },
      'treinta y uno',
      '31',
    ),
    (
      'de two spellings of one entry — the first wins',
      {
        'number_words': {'dreißig': '30', 'dreissig': '31'},
      },
      'dreissig',
      '30',
    ),
    (
      'en «and» after a scale only',
      {
        'number_words': {'one': '1', 'five': '5', 'twenty': '20', 'hundred': '100'},
        'number_joiners': ['And'],
      },
      'twenty and five, one hundred and five',
      '20 and 5 105',
    ),
  ];

  // Canon (наряд LANG-1 §4): «списки речи — в той же канонической форме, что и текст, с которым они сравниваются», на обеих
  // сторонах. CATCHES an entry kept as written that no folded text can ever say («dreißig» against `dreissig`,
  // «quatre-vingt-dix» — one word — against three, the «ş» of a joiner against the «ș» of a folded text), capitals kept,
  // and two spellings of one entry fighting (the last one won).
  group('a pack written the way its language writes meets the text', () {
    for (final (name, keys, text, expected) in asWritten) {
      test(name, () {
        expect(SpeechMatch.words(text, SpeechRules.fromJson(keys)).join(' '), expected);
      });
    }
  });

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
    test('a glued run of the expected text\'s own words counts as all of them', () {
      // The server's `SpokenWordBoundary::decompose()`, walk for walk (FIX-2 §2): every piece must be a word the
      // card expects, and nothing more is asked — not adjacency, not the card's own order, up to three pieces. The
      // phone used to demand an adjacent PAIR, which made it refuse readings the server accepts.
      expect(SpeechMatch.unglue('workschedule', {'my', 'work', 'schedule'}), ['work', 'schedule']);
      expect(SpeechMatch.unglue('schedulework', {'my', 'work', 'schedule'}), ['schedule', 'work']);
      expect(SpeechMatch.unglue('myschedule', {'my', 'work', 'schedule'}), ['my', 'schedule']);
      expect(SpeechMatch.unglue('couldyoutake', {'could', 'you', 'take', 'a', 'photo'}), ['could', 'you', 'take']);
      expect(SpeechMatch.unglue('carpet', {'the', 'red', 'car'}), isNull, reason: 'no word is invented: «pet» is not asked for');
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
