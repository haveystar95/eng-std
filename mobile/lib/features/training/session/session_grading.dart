import '../../../data/models.dart';

/// The client's INSTANT read of an answer — for feedback only. The server is the sole grader
/// (invariant): it re-grades every raw answer and schedules from that. This check exists so the
/// card can react the moment the user answers, and it is deliberately **never stricter than the
/// server** — it mirrors the server's [AnswerGrader] normalisation (case, spacing, punctuation,
/// contractions, optional leading article) and its single-character-typo tolerance on long-enough
/// answers, so anything the server would accept, the client also shows as accepted.
///
/// It now grades against the same SET the server does — the card's answer plus its
/// `accepted_variants` — because the device grades offline and a client that didn't know the
/// variants would show «Не то» for an answer the scheduler then counts as correct. Pure and
/// unit-tested.
enum LocalCheck {
  /// Exact after normalisation — «Верно».
  correct,

  /// One-character typo on a long-enough answer — accepted, shown as «Почти».
  typo,

  /// Not a match — «Не то».
  wrong;

  bool get isAccepted => this != wrong;
}

/// The rules that apply to an answer that arrived through a SPEECH RECOGNISER rather than through
/// the keyboard or a tap.
abstract final class SpokenAnswer {
  /// The share of a read-aloud sentence's words that must be heard for it to count. Mirror of the
  /// server's `SpokenCoverage::MIN_COVERAGE` — moving it moves both, or the phone shows a verdict
  /// the server then contradicts.
  static const double minCoverage = 0.7;

  /// How many times a card lets the recogniser fail before it offers «Пропустить».
  ///
  /// This is the CHANNEL budget, and it is the whole reason speaking can be forgiving without being
  /// dishonest: three chances for the microphone, and then the card is set aside with nothing
  /// written anywhere. It is not a budget for wrong answers — a recognised answer that is simply
  /// wrong is a verdict on the first try, like every other trainer.
  static const int maxChannelAttempts = 3;
}

/// Mirror of the server `AnswerGrader`'s correctness stages (grading of the SPEED/hint into
/// again/hard/good/easy stays server-side — the client never needs it).
abstract final class SessionGrader {
  /// Typo leniency only kicks in for longer answers, else "cat"/"cut" pass as correct.
  static const int _minTypoLength = 5;

  /// Check a raw [response] against the card's canonical [answer] plus any [variants] that are
  /// equally correct. Uniform across modes: a multiple-choice pick, an assembled word_bank string
  /// and typed text (typing / cloze / dictation) all run this one path — matching the server, which
  /// also grades every mode through one grader.
  ///
  /// Staged exactly like the server's [AnswerGrader]: an exact match on ANY accepted form first,
  /// then (speaking only) a suffix-tolerant match, then a one-character typo against any of them.
  /// Doing it in that order matters — checking typos per-candidate before exhausting the exact
  /// matches could report «Почти» for something that is simply a correct variant.
  /// [forgiveTypos] mirrors the server's `ExerciseMode::forgivesTypos()`. Pass false whenever the
  /// answer was TAPPED or ASSEMBLED rather than typed: there is no slipped key to forgive, and on
  /// pick_correct a one-character difference is usually the exact thing the card tests ("Could you
  /// takes a photo…" against "Could you take a photo…"). Forgiving it there ticks the broken sentence
  /// as correct — which is what the device caught.
  /// [spokenSuffixTolerance] mirrors the server's speaking-only leniency for a dropped trailing
  /// -s/-es/-'s (QA-20: "salary expectation" heard for "salary expectations" is the recogniser
  /// eating a sound, not the learner not knowing the word). Pass true ONLY for a speaking word-form
  /// answer — every other mode keeps its existing [forgiveTypos] leniency (capped at «Почти», never
  /// «Верно») and must not additionally accept this at full grade.
  static LocalCheck check(
    String response,
    String answer, {
    List<String> variants = const [],
    bool forgiveTypos = true,
    bool spokenSuffixTolerance = false,
  }) {
    final r = _normalize(response);
    if (r.isEmpty) return LocalCheck.wrong; // «Не помню» / blank

    final accepted = [answer, ...variants].map(_normalize).where((a) => a.isNotEmpty);
    for (final a in accepted) {
      if (r == a) return LocalCheck.correct;
    }
    if (spokenSuffixTolerance) {
      for (final a in accepted) {
        if (_suffixTolerantEqual(r, a)) return LocalCheck.correct;
      }
    }
    if (!forgiveTypos) return LocalCheck.wrong;
    for (final a in accepted) {
      if (_isTypo(r, a)) return LocalCheck.typo;
    }
    return LocalCheck.wrong;
  }

  /// The share of [expected]'s words that appear in [response], 0…1. Mirror of the server's
  /// `SpokenCoverage`.
  ///
  /// Why a sentence read aloud is not compared for equality: an on-device recogniser transcribes an
  /// ordinary reading of «Could you take a photo of us?» as «could you take photo of us» or «…of as»
  /// — it eats unstressed function words and guesses between homophones. Every one of those is a
  /// learner who did what the card asked, and holding them to equality would show «Не то» for a
  /// correct reading while the server (which does NOT hold them to it) счёл бы ответ верным — the
  /// one direction the client check is forbidden to take.
  ///
  /// Order-free and counted by MULTISET, like the server: recognisers drop and substitute words,
  /// they do not transpose them, and a sentence that says «very» twice needs it twice.
  static double coverageOf(String response, String expected) {
    final wanted = _words(expected);
    if (wanted.isEmpty) return 0; // nothing expected is never a vacuous pass

    final available = <String, int>{};
    for (final word in _words(response)) {
      available[word] = (available[word] ?? 0) + 1;
    }

    var found = 0;
    for (final word in wanted) {
      if (_consume(available, word)) found++;
    }
    return found / wanted.length;
  }

  /// Marks one occurrence of [word] as used in [available] and returns true — exact first, then a
  /// suffix-tolerant match (QA-20: a recogniser drops a trailing sibilant far more than it invents
  /// or swaps a whole word). [available] is one sentence's worth of words, so a linear scan for the
  /// tolerant match costs nothing that matters here.
  static bool _consume(Map<String, int> available, String word) {
    if ((available[word] ?? 0) > 0) {
      available[word] = available[word]! - 1;
      return true;
    }
    for (final candidate in available.keys.toList()) {
      if ((available[candidate] ?? 0) > 0 && _suffixTolerantEqual(word, candidate)) {
        available[candidate] = available[candidate]! - 1;
        return true;
      }
    }
    return false;
  }

  /// Was enough of [expected] said? [SpokenAnswer.minCoverage] is the shared threshold.
  static bool covers(String response, String expected) =>
      coverageOf(response, expected) >= SpokenAnswer.minCoverage;

  /// The comparable words of a string — canonicalised WITHOUT dropping the leading article, which
  /// here is just another word the recogniser may or may not have caught.
  static List<String> _words(String value) {
    var v = value.toLowerCase().trim();
    v = _expandContractions(v);
    v = v.replaceAll(RegExp(r'[^\p{L}\p{N}\s]+', unicode: true), ' ');
    v = v.replaceAll(RegExp(r'\s+', unicode: true), ' ').trim();
    return v.isEmpty ? const [] : v.split(' ');
  }

  /// Which of the learner's OWN words do not belong, as indices into [given].
  ///
  /// This is what a wrong verdict has to be able to point at. Showing the CORRECT form in place of
  /// what was typed — which cloze used to do — erases the one thing the learner needs to compare
  /// against: their own answer (QA-16). Marking it is the same move pick_correct already makes with
  /// an `error_span`, and the same wavy terracotta says it.
  ///
  /// The rule is a longest-common-subsequence over normalised words: everything that survives the
  /// LCS is a word that IS in the expected answer, in the right order, so what is left over is
  /// exactly «this word is wrong or does not belong here». Order matters, unlike [coverageOf] —
  /// this is typing and assembly, where the learner chose the order, not a recogniser transcript.
  ///
  /// Returns an empty set when the answer matches, and when nothing can be compared: a mark on
  /// every word says nothing more than the verdict already did.
  static Set<int> misplacedWords(List<String> given, String expected) {
    final wanted = _words(expected);
    final mine = given.map((w) => _words(w).join(' ')).toList();
    if (wanted.isEmpty || mine.every((w) => w.isEmpty)) return const {};

    // LCS by table, over word lists that are a sentence long at most.
    final n = mine.length, m = wanted.length;
    final table = List.generate(n + 1, (_) => List<int>.filled(m + 1, 0));
    for (var i = n - 1; i >= 0; i--) {
      for (var j = m - 1; j >= 0; j--) {
        table[i][j] = mine[i] == wanted[j]
            ? table[i + 1][j + 1] + 1
            : (table[i + 1][j] > table[i][j + 1] ? table[i + 1][j] : table[i][j + 1]);
      }
    }

    final wrong = <int>{};
    var i = 0, j = 0;
    while (i < n) {
      if (j < m && mine[i] == wanted[j]) {
        i++;
        j++;
      } else if (j < m && table[i + 1][j] >= table[i][j + 1]) {
        wrong.add(i);
        i++;
      } else if (j < m) {
        j++;
      } else {
        wrong.add(i); // trailing extras: nothing left to match them against
        i++;
      }
    }

    return wrong;
  }

  static bool _isTypo(String response, String candidate) {
    if (candidate.length < _minTypoLength) return false;
    return response != candidate && _levenshtein(response, candidate) == 1;
  }

  /// The three ASR-channel tails this trainer forgives (QA-20), mirror of the server's
  /// `SpokenSuffixTolerance`. `'s` is spelled ` s` (a separate trailing word) because by the time
  /// either side reaches this check it has already been through [_normalize]/[_words], which turn
  /// the apostrophe itself into a space.
  static const List<String> _suffixTails = ['s', 'es', ' s'];

  /// Same word (or phrase), once exactly one of the tolerated ASR tails is allowed for, in EITHER
  /// direction. Deliberately narrow: "expect" vs "expectations" differs by "ations", not by one of
  /// these, and stays a miss.
  static bool _suffixTolerantEqual(String a, String b) {
    if (a == b) return true;
    for (final tail in _suffixTails) {
      if ('$a$tail' == b || '$b$tail' == a) return true;
    }
    return false;
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
      // The acquisition ladder's rung 0 — literally the introduction the header has always named.
      ExerciseMode.intro => SessionPhase.intro,
      ExerciseMode.multipleChoice => SessionPhase.intro,
      // scramble assembles too, but a sentence, and it is a LATER rung than word_bank — the header
      // should read as review, not as the recognition→production step.
      ExerciseMode.wordBank => SessionPhase.assemble,
      // pick_correct is a LATER rung than word_bank (it reads whole sentences), so the header reads
      // as review rather than as the recognition→production step.
      // speaking opens on the assembly rung and only gets harder from there, so it reads as review
      // — never as the recognition→production step the header calls «assemble».
      ExerciseMode.speaking ||
      ExerciseMode.pickCorrect ||
      ExerciseMode.typing ||
      ExerciseMode.listening ||
      ExerciseMode.cloze ||
      ExerciseMode.scramble ||
      ExerciseMode.dictation =>
        SessionPhase.review,
    };

/// How many WORDS this session introduced — unique terms that were dealt an intro card.
///
/// The summary's «НОВЫХ» is about words, not cards. Counting cards read 12 for the six words the
/// device actually met, because a freshly introduced word gets an intro card AND its recognition
/// cards in the same run, and every one of them sits in the intro PHASE. Unique term ids on the
/// `intro` mode alone is the only reading that answers the question the label asks.
int newWordCount(Iterable<SessionCard> cards) => cards
    .where((c) => c.mode == ExerciseMode.intro)
    .map((c) => c.termId)
    .toSet()
    .length;

/// Whole calendar days from [now] to [due], in LOCAL time (0 = today, 1 = tomorrow, …). Negative
/// (already overdue) is clamped to 0. Pure formatting of a REAL server `due_at`; the l10n layer
/// turns the number into «сегодня» / «завтра» / «через N дней».
int daysUntil(DateTime due, DateTime now) {
  final d = DateTime(due.year, due.month, due.day);
  final n = DateTime(now.year, now.month, now.day);
  final diff = d.difference(n).inDays;
  return diff < 0 ? 0 : diff;
}
