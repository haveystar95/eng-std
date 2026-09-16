/// WHICH WORDS OF A LINE WERE HEARD (work order SESSION-1c, section 4; canvas 35-3 «heard · the text opened») — the
/// revealed line of the echo marks every word the learner said in sage, word by word, the punctuation outside the
/// marks. And the reverse for the own bubble of the dialogue (35-2, 35-5): which heard words belong to the expected
/// line.
///
/// Words are compared in the canonical form of speech coverage ([SpeechCoverage.words]) and as a multiset: a word the
/// line says twice needs to be heard twice to be marked twice; a gluing of two adjacent words counts as both.
///
/// Pure functions, not a single widget.
library;

import 'speech_coverage.dart';

/// A word of the line, `[start, end)` in characters.
typedef WordRange = ({int start, int end});

abstract final class HeardWords {
  static final RegExp _word = RegExp(r"[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*", unicode: true);

  /// The words of [line] that are in [heard] — their character ranges, in the line's order.
  static List<WordRange> matched(String line, String heard) {
    final available = <String, int>{};
    for (final w in SpeechCoverage.heardWords(heard, line)) {
      available[w] = (available[w] ?? 0) + 1;
    }
    final out = <WordRange>[];
    for (final m in _word.allMatches(line)) {
      final tokens = SpeechCoverage.words(m[0]!);
      if (tokens.isEmpty) continue;
      final need = <String, int>{};
      for (final t in tokens) {
        need[t] = (need[t] ?? 0) + 1;
      }
      if (!need.entries.every((e) => (available[e.key] ?? 0) >= e.value)) continue;
      for (final e in need.entries) {
        available[e.key] = available[e.key]! - e.value;
      }
      out.add((start: m.start, end: m.end));
    }
    return out;
  }
}
