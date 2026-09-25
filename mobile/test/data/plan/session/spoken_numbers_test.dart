import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/speech_match.dart';
import 'package:eng_std/data/plan/session/spoken_numbers.dart';

/// A NUMBER SAID IN WORDS IS THE NUMBER IN DIGITS, IN EVERY LANGUAGE OF THE PLAN — the phone's half (наряд FIX-3 §4,
/// DECISIONS п. 393; наряд LANG-1 — entries of several words, a joiner after a tens word).
///
/// The rule is ONE on the server and on the phone, so the example table below is the server's, row for row:
/// `backend2/tests/Unit/Shared/SpokenNumbersTest.php`. A row changes in both files or in neither — a number the phone
/// reads differently is a verdict the server would not give.
///
/// The packs are SYNTHETIC — the real `number_words` of es, ro, fr, de, pl, it arrive with their languages; each map
/// here holds only what its rows need, in the canonical form an entry takes (lower case, «ß» as «ss», one space between
/// the words of one entry). English is the pack's own table (the server's test pins its copy to `en.php`).
void main() {
  const packs = <String, SpeechRules>{
    'en': SpeechRules(
      numberWords: {
        'zero': '0',
        'one': '1',
        'two': '2',
        'three': '3',
        'four': '4',
        'five': '5',
        'six': '6',
        'seven': '7',
        'eight': '8',
        'nine': '9',
        'ten': '10',
        'eleven': '11',
        'twelve': '12',
        'thirteen': '13',
        'fourteen': '14',
        'fifteen': '15',
        'sixteen': '16',
        'seventeen': '17',
        'eighteen': '18',
        'nineteen': '19',
        'twenty': '20',
        'thirty': '30',
        'forty': '40',
        'fifty': '50',
        'sixty': '60',
        'seventy': '70',
        'eighty': '80',
        'ninety': '90',
        'hundred': '100',
        'thousand': '1000',
        'million': '1000000',
      },
      articles: {'a', 'an', 'the'},
      numberJoiners: {'and'},
    ),
    'es': SpeechRules(
      numberWords: {
        'un': '1',
        'uno': '1',
        'dos': '2',
        'tres': '3',
        'cinco': '5',
        'veinte': '20',
        'veintiuno': '21',
        'treinta': '30',
        'cien': '100',
        'ciento': '100',
        'quinientos': '500',
        'mil': '1000',
      },
      articles: {'el', 'la', 'los', 'las', 'un', 'una'},
      numberJoiners: {'y'},
    ),
    'ro': SpeechRules(
      numberWords: {
        'unu': '1',
        'doi': '2',
        'două': '2',
        'trei': '3',
        'cinci': '5',
        'douăzeci': '20',
        'sută': '100',
        'sute': '100',
        'mie': '1000',
        'mii': '1000',
      },
      articles: {'un', 'o'},
      numberJoiners: {'și'},
    ),
    'fr': SpeechRules(
      numberWords: {
        'un': '1',
        'deux': '2',
        'quatre': '4',
        'sept': '7',
        'huit': '8',
        'neuf': '9',
        'dix': '10',
        'onze': '11',
        'vingt': '20',
        'soixante': '60',
        'soixante dix': '70',
        'soixante et onze': '71',
        'quatre vingt': '80',
        'quatre vingts': '80',
        'quatre vingt dix': '90',
        'quatre vingt dix neuf': '99',
        'cent': '100',
        'cents': '100',
        'mille': '1000',
      },
      articles: {'le', 'la', 'les', 'un', 'une'},
      numberJoiners: {'et'},
    ),
    'de': SpeechRules(
      numberWords: {
        'eins': '1',
        'zwei': '2',
        'zwanzig': '20',
        'einundzwanzig': '21',
        'dreissig': '30',
        'hundert': '100',
        'tausend': '1000',
      },
      articles: {'der', 'die', 'das', 'ein', 'eine'},
    ),
    'pl': SpeechRules(
      numberWords: {
        'jeden': '1',
        'dwa': '2',
        'dwadzieścia': '20',
        'trzydzieści': '30',
        'sto': '100',
        'dwieście': '200',
        'tysiąc': '1000',
        'tysiące': '1000',
      },
    ),
    'it': SpeechRules(
      numberWords: {'uno': '1', 'venti': '20', 'ventuno': '21', 'cento': '100', 'mille': '1000'},
      articles: {'il', 'lo', 'la', 'un', 'una'},
    ),
    'none': SpeechRules(),
  };

  /// THE EXAMPLE TABLE — [pack, text, its words once the numbers are read] — identical, row for row, to the one in
  /// `backend2/tests/Unit/Shared/SpokenNumbersTest.php`.
  const table = <List<String>>[
    // English as FIX-3 §4 read it: the same rows as SpeechMatchTest and speech_match_test.dart.
    ['en', "I'll rest for forty-five seconds.", 'i will rest for 45 seconds'],
    ['en', 'I will rest for 45 seconds', 'i will rest for 45 seconds'],
    ['en', 'twenty one', '21'],
    ['en', 'Take one minute, then twenty-one reps', 'take 1 minute then 21 reps'],
    ['en', 'a hundred dollars', '100 dollars'],
    ['en', 'one hundred twenty-five', '125'],
    ['en', 'two thousand five hundred', '2500'],
    ['en', 'ten five', '10 5'],
    ['en', 'two three', '2 3'],
    ['en', 'twenty twelve', '20 12'],
    ['en', 'a bar', 'a bar'],
    ['en', 'one hundred and twenty', '120'],
    ['en', 'a hundred and five dollars', '105 dollars'],
    ['en', 'two thousand and five', '2005'],
    ['en', 'five and six', '5 and 6'],
    ['en', 'ten and five', '10 and 5'],
    ['en', 'two hundred and a thousand', '200 and 1000'],
    ['en', 'a hundred and twenty and five', '120 and 5'],
    ['en', 'one million', '1000000'],
    ['en', 'one hundred and twenty thousand and five', '120005'],
    ['en', 'two thousand and five hundred and twenty', '2520'],
    // A joiner is never taken and left without its value: nought after it does not fit, so «and» stays a word.
    ['en', 'a hundred and zero', '100 and 0'],
    // What LANG-1 changes in English: a tens word «and» did not bring in joins a unit after «and», and a scale after
    // that unit multiplies it. The second row is THE COST, pinned so it is seen — not a reading anyone wants: two
    // numbers read as one, so a recogniser's «between 20 and 100 dollars» no longer matches (it was «between 20 and
    // 100 dollars» before LANG-1). It flips back only with a key of the pack saying after what en «and» stands.
    ['en', 'twenty and five', '25'],
    ['en', 'between twenty and one hundred dollars', 'between 2100 dollars'],
    // Spanish: «y» between the tens and the unit, in each part of a number; 21–29 are one word.
    ['es', 'treinta y uno', '31'],
    ['es', 'veintiuno', '21'],
    ['es', 'ciento veinte', '120'],
    ['es', 'ciento treinta y uno euros', '131 euros'],
    ['es', 'treinta y un mil quinientos treinta y dos', '31532'],
    ['es', 'uno y dos', '1 y 2'],
    ['es', 'veinte y', '20 y'],
    ['es', 'mil y quinientos', '1000 y 500'],
    // Romanian: «și» after the tens, the article «o» before a scale, the plural forms of the scales.
    ['ro', 'douăzeci și unu', '21'],
    ['ro', 'douăzeci şi unu', '21'],
    ['ro', 'o sută', '100'],
    ['ro', 'două sute', '200'],
    ['ro', 'trei sute douăzeci și cinci de lei', '325 de lei'],
    ['ro', 'o mie', '1000'],
    ['ro', 'două mii', '2000'],
    // French: counting by twenties through entries of several words, the longest one read, «et» after the tens.
    ['fr', 'vingt et un', '21'],
    ['fr', 'soixante dix', '70'],
    ['fr', 'soixante-dix-sept', '77'],
    ['fr', 'soixante et onze', '71'],
    ['fr', 'quatre vingt', '80'],
    ['fr', 'quatre vingts', '80'],
    ['fr', 'quatre vingt dix neuf', '99'],
    ['fr', 'quatre-vingt-dix-huit', '98'],
    ['fr', 'quatre enfants', '4 enfants'],
    ['fr', 'cent vingt', '120'],
    ['fr', 'deux cents', '200'],
    ['fr', 'cent quatre vingt', '180'],
    ['fr', 'mille et un', '1001'],
    ['fr', 'vingt et onze', '20 et 11'],
    // What stands after the joiner is read as an entry too, the longest: «quatre vingts» is 80, which does not fit
    // after «vingt» — two numbers, «et» between them — not the 4 of «quatre», which would.
    ['fr', 'entre vingt et quatre-vingts euros', 'entre 20 et 80 euros'],
    // German: a number below a hundred is one word; «ß» is folded before the numbers are read.
    ['de', 'einundzwanzig', '21'],
    ['de', 'hundert', '100'],
    ['de', 'dreißig', '30'],
    ['de', 'dreissig Euro', '30 euro'],
    // Polish: no joiner, no article; the hundreds are words of their own.
    ['pl', 'dwadzieścia jeden', '21'],
    ['pl', 'sto dwadzieścia', '120'],
    ['pl', 'dwieście trzydzieści dwa', '232'],
    ['pl', 'dwa tysiące', '2000'],
    // Italian: 21 is one word.
    ['it', 'ventuno', '21'],
    ['it', 'cento venti', '120'],
    // A pack that names no number words reads none.
    ['none', 'twenty one', 'twenty one'],
  ];

  // Canon (DECISIONS п. 393, наряд LANG-1): «слова одного числа — одно число, по одному правилу у сервера и телефона».
  // CATCHES, row by row: an entry of several words read word by word («quatre vingt» → «4 20»), a shorter entry read
  // where a longer one stands («soixante et onze» → «60 et 11»), a joiner after a tens word ignored («treinta y uno» →
  // «30 y 1») or taken twice («a hundred and twenty and five» → 125), a joiner joining what does not fit («vingt et
  // onze», «uno y dos», «mil y quinientos»), a joiner swallowed with nothing after it («veinte y») or with nought after
  // it («a hundred and zero» → «100 0»), the value after a joiner judged by its first word alone («entre vingt et
  // quatre-vingts» → «entre 20 80»), an article or a plural scale not read («o sută», «două sute»), the canonical
  // fold not reaching the numbers («dreißig», «soixante-dix-sept»), and any English reading of FIX-3 moved.
  group('the words of one number are that number, in every language of the plan', () {
    for (final row in table) {
      final pack = row[0];
      final text = row[1];
      final expected = row[2];
      test('$pack: «$text» → «$expected»', () {
        final rules = packs[pack]!;
        final words = SpokenNumbers.fold(
          SpeechMatch.canonicalize(text).split(' '),
          numberWords: rules.numberWords,
          articles: rules.articles,
          joiners: rules.numberJoiners,
        );
        expect(words.join(' '), expected);
      });
    }
  });
}
