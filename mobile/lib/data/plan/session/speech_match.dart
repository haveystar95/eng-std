/// DID THE LEARNER SAY IT — the phone's half of the ONE rule of spoken grading (work order FIX-2, item 2; the server
/// has the same one, `Shared/Domain/Service/SpeechMatch`, and nothing else).
///
/// Two modes, and the CARD says which ([SpeechMode], `speech_mode` in the payload — the share `coverage_min` is gone):
///
///   repeat  the text is ON SCREEN and the learner reads or repeats it: every CONTENT word of the expected text, in
///           its order. Nothing is forgiven but the words a recogniser eats ([SpeechRules.unstressed], from the
///           target's own pack) and what is spelling rather than speech; extra words heard around them do not matter.
///           A share was the wrong bar here and it cost a live day: «He has a rush» covers 70 % of «He has a rash» and
///           was graded «верно» on the owner's phone (пройденный день 20.09).
///   free    the learner says their OWN sentence and only the KEY is checked: all of a short key, 70 % of a longer
///           one, as a multiset and order-free — a recogniser drops and swaps words, it does not reorder them.
///
/// WHAT IT KNOWS ABOUT THE LANGUAGE is not written here: [SpeechRules] arrives with the day (`speech` of
/// `GET …/days/{n}`) and holds the target pack's own lists. The phone used to keep «a/an/the» in Dart; that is not a
/// fact about speech, it is a fact about English, and the server already has it written down.
///
/// Words are compared in the canonical form of the server's kernel: lowercase, English contractions expanded
/// («doesn't» → «does not», «he's been» → «he has been»), the apostrophe removed (it joins letters rather than
/// separating words), other marks and hyphens — a space («X-ray» — two words `x ray`), spaces collapsed; then the
/// pack's abbreviations folded to their letters («p.m.» → `pm`) and its number words written as digits («three» →
/// `3`). Unicode folding as the server does it — comma-below instead of cedilla, `ß` → `ss`, `œ` → `oe`.
///
/// The recogniser's own two habits are forgiven wherever words are counted, in both modes, exactly as the server
/// forgives them: a boundary it guessed differently ([heardWords] — «withoututilities» is «without utilities», «down
/// town» is «downtown») and a trailing sibilant it did not hear ([suffixEqual]). The client's read may never be
/// STRICTER than the server's — the rule this whole grader is pinned by.
///
/// Pure functions, not a single widget and not a single network call.
library;

import 'dart:math' as math;
import 'spoken_numbers.dart';

/// How a spoken attempt is compared with what was asked for — the card says which, on the wire.
enum SpeechMode {
  /// The text is on screen: every content word, in its order.
  repeat('repeat'),

  /// The learner's own sentence: a share of the key.
  free('free');

  const SpeechMode(this.wire);

  final String wire;

  /// The mode named in a payload; anything unknown (an older server, a broken row) reads as [free] — the looser of
  /// the two, so a build that does not understand the wire never fails a learner who spoke.
  static SpeechMode fromWire(Object? value) => switch (value) {
    'repeat' => SpeechMode.repeat,
    _ => SpeechMode.free,
  };
}

/// WHAT A COMPARISON OF SPEECH KNOWS ABOUT THE TARGET LANGUAGE — the `speech` block of the day, as the server sends
/// it. A language nobody has written a pack for arrives empty, and then nothing is forgiven.
class SpeechRules {
  const SpeechRules({
    this.unstressed = const {},
    this.articles = const {},
    this.abbreviations = const [],
    this.numberWords = const {},
    this.numberJoiners = const {},
    this.repeatMisses = 0,
  });

  /// The words a recogniser eats — articles, prepositions, auxiliaries: left out of BOTH sides in [SpeechMode.repeat].
  final Set<String> unstressed;

  /// The narrower list [SpeechMode.free] forgives, where the bar is a SHARE and dropping every function word would
  /// leave nothing to count.
  final Set<String> articles;

  /// Abbreviations AS THEY ARE WRITTEN («p.m.»): folded to their letters before anything else looks at the text.
  final List<String> abbreviations;

  /// A number word → the digits it says («three» → «3»); the words of ONE number are read as one number
  /// ([SpokenNumbers], наряд FIX-3 §4).
  final Map<String, String> numberWords;

  /// The words that join the parts of one number (en «and» of «one hundred and twenty»).
  final Set<String> numberJoiners;

  /// How many CONTENT words a line on the screen may lose and still pass — the server's `plan.speech.repeat_misses`.
  final int repeatMisses;

  /// Nothing known: every word counts, nothing is folded. What a day without the block falls back to.
  static const SpeechRules none = SpeechRules();

  factory SpeechRules.fromJson(Map<String, dynamic> j) => SpeechRules(
    unstressed: _words(j['unstressed_words']),
    articles: _words(j['articles']),
    abbreviations: [
      for (final v in (j['abbreviations'] as List?) ?? const [])
        if (v is String && v.isNotEmpty) v,
    ],
    numberWords: {
      for (final e in ((j['number_words'] as Map?) ?? const {}).entries)
        if (e.key is String && e.value is String) (e.key as String).toLowerCase(): e.value as String,
    },
    numberJoiners: _words(j['number_joiners']),
    repeatMisses: (j['repeat_misses'] as num?)?.toInt() ?? 0,
  );

  static Set<String> _words(Object? raw) => {
    for (final v in (raw as List?) ?? const [])
      if (v is String && v.isNotEmpty) v.toLowerCase(),
  };
}

abstract final class SpeechMatch {
  /// «Say everything» — the share a short key asks for.
  static const double all = 1.0;

  /// «Say most of it» — the share a longer key asks for.
  static const double most = 0.7;

  /// Up to how many counted words a key is short enough to be asked for whole.
  static const int shortWords = 2;

  /// The precision of comparing a share with the threshold (7 of 10 is exactly 0.7).
  static const double _epsilon = 1e-9;

  /// THE VERDICT OF ONE ATTEMPT, by the mode the card was dealt with.
  static bool said(String heard, String expected, SpeechMode mode, SpeechRules rules) => switch (mode) {
    SpeechMode.repeat => repeated(heard, expected, rules),
    SpeechMode.free => covers(heard, expected, minFor(expected, rules), rules),
  };

  /// `repeat`: every content word of [expected], in its order, in what was heard — [SpeechRules.repeatMisses] of them
  /// forgiven. A text of nothing but function words has no content to anchor on and is asked for whole.
  static bool repeated(String heard, String expected, SpeechRules rules) {
    final every = words(expected, rules);
    var wanted = _without(every, rules.unstressed);
    final drop = wanted.isEmpty ? const <String>{} : rules.unstressed;
    if (wanted.isEmpty) wanted = every;
    if (wanted.isEmpty) return false;
    final heardList = _without(heardWords(heard, expected, rules), drop);

    var at = 0;
    var lost = 0;
    for (final word in wanted) {
      var found = -1;
      for (var i = at; i < heardList.length; i++) {
        if (heardList[i] == word || suffixEqual(word, heardList[i])) {
          found = i;
          break;
        }
      }
      if (found < 0) {
        lost++;
        continue;
      }
      at = found + 1;
    }
    return lost <= (rules.repeatMisses < 0 ? 0 : rules.repeatMisses);
  }

  /// EVERY CONTENT WORD OF [expected] IS ALREADY IN WHAT WAS HEARD — when a recording may close on the short pause
  /// (наряд CLIENT-CONV-1b, «тишина по длине»). NOT A VERDICT: nothing is graded by it, the card's own rule still
  /// judges the whole transcript after the close; it only tells the microphone that the learner has said the line
  /// through and is not stopping in the middle of it. Content words as [repeated] counts them — the pack's unstressed
  /// words aside, all of a text made of nothing else — heard as a multiset in any order, the recogniser's boundaries
  /// and trailing sibilants forgiven. An empty [expected] asks for nothing, and there is nothing to be through with.
  static bool heardAll(String heard, String expected, SpeechRules rules) {
    final every = words(expected, rules);
    var wanted = _without(every, rules.unstressed);
    if (wanted.isEmpty) wanted = every;
    if (wanted.isEmpty) return false;
    final available = _counts(heardWords(heard, expected, rules));
    for (final w in wanted) {
      if (!_consume(available, w)) return false;
    }
    return true;
  }

  /// `free`: were at least [min] of the key's words (articles aside) heard, each heard word spent once?
  static bool covers(String heard, String expected, double min, SpeechRules rules) =>
      coverageOf(heard, expected, rules) + _epsilon >= min && _without(words(expected, rules), rules.articles).isNotEmpty;

  /// The share of the key's words (articles aside) present in what was heard — as a multiset. An empty key — 0.
  static double coverageOf(String heard, String expected, SpeechRules rules) {
    final wanted = _without(words(expected, rules), rules.articles);
    if (wanted.isEmpty) return 0;
    final available = _counts(heardWords(heard, expected, rules));
    var found = 0;
    for (final w in wanted) {
      if (_consume(available, w)) found++;
    }
    return found / wanted.length;
  }

  /// How many words of the key count: all except the target's articles.
  static int countedWords(String expected, SpeechRules rules) => _without(words(expected, rules), rules.articles).length;

  /// The share a key asks for: all of a short one, most of a longer one.
  static double minFor(String expected, SpeechRules rules) => countedWords(expected, rules) <= shortWords ? all : most;

  /// How many words of [heard] (articles aside) remain beyond the words of [expected] — as a multiset.
  static int extraWords(String heard, String expected, SpeechRules rules) {
    final left = _counts(_without(words(expected, rules), rules.articles));
    var extra = 0;
    for (final w in _without(heardWords(heard, expected, rules), rules.articles)) {
      if (!_consume(left, w)) extra++;
    }
    return extra;
  }

  /// A SPACE IS THE RECOGNISER'S GUESS — the heard words re-cut against the expected text's own vocabulary, in both
  /// directions, exactly as the server's `SpokenWordBoundary` does it: a token that is not an expected word but is two
  /// or more of them written without the spaces becomes those words («withoututilities» → «without utilities»), and a
  /// run of tokens whose concatenation IS an expected word becomes that word («down town» → «downtown»). Keyed on the
  /// EXPECTED side both ways, so nothing here can invent a word the learner did not say.
  static List<String> heardWords(String heard, String expected, [SpeechRules rules = SpeechRules.none]) {
    final got = words(heard, rules);
    final want = words(expected, rules);
    if (got.isEmpty || want.isEmpty) return got;
    final known = want.toSet();
    final split = [
      for (final w in got)
        if (known.contains(w)) w else ...(unglue(w, known) ?? [w]),
    ];
    return _join(split, known);
  }

  /// SPLIT BACK TOGETHER: «down», «town» → «downtown». Left to right and greedy, and only from a token the vocabulary
  /// does not already know — a run whose first word is an expected word is a run the sentence asked for.
  static List<String> _join(List<String> heard, Set<String> vocabulary) {
    final out = <String>[];
    for (var i = 0; i < heard.length; i++) {
      if (vocabulary.contains(heard[i])) {
        out.add(heard[i]);
        continue;
      }
      var joined = heard[i];
      var taken = 1;
      for (var span = 1; span < _maxJoin && i + span < heard.length; span++) {
        joined += heard[i + span];
        if (vocabulary.contains(joined)) {
          taken = span + 1;
          break;
        }
      }
      out.add(taken == 1 ? heard[i] : joined);
      i += taken - 1;
    }
    return out;
  }

  /// FORGIVES THE CHANNEL, NOT THE MEMORY (QA-20, mirrored from the server's `SpokenSuffixTolerance`): an on-device
  /// recogniser drops a trailing sibilant far more than it invents or swaps a whole word, so «salary expectation» for
  /// «salary expectations» is a microphone fact. Three tails and nothing else — «expect» against «expectations»
  /// differs by more and stays a miss.
  static bool suffixEqual(String a, String b) {
    if (a == b) return true;
    for (final tail in const ['s', 'es', ' s']) {
      if (a + tail == b || b + tail == a) return true;
    }
    return false;
  }

  /// Marks one occurrence of [word] as used and returns true — exact first, then suffix-tolerant.
  static bool _consume(Map<String, int> available, String word) {
    final left = available[word] ?? 0;
    if (left > 0) {
      available[word] = left - 1;
      return true;
    }
    for (final e in available.entries) {
      if (e.value > 0 && suffixEqual(word, e.key)) {
        available[e.key] = e.value - 1;
        return true;
      }
    }
    return false;
  }

  /// THE EXPECTED WORDS [token] IS MADE OF, in order, or null when it is not made of them — a mirror of the server's
  /// `SpokenWordBoundary::decompose()`, walk for walk: left to right, the LONGEST prefix first, backtracking, at most
  /// [_maxJoin] pieces.
  ///
  /// It asks only that every piece be a word the card expects, NOT that the pieces stand next to each other in the
  /// card's own order — a recogniser glues what it hears, and «couldyoutake» is three of them. The phone used to ask
  /// for two ADJACENT words, which made it refuse readings the server accepts; that is the one thing a client check
  /// may never do (work order FIX-2 §2, invariant «клиентская проверка не строже серверной»). Before FIX-2 the
  /// plan's server rule had no boundary pass at all and the narrower client rule was merely looser; the kernel merge
  /// gave the server the full walk and would have left the mirror behind.
  static List<String>? unglue(String token, Set<String> expected, [int depth = 0]) {
    if (depth >= _maxJoin) return null;
    for (var take = token.length - 1; take >= 1; take--) {
      final head = token.substring(0, take);
      if (!expected.contains(head)) continue;
      final tail = token.substring(take);
      if (expected.contains(tail)) return [head, tail];
      final rest = unglue(tail, expected, depth + 1);
      if (rest != null) return [head, ...rest];
    }
    return null;
  }

  /// How many heard tokens may be joined back into one expected word, and how many expected words one glued token may
  /// be cut into — the server's `SpokenWordBoundary::MAX_JOIN`.
  static const int _maxJoin = 3;

  /// THE WORDS HEARD BEYOND [key] — the SPOKEN words of [heard] that [key]'s own words do not account for, in the order
  /// heard, each in its comparable form (a spoken word that folds into several comparable words stays one entry,
  /// «over the counter»). A mirror of the server's `SpeechMatch::slotWords()` as its judge reads it: the word is the
  /// spoken one — it belongs to the key only when EVERY comparable word it folds into is still unspent there, else the
  /// whole spoken word is left over; and the words are EXACT — no trailing sibilant forgiven, no boundary re-cut —
  /// because what matters is what the server would find left over. Counted per comparable word instead, «the
  /// over-the-counter» against the key «over-the-counter» left «the» over where the server leaves «over-the-counter» —
  /// the phone refused what the server hands to the model (invariant review, CLIENT-CONV-1c).
  static List<String> beyondKey(String heard, String key, SpeechRules rules) {
    final available = _counts(words(key, rules));
    final surface = heard.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
    final stream = words(surface.join(' '), rules);
    final beyond = <String>[];
    var taken = 0;
    for (var i = 0; i < surface.length; i++) {
      // The comparable words of THIS spoken word: what the whole line folds into, less what the words after it fold into.
      final rest = words(surface.skip(i + 1).join(' '), rules).length;
      final count = math.max(0, stream.length - rest - taken);
      final tokens = stream.sublist(taken, taken + count);
      taken += count;
      if (tokens.isEmpty) continue;
      final need = _counts(tokens);
      if (need.entries.every((e) => (available[e.key] ?? 0) >= e.value)) {
        need.forEach((token, n) => available[token] = available[token]! - n);
        continue;
      }
      beyond.add(tokens.join(' '));
    }
    return beyond;
  }

  /// Whether the words of [value] stand consecutively in [heard] — articles do not count on either side.
  static bool containsSequence(String heard, String value, SpeechRules rules) {
    final needle = _without(words(value, rules), rules.articles);
    final haystack = _without(heardWords(heard, value, rules), rules.articles);
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

  /// THE COMPARABLE WORDS OF A TEXT: the pack's abbreviations folded to their letters, then the kernel's canonical
  /// form, then the words of one NUMBER read as that number ([SpokenNumbers]) — the same three steps, in the same
  /// order, as the server's `SpeechMatch::words()`.
  static List<String> words(String text, [SpeechRules rules = SpeechRules.none]) {
    final canonical = canonicalize(_foldAbbreviations(text, rules.abbreviations));
    if (canonical.isEmpty) return const [];

    return SpokenNumbers.fold(
      canonical.split(' '),
      numberWords: rules.numberWords,
      articles: rules.articles,
      joiners: rules.numberJoiners,
    );
  }

  /// An abbreviation written as its letters: «3 p.m.» → «3 pm». Matched as a whole token, letter case aside.
  static String _foldAbbreviations(String text, List<String> abbreviations) {
    var out = text;
    for (final abbreviation in abbreviations) {
      final bare = abbreviation.replaceAll('.', '');
      if (bare.isEmpty) continue;
      out = out.replaceAll(
        RegExp('(?<![\\p{L}\\p{N}])${RegExp.escape(abbreviation)}', unicode: true, caseSensitive: false),
        bare,
      );
    }
    return out;
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

  static List<String> _without(List<String> words, Set<String> drop) =>
      drop.isEmpty ? words : [for (final w in words) if (!drop.contains(w)) w];

  static Map<String, int> _counts(List<String> words) {
    final out = <String, int>{};
    for (final w in words) {
      out[w] = (out[w] ?? 0) + 1;
    }
    return out;
  }

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
