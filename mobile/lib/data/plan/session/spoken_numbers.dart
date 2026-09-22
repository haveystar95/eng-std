/// A NUMBER SAID IN WORDS IS THE NUMBER IN DIGITS — the phone's half of the server's rule (наряд FIX-3 §§2, 4).
///
/// The owner said «I will rest for 45 seconds» to a card that asked for «I'll rest for forty-five seconds» and was
/// failed twice (зал, день 2): the recogniser writes a number in digits, the lesson in words, and a word-by-word fold
/// turned «forty-five» into «40 5» — never «45».
///
/// THE RULE, over the canonical words of a text (case folded, contractions spelt out, every mark — the hyphen too — a
/// space, so «forty-five» is «forty five»), left to right. It is the server's `Shared/Domain/Service/SpokenNumbers`
/// word for word, and the lists it reads are the server's own (`speech.number_words`, `speech.articles`,
/// `speech.number_joiners` of the day) — never a copy of English in Dart:
///
/// 1. A word of `numberWords` is its value; an ARTICLE right before a SCALE counts as one («a hundred» → 100). A scale
///    is a value of 100 or more that is a power of ten (hundred, thousand, million).
/// 2. The words of one number join while the next one FITS:
///    - a scale fits after a number above nought and below it, and multiplies it («two hundred» → 200); a thousand or
///      more closes that part and a smaller number starts after it («two thousand five» → 2005);
///    - any other value fits after a word of 20 or more when it is smaller than that word's last place — the largest
///      power of ten that divides it — and above nought: «forty five» → 45, «hundred twenty» → 120; «ten five»,
///      «twenty twelve», «two three» do not fit and stay two numbers;
///    - a JOINER (en «and») between a scale and a value below a hundred that fits after it is part of the number:
///      «one hundred and twenty» → 120, «two thousand and five» → 2005; anywhere else it is a word of its own.
/// 3. A joined number is written in digits and replaces its words; digits the text already has stay as they are and
///    never join anything.
///
/// A verdict on the phone that the server would not give is a lie shown to the learner, so this file changes only
/// together with the server's.
abstract final class SpokenNumbers {
  /// [words] — canonical words; [numberWords] — word → the digits it says; [articles], [joiners] — the pack's lists.
  static List<String> fold(
    List<String> words, {
    required Map<String, String> numberWords,
    Set<String> articles = const {},
    Set<String> joiners = const {},
  }) {
    if (numberWords.isEmpty) return words;
    final values = <String, int>{};
    for (final e in numberWords.entries) {
      final v = int.tryParse(e.value);
      if (v != null) values[e.key] = v;
    }
    if (values.isEmpty) return words;

    final out = <String>[];
    final n = words.length;
    var i = 0;
    while (i < n) {
      final word = words[i];
      final articleOne = articles.contains(word) && _isScale(values[i + 1 < n ? words[i + 1] : '']);
      if (!values.containsKey(word) && !articleOne) {
        out.add(word);
        i++;
        continue;
      }
      var total = 0;
      var current = 0;
      int? last;
      if (articleOne) {
        current = 1;
        last = 1;
        i++;
      }
      while (i < n) {
        if (joiners.contains(words[i]) && _isScale(last)) {
          final next = values[i + 1 < n ? words[i + 1] : ''];
          if (next == null || _isScale(next) || next <= 0 || next >= 100) break;
          i++;
        }
        final value = values[words[i]];
        if (value == null) break;
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
        i++;
      }
      out.add('${total + current}');
    }

    return out;
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
