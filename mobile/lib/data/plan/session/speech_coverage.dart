/// ПОКРЫТИЕ РЕЧИ — сказал ли ученик достаточно слов строки (наряд SESSION-1b; на сервере то же правило —
/// `Plan/Domain/Service/SpeechCoverage` поверх `Shared/Domain/Service/LexicalNormalizer::canonicalize`).
///
/// Правило контракта («Покрытие речи»): ожидаемый текст из ≤ 2 значащих слов требует всех, из 3 и больше —
/// 70 %; слова сверяются мультимножеством и без порядка (распознаватель роняет и меняет слова местами, но
/// не переставляет строку); артикли ЯЗЫКА ЦЕЛИ прощаются, у языка без артиклей не прощается ничего. Порог
/// карточки приходит в payload (`coverage_min`) — клиент его не выбирает, [minFor] здесь ради сверки с
/// сервером в тестах.
///
/// Слова сравниваются в той же канонической форме, что на сервере: нижний регистр, раскрытые английские
/// сокращения («doesn't» → «does not», «he's been» → «he has been»), апостроф снят (соединяет буквы, а не
/// разделяет слова), остальные знаки и дефисы — пробел («X-ray» — два слова `x ray`), пробелы схлопнуты.
/// Юникод-свёртка — как у сервера для сравнения: запятая-под-буквой вместо седили, `ß` → `ss`, `œ` → `oe`
/// (NFC-композиции в Dart нет; распознаватель iOS и так отдаёт составные буквы).
///
/// Чистые функции, ни одного виджета и ни одного обращения к сети.
library;

abstract final class SpeechCoverage {
  /// «Сказать всё».
  static const double all = 1.0;

  /// «Сказать большую часть» — 70 %.
  static const double most = 0.7;

  /// До скольких значащих слов строка считается короткой.
  static const int shortWords = 2;

  /// Точность сравнения доли с порогом (7 из 10 — ровно 0.7).
  static const double _epsilon = 1e-9;

  /// Артикли пакета языка цели. Пакеты живут на сервере; клиенту известен только английский, у
  /// остальных языков артиклей в пакете нет или пакета нет вовсе — не прощается ничего.
  static Set<String> articlesFor(String targetLang) => switch (targetLang) {
    'en' => const {'a', 'an', 'the'},
    _ => const {},
  };

  /// Сравнимые слова текста — каноническая форма, по одному.
  static List<String> words(String text) {
    final canonical = canonicalize(text);
    if (canonical.isEmpty) return const [];
    return canonical.split(' ');
  }

  /// Сколько слов ожидаемого текста считается: все, кроме артиклей.
  static int countedWords(String expected, Set<String> articles) =>
      _withoutArticles(words(expected), articles).length;

  /// Доля, которую просит строка: всё у короткой, большая часть у длинной.
  static double minFor(String expected, Set<String> articles) =>
      countedWords(expected, articles) <= shortWords ? all : most;

  /// Услышано ли не меньше [min] слов [expected] (без артиклей), каждое услышанное слово — один раз.
  static bool covers(String heard, String expected, double min, Set<String> articles) =>
      coverageOf(heard, expected, articles) + _epsilon >= min && _withoutArticles(words(expected), articles).isNotEmpty;

  /// Доля слов [expected] (без артиклей), которые есть в [heard], — мультимножеством. Пустое ожидаемое — 0.
  static double coverageOf(String heard, String expected, Set<String> articles) {
    final wanted = _withoutArticles(words(expected), articles);
    if (wanted.isEmpty) return 0;
    final available = <String, int>{};
    for (final w in words(heard)) {
      available[w] = (available[w] ?? 0) + 1;
    }
    var found = 0;
    for (final w in wanted) {
      final left = available[w] ?? 0;
      if (left > 0) {
        available[w] = left - 1;
        found++;
      }
    }
    return found / wanted.length;
  }

  /// Стоят ли слова [value] в [heard] подряд — артикли не в счёт с обеих сторон.
  static bool containsSequence(String heard, String value, Set<String> articles) {
    final needle = _withoutArticles(words(value), articles);
    final haystack = _withoutArticles(words(heard), articles);
    final n = needle.length;
    if (n == 0 || n > haystack.length) return false;
    for (var i = 0; i + n <= haystack.length; i++) {
      var match = true;
      for (var k = 0; k < n; k++) {
        if (haystack[i + k] != needle[k]) {
          match = false;
          break;
        }
      }
      if (match) return true;
    }
    return false;
  }

  /// Каноническая форма строки — зеркало серверного `LexicalNormalizer::canonicalize()`.
  static String canonicalize(String value) {
    var v = _fold(value).toLowerCase().trim();
    v = _expandContractions(v);
    v = v.replaceAll(RegExp("[${_apostrophes.join()}]"), '');
    v = v.replaceAll(RegExp(r'[^\p{L}\p{N}\s]+', unicode: true), ' ');
    v = v.replaceAll(RegExp(r'\s+', unicode: true), ' ');
    return v.trim();
  }

  static List<String> _withoutArticles(List<String> words, Set<String> articles) =>
      articles.isEmpty ? words : [for (final w in words) if (!articles.contains(w)) w];

  static const List<String> _apostrophes = ['’', '‘', '´', '`', "'"];

  /// Свёртка сервера для сравнения: седиль → запятая под буквой, `ß` → `ss`, `œ` → `oe`.
  static String _fold(String value) => value
      .replaceAll('ş', 'ș')
      .replaceAll('Ş', 'Ș')
      .replaceAll('ţ', 'ț')
      .replaceAll('Ţ', 'Ț')
      .replaceAll('ß', 'ss')
      .replaceAll('ẞ', 'SS')
      .replaceAll('œ', 'oe')
      .replaceAll('Œ', 'OE');

  /// Английские сокращения — тот же выверенный список, что на сервере, и то же правило «перед been».
  static String _expandContractions(String value) {
    var v = value;
    for (final glyph in const ['’', '‘', '´', '`']) {
      v = v.replaceAll(glyph, "'");
    }
    // «he's been» — только «he has been», «I'd been» — только «I had been»: здесь грамматика решает сама.
    v = v.replaceAllMapped(RegExp(r"\b([a-z]+)'s(\s+been\b)"), (m) => '${m[1]} has${m[2]}');
    v = v.replaceAllMapped(RegExp(r"\b([a-z]+)'d(\s+been\b)"), (m) => '${m[1]} had${m[2]}');
    return v.replaceAllMapped(RegExp(r"\b[a-z]+'[a-z]+\b"), (m) => _contractions[m[0]] ?? m[0]!);
  }

  static const Map<String, String> _contractions = {
    "i'd": 'i would',
    "i'll": 'i will',
    "i'm": 'i am',
    "i've": 'i have',
    "you're": 'you are',
    "you'd": 'you would',
    "you'll": 'you will',
    "you've": 'you have',
    "we're": 'we are',
    "we'd": 'we would',
    "we'll": 'we will',
    "we've": 'we have',
    "they're": 'they are',
    "they'd": 'they would',
    "they'll": 'they will',
    "they've": 'they have',
    "it's": 'it is',
    "that's": 'that is',
    "there's": 'there is',
    "let's": 'let us',
    "don't": 'do not',
    "doesn't": 'does not',
    "didn't": 'did not',
    "isn't": 'is not',
    "aren't": 'are not',
    "wasn't": 'was not',
    "weren't": 'were not',
    "can't": 'cannot',
    "won't": 'will not',
    "wouldn't": 'would not',
    "couldn't": 'could not',
    "shouldn't": 'should not',
    "haven't": 'have not',
    "hasn't": 'has not',
    "hadn't": 'had not',
  };
}
