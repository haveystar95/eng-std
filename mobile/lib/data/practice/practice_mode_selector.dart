import '../models.dart';

/// The exercise modes free practice may deal, in the order the rotation walks them.
///
/// This mirrors the server's `config/learning.php` → `enabled_modes`, and the ORDER is part of the
/// contract: the round-robin indexes into this list, so reordering it re-deals every card. It is a
/// value, not a constant, so the ladder can come from a policy later (v3) without touching the
/// selector — the default is exactly today's server config.
class PracticeModes {
  // No assert on emptiness: `modes.length` is not const-evaluable, and [PracticeModeSelector]
  // falls back to `first` only when a mode set is non-empty anyway. An empty set is a programming
  // error that surfaces immediately on the first card.
  const PracticeModes(this.modes);

  /// Today's server default (`config/learning.php`).
  static const PracticeModes serverDefault = PracticeModes([
    ExerciseMode.multipleChoice,
    ExerciseMode.wordBank,
    ExerciseMode.typing,
    ExerciseMode.listening,
    ExerciseMode.cloze,
    ExerciseMode.scramble,
  ]);

  final List<ExerciseMode> modes;

  ExerciseMode get first => modes.first;
}

/// Client port of the server's `ExerciseSelector::selectForPractice`.
///
/// Free practice is NOT the SRS ladder: it fans across every mode a term can be drilled in,
/// round-robin, so one session shows them all and a repeat re-deals. The only filter is what the
/// term's own data supports — see [TermPlayability], which holds every such rule.
///
/// Pinned to the server by a fixture the server generates
/// (`backend2/tests/Fixtures/practice-mode-contract.json`), because a divergence here is silent:
/// the card would simply be the wrong exercise, and nothing would fail.
abstract final class PracticeModeSelector {
  /// The rotation seed for a card, exactly as `StudyCardAssembler` builds it: the card's index
  /// plus a stable, well-spread per-term offset, so a given index doesn't hand the same mode to
  /// every card in the session.
  static int rotationFor(String termId, int cardIndex) => cardIndex + crc32(termId);

  /// The mode for one practice card.
  static ExerciseMode select({
    required PracticeModes enabled,
    required int rotation,
    required TermPlayability playable,
  }) {
    final applicable = playable.only(enabled.modes);
    // Only reachable if the enabled set is exotic — typing and multiple_choice always apply.
    if (applicable.isEmpty) return enabled.first;
    final n = applicable.length;
    return applicable[((rotation % n) + n) % n]; // guard a negative seed into range
  }
}

/// Client port of the server's `TermPlayability` — "which exercises can this term's DATA support".
///
/// Same split as on the server, for the same reason: the ladder decides what comes next, this
/// decides what is possible at all, and keeping them apart is what lets a ladder stay a plain list.
/// Every applicability rule lives in [supports] and nowhere else.
class TermPlayability {
  const TermPlayability({
    required this.answerWordCount,
    required this.clozeable,
    this.exampleTokenCount = 0,
    this.hasExampleTranslation = false,
    this.exampleIsAnswer = false,
  });

  /// Derive it from a term's own content, exactly as the server's `PlayabilityAssessor` does.
  factory TermPlayability.of({
    required String answer,
    String? example,
    String? exampleTranslation,
  }) {
    final hasExample = example != null && example.isNotEmpty;
    return TermPlayability(
      answerWordCount: wordsIn(answer),
      clozeable: hasExample && answer.isNotEmpty && example.toLowerCase().contains(answer.toLowerCase()),
      exampleTokenCount: hasExample ? SentenceTokenizer.tokenize(example).length : 0,
      hasExampleTranslation: exampleTranslation != null && exampleTranslation.trim().isNotEmpty,
      exampleIsAnswer: hasExample && SentenceTokenizer.sameTokens(example, answer),
    );
  }

  /// Nothing to assemble from a single word.
  static const int minWordBankWords = 2;

  /// Scramble's sentence-length window (see the server VO for why these numbers).
  static const int minScrambleTokens = 4;
  static const int maxScrambleTokens = 12;

  final int answerWordCount;
  final bool clozeable;
  final int exampleTokenCount;
  final bool hasExampleTranslation;
  final bool exampleIsAnswer;

  /// Can this term be drilled in this mode at all?
  bool supports(ExerciseMode mode) => switch (mode) {
        ExerciseMode.wordBank => answerWordCount >= minWordBankWords,
        ExerciseMode.cloze => clozeable, // needs an example holding the answer
        ExerciseMode.scramble => !exampleIsAnswer && // else it is word_bank with extra steps
            hasExampleTranslation && // the translation IS the question
            exampleTokenCount >= minScrambleTokens &&
            exampleTokenCount <= maxScrambleTokens,
        // multiple_choice / typing / listening fit any term — they ask for the term itself.
        ExerciseMode.multipleChoice || ExerciseMode.typing || ExerciseMode.listening => true,
      };

  /// The given modes this term can be drilled in, order preserved.
  List<ExerciseMode> only(List<ExerciseMode> modes) => [
        for (final mode in modes)
          if (supports(mode)) mode,
      ];

  /// Whitespace-separated words in the answer — what gates word_bank. Mirrors the server's
  /// `ChipShuffler::wordCount`.
  static int wordsIn(String answer) =>
      answer.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).length;
}

/// Client port of the server's `SentenceTokenizer` — the chips a `scramble` card is assembled from,
/// and therefore how long a sentence "is" for the gate.
///
/// The rules (see the server class for the corpus numbers behind each): split on whitespace only;
/// an in-word apostrophe stays inside its token; the FINAL `.`/`!`/`?` is dropped and never becomes
/// a chip; inner punctuation stays glued to its word; case is not folded.
abstract final class SentenceTokenizer {
  static final RegExp _whitespace = RegExp(r'\s+');
  static final RegExp _terminal = RegExp(r'[.!?…]+$');

  static List<String> tokenize(String sentence) {
    final tokens = sentence.trim().split(_whitespace).where((t) => t.isNotEmpty).toList();
    if (tokens.isEmpty) return const [];

    final last = tokens.last.replaceFirst(_terminal, '');
    if (last.isEmpty) {
      tokens.removeLast(); // the sentence ended on a standalone "!" — drop the empty chip
      return tokens;
    }
    tokens[tokens.length - 1] = last;
    return tokens;
  }

  /// Do two strings tokenize to the same words (ignoring case)? A term whose example IS the term
  /// would make scramble a copy of word_bank.
  static bool sameTokens(String a, String b) {
    final ta = tokenize(a).map((t) => t.toLowerCase()).toList();
    final tb = tokenize(b).map((t) => t.toLowerCase()).toList();
    if (ta.length != tb.length) return false;
    for (var i = 0; i < ta.length; i++) {
      if (ta[i] != tb[i]) return false;
    }
    return true;
  }
}

/// CRC-32 (IEEE 802.3, reflected) — the same value PHP's `crc32()` returns, which is what the
/// server uses as the per-term rotation offset. Implemented here because Dart has no built-in and
/// the seed has to match exactly; the contract fixture is what proves it does.
int crc32(String input) {
  var crc = 0xFFFFFFFF; // hex-ok: CRC seed, not a colour
  for (final byte in _utf8Bytes(input)) {
    crc ^= byte;
    for (var bit = 0; bit < 8; bit++) {
      crc = (crc & 1) != 0 ? (crc >> 1) ^ 0xEDB88320 : crc >> 1; // hex-ok: CRC polynomial
    }
  }
  return (crc ^ 0xFFFFFFFF) & 0xFFFFFFFF; // hex-ok: final xor + unsigned mask, as PHP returns it
}

/// ULIDs and answers are ASCII in practice, but encode properly rather than assume it — a
/// non-ASCII term id would otherwise silently seed differently from the server.
List<int> _utf8Bytes(String input) {
  final bytes = <int>[];
  for (final rune in input.runes) {
    if (rune < 0x80) {
      bytes.add(rune);
    } else if (rune < 0x800) {
      bytes..add(0xC0 | (rune >> 6))..add(0x80 | (rune & 0x3F));
    } else if (rune < 0x10000) {
      bytes
        ..add(0xE0 | (rune >> 12))
        ..add(0x80 | ((rune >> 6) & 0x3F))
        ..add(0x80 | (rune & 0x3F));
    } else {
      bytes
        ..add(0xF0 | (rune >> 18))
        ..add(0x80 | ((rune >> 12) & 0x3F))
        ..add(0x80 | ((rune >> 6) & 0x3F))
        ..add(0x80 | (rune & 0x3F));
    }
  }
  return bytes;
}
