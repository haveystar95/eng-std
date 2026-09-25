import 'dart:math' as math;

/// A NUMBER SAID IN WORDS IS THE NUMBER IN DIGITS — the phone's half of the server's rule (наряд FIX-3 §§2, 4,
/// DECISIONS п. 393; the languages of the plan, наряд LANG-1).
///
/// The owner said «I will rest for 45 seconds» to a card that asked for «I'll rest for forty-five seconds» and was
/// failed twice (зал, день 2): the recogniser writes a number in digits, the lesson in words, and a word-by-word fold
/// turned «forty-five» into «40 5» — never «45».
///
/// THE RULE, over the canonical words of a text (case folded, contractions spelt out, every mark — the hyphen too — a
/// space, so «forty-five» is «forty five» and «quatre-vingt-dix» is «quatre vingt dix»), left to right. It is the
/// server's `Shared/Domain/Service/SpokenNumbers` word for word, and the lists it reads are the server's own
/// (`speech.number_words`, `speech.articles`, `speech.number_joiners` of the day) — never a copy of a language in Dart:
///
/// 1. An ENTRY of `numberWords` is its value. An entry is one word or SEVERAL, one space apart, in that canonical form
///    («soixante dix» → 70, «quatre vingt dix» → 90): at every place the LONGEST entry whose words stand there is read,
///    as one value — «quatre vingt dix neuf» with an entry for it is 99, «quatre vingt» 80, a lone «quatre» 4. An
///    ARTICLE right before a SCALE counts as one («a hundred» → 100, ro «o sută» → 100). A scale is a value of 100 or
///    more that is a power of ten (hundred, thousand, million; ro «sute», pl «tysiące» — a plural form of a scale is one
///    more entry of the same value).
/// 2. The values of one number join while the next one FITS:
///    - a scale fits after a number above nought and below it, and multiplies it («two hundred» → 200, ro «două sute» →
///      200); a thousand or more closes that part and a smaller number starts after it («two thousand five» → 2005);
///    - any other value fits after a value of 20 or more when it is smaller than that value's last place — the largest
///      power of ten that divides it — and above nought: «forty five» → 45, «hundred twenty» → 120, pl «dwadzieścia
///      jeden» → 21; «ten five», «twenty twelve», «two three» do not fit and stay two numbers;
///    - a JOINER stands between two values of one number when the value after it fits after the value before it and is
///      below a hundred — after a SCALE (en «and»: «one hundred and twenty» → 120, «two thousand and five» → 2005), or
///      after a TENS value — 20 or more, not a scale — that no joiner brought in (es «treinta y uno» → 31, ro «douăzeci
///      și unu» → 21, fr «vingt et un» → 21); «vingt et onze» stays three words (fr 71 is an entry of its own), and «a
///      hundred and twenty and five» is 120, «and», 5. Anywhere else the joiner is a word of its own («five and six»).
/// 3. A joined number is written in digits and replaces its words; digits the text already has stay as they are and
///    never join anything.
///
/// English reads as before LANG-1 but for one place: «tens and unit» — «twenty and five» is now 25, as es «veinte y
/// cinco» is, and a scale after the unit multiplies it, as es «treinta y un mil» → 31000 must: so en «between twenty and
/// one hundred dollars» — two numbers — is now «between 2100 dollars». On the server exactly the same; the way back for
/// English is a key of the pack, an open question of наряд LANG-1.
///
/// A verdict on the phone that the server would not give is a lie shown to the learner, so this file changes only
/// together with the server's, and `test/data/plan/session/spoken_numbers_test.dart` holds the server's example table
/// row for row.
abstract final class SpokenNumbers {
  /// [words] — canonical words; [numberWords] — an entry (a word, or several one space apart) → the digits it says;
  /// [articles], [joiners] — the pack's lists.
  static List<String> fold(
    List<String> words, {
    required Map<String, String> numberWords,
    Set<String> articles = const {},
    Set<String> joiners = const {},
  }) {
    if (numberWords.isEmpty) return words;
    final values = <String, int>{};
    var longest = 1;
    for (final e in numberWords.entries) {
      final v = int.tryParse(e.value);
      if (v == null) continue;
      values[e.key] = v;
      final size = e.key.split(' ').length;
      if (size > longest) longest = size;
    }
    if (values.isEmpty) return words;

    final out = <String>[];
    final n = words.length;
    var i = 0;
    while (i < n) {
      final word = words[i];
      final articleOne =
          articles.contains(word) && _isScale(_valueAt(words, i + 1, values, longest)?.$1);
      if (_valueAt(words, i, values, longest) == null && !articleOne) {
        out.add(word);
        i++;
        continue;
      }
      var total = 0;
      var current = 0;
      int? last;
      // Did a joiner bring [last] in? A tens value that one did takes no joiner after it.
      var joined = false;
      if (articleOne) {
        current = 1;
        last = 1;
        i++;
      }
      while (i < n) {
        var viaJoiner = false;
        if (joiners.contains(words[i]) &&
            last != null &&
            (_isScale(last) || (last >= 20 && !joined))) {
          final next = _valueAt(words, i + 1, values, longest)?.$1;
          if (next == null || next <= 0 || next >= math.min(100, _place(last))) break;
          viaJoiner = true;
          i++;
        }
        final at = _valueAt(words, i, values, longest);
        if (at == null) break;
        final (value, take) = at;
        if (last == null) {
          if (_isScale(value)) {
            final scaled = _scaled(0, 1, value);
            total = scaled[0];
            current = scaled[1];
          } else {
            total = 0;
            current = value;
          }
        } else if (_isScale(value) && current > 0 && current < value) {
          final scaled = _scaled(total, current, value);
          total = scaled[0];
          current = scaled[1];
        } else if (!_isScale(value) && last >= 20 && value > 0 && value < _place(last)) {
          current += value;
        } else {
          break;
        }
        last = value;
        joined = viaJoiner;
        i += take;
      }
      out.add('${total + current}');
    }

    return out;
  }

  /// The value that starts at [at] — the LONGEST entry whose words stand there — and how many words it takes; null when
  /// no entry starts there.
  static (int, int)? _valueAt(List<String> words, int at, Map<String, int> values, int longest) {
    for (var take = math.min(longest, words.length - at); take >= 1; take--) {
      final value = values[words.sublist(at, at + take).join(' ')];
      if (value != null) return (value, take);
    }
    return null;
  }

  /// The running total and the part after it, once a scale is said.
  static List<int> _scaled(int total, int current, int scale) =>
      scale >= 1000 ? [total + current * scale, 0] : [total, current * scale];

  static bool _isScale(int? value) => value != null && value >= 100 && _place(value) == value;

  /// The largest power of ten that divides a positive number: 40 → 10, 200 → 100, 1000 → 1000, 45 → 1.
  static int _place(int value) {
    var place = 1;
    while (value > 0 && value % (place * 10) == 0) {
      place *= 10;
    }

    return place;
  }
}
