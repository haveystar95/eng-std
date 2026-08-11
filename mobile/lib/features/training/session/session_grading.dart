import '../../../data/models.dart';

/// The client's INSTANT read of an answer — for feedback only. The server is the sole grader
/// (invariant): it re-grades every raw answer and schedules from that. This check exists so the
/// card can react the moment the user answers, and it is deliberately **never stricter than the
/// server** — it mirrors the server's [AnswerGrader] normalisation (case, spacing, punctuation,
/// contractions, optional leading article) and its single-character-typo tolerance on long-enough
/// answers, so anything the server would accept, the client also shows as accepted.
///
/// It knows only the card's own canonical answer, not the server's full synonym set, so it can be
/// *more* lenient in edge cases (a rejected synonym still schedules correctly once the server
/// folds it) — but it is never harsher. Pure and unit-tested.
enum LocalCheck {
  /// Exact after normalisation — «Верно».
  correct,

  /// One-character typo on a long-enough answer — accepted, shown as «Почти».
  typo,

  /// Not a match — «Не то».
  wrong;

  bool get isAccepted => this != wrong;
}

/// Mirror of the server `AnswerGrader`'s correctness stages (grading of the SPEED/hint into
/// again/hard/good/easy stays server-side — the client never needs it).
abstract final class SessionGrader {
  /// Typo leniency only kicks in for longer answers, else "cat"/"cut" pass as correct.
  static const int _minTypoLength = 5;

  /// Check a raw [response] against the card's canonical [answer]. Uniform across modes: a
  /// multiple-choice pick, an assembled word_bank string and typed text all normalise-compare the
  /// same way (matching the server, which grades every mode through one path).
  static LocalCheck check(String response, String answer) {
    final r = _normalize(response);
    final a = _normalize(answer);
    if (r.isEmpty) return LocalCheck.wrong; // «Не помню» / blank
    if (r == a) return LocalCheck.correct;
    if (_isTypo(r, a)) return LocalCheck.typo;
    return LocalCheck.wrong;
  }

  static bool _isTypo(String response, String candidate) {
    if (candidate.length < _minTypoLength) return false;
    return response != candidate && _levenshtein(response, candidate) == 1;
  }

  /// Lowercase, expand contractions, punctuation → space, whitespace collapsed, leading article
  /// dropped — the server's exact sequence.
  static String _normalize(String value) {
    var v = value.toLowerCase().trim();
    v = _expandContractions(v); // before stripping the apostrophe
    v = v.replaceAll(RegExp(r'[^\p{L}\p{N}\s]+', unicode: true), ' ');
    v = v.replaceAll(RegExp(r'\s+', unicode: true), ' ').trim();
    return _stripArticle(v);
  }

  static String _stripArticle(String v) => v.replaceFirst(RegExp(r'^(the|a|an)\s+'), '');

  static const Map<String, String> _contractions = {
    "i'd": 'i would', "i'll": 'i will', "i'm": 'i am', "i've": 'i have',
    "you're": 'you are', "you'd": 'you would', "you'll": 'you will', "you've": 'you have',
    "we're": 'we are', "we'd": 'we would', "we'll": 'we will', "we've": 'we have',
    "they're": 'they are', "they'd": 'they would', "they'll": 'they will', "they've": 'they have',
    "it's": 'it is', "that's": 'that is', "there's": 'there is', "let's": 'let us',
    "don't": 'do not', "doesn't": 'does not', "didn't": 'did not', "isn't": 'is not',
    "aren't": 'are not', "wasn't": 'was not', "weren't": 'were not', "can't": 'cannot',
    "won't": 'will not', "wouldn't": 'would not', "couldn't": 'could not', "shouldn't": 'should not',
    "haven't": 'have not', "hasn't": 'has not', "hadn't": 'had not',
  };

  static String _expandContractions(String value) {
    final v = value.replaceAll('’', "'").replaceAll('`', "'");
    return v.replaceAllMapped(
      RegExp(r"\b[a-z]+'[a-z]+\b"),
      (m) => _contractions[m[0]] ?? m[0]!,
    );
  }

  /// Byte-free Levenshtein (edit distance) over runes — exact for the latin target side.
  static int _levenshtein(String s, String t) {
    if (s == t) return 0;
    final a = s.runes.toList(), b = t.runes.toList();
    if (a.isEmpty) return b.length;
    if (b.isEmpty) return a.length;
    var prev = List<int>.generate(b.length + 1, (i) => i);
    var cur = List<int>.filled(b.length + 1, 0);
    for (var i = 0; i < a.length; i++) {
      cur[0] = i + 1;
      for (var j = 0; j < b.length; j++) {
        final cost = a[i] == b[j] ? 0 : 1;
        cur[j + 1] = _min3(cur[j] + 1, prev[j + 1] + 1, prev[j] + cost);
      }
      final tmp = prev;
      prev = cur;
      cur = tmp;
    }
    return prev[b.length];
  }

  static int _min3(int a, int b, int c) => a < b ? (a < c ? a : c) : (b < c ? b : c);
}

/// The three session "phases" the header labels (§2б / кадры 12a–12i). Derived from the exercise
/// mode as a stable proxy for the term's learning state (the session card carries the mode, not
/// the state): multiple_choice → intro, word_bank → assemble, everything else → review. In a
/// practice session the whole header reads «Свободная тренировка» instead (handled by the caller).
enum SessionPhase { intro, assemble, review }

SessionPhase phaseFor(ExerciseMode mode) => switch (mode) {
      ExerciseMode.multipleChoice => SessionPhase.intro,
      // scramble assembles too, but a sentence, and it is a LATER rung than word_bank — the header
      // should read as review, not as the recognition→production step.
      ExerciseMode.wordBank => SessionPhase.assemble,
      ExerciseMode.typing ||
      ExerciseMode.listening ||
      ExerciseMode.cloze ||
      ExerciseMode.scramble =>
        SessionPhase.review,
    };

/// Whole calendar days from [now] to [due], in LOCAL time (0 = today, 1 = tomorrow, …). Negative
/// (already overdue) is clamped to 0. Pure formatting of a REAL server `due_at`; the l10n layer
/// turns the number into «сегодня» / «завтра» / «через N дней».
int daysUntil(DateTime due, DateTime now) {
  final d = DateTime(due.year, due.month, due.day);
  final n = DateTime(now.year, now.month, now.day);
  final diff = d.difference(n).inDays;
  return diff < 0 ? 0 : diff;
}
