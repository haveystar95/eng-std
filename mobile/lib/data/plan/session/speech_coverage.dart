/// SPEECH COVERAGE — whether the learner said enough words of the string (work order SESSION-1b; the server has
/// the same rule — `Plan/Domain/Service/SpeechCoverage` on top of
/// `Shared/Domain/Service/LexicalNormalizer::canonicalize`).
///
/// The contract rule («Speech coverage»): an expected text of ≤ 2 significant words requires all of them, of 3
/// or more — 70 %; words are matched as a multiset and regardless of order (the recognizer drops words and swaps
/// them around, but does not reorder the string); the TARGET LANGUAGE's articles are forgiven, for a language
/// without articles nothing is forgiven. The card's threshold arrives in the payload (`coverage_min`) — the client
/// does not choose it, [minFor] is here for cross-checking against the server in tests.
///
/// Words are compared in the same canonical form as on the server: lowercase, English contractions expanded
/// («doesn't» → «does not», «he's been» → «he has been»), the apostrophe removed (it joins letters rather than
/// separating words), other marks and hyphens — a space («X-ray» — two words `x ray`), spaces collapsed.
/// Unicode folding — as the server does for comparison: comma-below instead of cedilla, `ß` → `ss`, `œ` → `oe`
/// (Dart has no NFC composition; the iOS recognizer returns composed letters anyway).
///
/// Gluing (polish pass SESSION-1b′, item 6): the recognizer sometimes loses the space between words
/// («workschedule»); a heard word that is not in the expected text but equals two ADJACENT expected words without
/// a space counts as both ([heardWords]). The server does not split gluing — the client is more lenient here, not
/// stricter.
///
/// Pure functions, not a single widget and not a single network call.
library;

abstract final class SpeechCoverage {
  /// «Say everything».
  static const double all = 1.0;

  /// «Say most of it» — 70 %.
  static const double most = 0.7;

  /// Up to how many significant words a string counts as short.
  static const int shortWords = 2;

  /// The precision of comparing a share with the threshold (7 of 10 is exactly 0.7).
  static const double _epsilon = 1e-9;

  /// The articles of the target language's pack. Packs live on the server; the client knows only English; the
  /// other languages have no articles in their pack or no pack at all — nothing is forgiven.
  static Set<String> articlesFor(String targetLang) => switch (targetLang) {
    'en' => const {'a', 'an', 'the'},
    _ => const {},
  };

  /// The comparable words of a text — canonical form, one by one.
  static List<String> words(String text) {
    final canonical = canonicalize(text);
    if (canonical.isEmpty) return const [];
    return canonical.split(' ');
  }

  /// How many words of the expected text count: all except articles.
  static int countedWords(String expected, Set<String> articles) =>
      _withoutArticles(words(expected), articles).length;

  /// The share a string asks for: all for a short one, most for a long one.
  static double minFor(String expected, Set<String> articles) =>
      countedWords(expected, articles) <= shortWords ? all : most;

  /// Whether no less than [min] of the words of [expected] (without articles) were heard, each heard word once.
  static bool covers(String heard, String expected, double min, Set<String> articles) =>
      coverageOf(heard, expected, articles) + _epsilon >= min && _withoutArticles(words(expected), articles).isNotEmpty;

  /// The share of the words of [expected] (without articles) that are in [heard] — as a multiset. An empty
  /// expected text — 0.
  static double coverageOf(String heard, String expected, Set<String> articles) {
    final wanted = _withoutArticles(words(expected), articles);
    if (wanted.isEmpty) return 0;
    final available = _counts(heardWords(heard, expected));
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

  /// How many words of [heard] (without articles) remain beyond the words of [expected] — as a multiset.
  static int extraWords(String heard, String expected, Set<String> articles) {
    final left = _counts(_withoutArticles(words(expected), articles));
    var extra = 0;
    for (final w in _withoutArticles(heardWords(heard, expected), articles)) {
      final n = left[w] ?? 0;
      if (n > 0) {
        left[w] = n - 1;
      } else {
        extra++;
      }
    }
    return extra;
  }

  /// The comparable words of [heard] with gluings split: a word that is not in [expected] but equals two
  /// adjacent words of [expected] without a space is both of them.
  static List<String> heardWords(String heard, String expected) {
    final got = words(heard);
    final want = words(expected);
    if (got.isEmpty || want.length < 2) return got;
    final known = want.toSet();
    return [
      for (final w in got)
        if (known.contains(w)) w else ...(unglue(w, want) ?? [w]),
    ];
  }

  /// Two adjacent words of [expected] (in comparable form) glued into [token] — or null.
  static List<String>? unglue(String token, List<String> expected) {
    for (var i = 0; i + 1 < expected.length; i++) {
      final a = expected[i];
      final b = expected[i + 1];
      if (a.length + b.length == token.length && token.startsWith(a) && token.endsWith(b)) return [a, b];
    }
    return null;
  }

  static Map<String, int> _counts(List<String> words) {
    final out = <String, int>{};
    for (final w in words) {
      out[w] = (out[w] ?? 0) + 1;
    }
    return out;
  }

  /// Whether the words of [value] stand consecutively in [heard] — articles do not count on either side.
  static bool containsSequence(String heard, String value, Set<String> articles) {
    final needle = _withoutArticles(words(value), articles);
    final haystack = _withoutArticles(heardWords(heard, value), articles);
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

  /// The canonical form of a string — a mirror of the server's `LexicalNormalizer::canonicalize()`.
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

  /// The server's folding for comparison: cedilla → comma below, `ß` → `ss`, `œ` → `oe`.
  static String _fold(String value) => value
      .replaceAll('ş', 'ș')
      .replaceAll('Ş', 'Ș')
      .replaceAll('ţ', 'ț')
      .replaceAll('Ţ', 'Ț')
      .replaceAll('ß', 'ss')
      .replaceAll('ẞ', 'SS')
      .replaceAll('œ', 'oe')
      .replaceAll('Œ', 'OE');

  /// English contractions — the same vetted list as on the server, and the same «before been» rule.
  static String _expandContractions(String value) {
    var v = value;
    for (final glyph in const ['’', '‘', '´', '`']) {
      v = v.replaceAll(glyph, "'");
    }
    // «he's been» — only «he has been», «I'd been» — only «I had been»: here the grammar decides by itself.
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
