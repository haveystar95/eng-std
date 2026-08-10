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
  ]);

  final List<ExerciseMode> modes;

  ExerciseMode get first => modes.first;
}

/// Client port of the server's `ExerciseSelector::selectForPractice`.
///
/// Free practice is NOT the SRS ladder: it fans across every mode a term can be drilled in,
/// round-robin, so one session shows them all and a repeat re-deals. Only the hard type limits
/// filter — word_bank needs at least two words to assemble, cloze needs an example the answer can
/// be cut from; multiple_choice, typing and listening fit anything.
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
    required int answerWordCount,
    required bool clozeable,
  }) {
    final applicable = applicableModes(
      enabled: enabled,
      answerWordCount: answerWordCount,
      clozeable: clozeable,
    );
    // Only reachable if the enabled set is exotic — typing and multiple_choice always apply.
    if (applicable.isEmpty) return enabled.first;
    final n = applicable.length;
    return applicable[((rotation % n) + n) % n]; // guard a negative seed into range
  }

  /// The enabled modes this term can actually be drilled in, in config order.
  static List<ExerciseMode> applicableModes({
    required PracticeModes enabled,
    required int answerWordCount,
    required bool clozeable,
  }) {
    return [
      for (final mode in enabled.modes)
        if (switch (mode) {
          ExerciseMode.wordBank => answerWordCount >= 2, // nothing to assemble from one word
          ExerciseMode.cloze => clozeable, // needs an example holding the answer
          _ => true, // multiple_choice / typing / listening fit any term
        })
          mode,
    ];
  }

  /// Whitespace-separated words in the answer — what gates word_bank. Mirrors the server's
  /// `ChipShuffler::wordCount`.
  static int answerWordCount(String answer) =>
      answer.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).length;

  /// Can a blank be cut from this example? The example must exist and contain the answer,
  /// case-insensitively — the same test the server makes, and the same one the cloze card uses
  /// when it blanks the span.
  static bool clozeable(String answer, String? example) {
    if (example == null || example.isEmpty || answer.isEmpty) return false;
    return example.toLowerCase().contains(answer.toLowerCase());
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
