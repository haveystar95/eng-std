/// WHICH WORDS OF A LINE WERE HEARD (work order SESSION-1c, section 4; canvas 35-3 «heard · the text opened») — the
/// revealed line of the echo marks every word the learner said in sage, word by word, the punctuation outside the
/// marks. And the reverse for the own bubble of the dialogue (35-2, 35-5): which heard words belong to the expected
/// line.
///
/// Words are compared in the canonical form of speech coverage ([SpeechMatch.words]) and as a multiset: a word the
/// line says twice needs to be heard twice to be marked twice; a gluing of two adjacent words counts as both.
///
/// Pure functions, not a single widget.
library;

import 'speech_match.dart';

/// A word of the line, `[start, end)` in characters.
typedef WordRange = ({int start, int end});

abstract final class HeardWords {
  static final RegExp _word = RegExp(r"[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*", unicode: true);

  /// The words of [line] that are in [heard] — their character ranges, in the line's order.
  static List<WordRange> matched(String line, String heard) {
    final available = <String, int>{};
    for (final w in SpeechMatch.heardWords(heard, line)) {
      available[w] = (available[w] ?? 0) + 1;
    }
    final out = <WordRange>[];
    for (final m in _word.allMatches(line)) {
      final tokens = SpeechMatch.words(m[0]!);
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

  /// EVERY WORD OF [line] THAT THE CONSTRUCTION SAYS (приёмка окна 2 FIX-3, п. 1) — the words of its immovable part and
  /// of the value the learner put in its window, both the server's. A SET, not a budget: a word the construction says
  /// once is underlined wherever the line says it, so «Can I take my laptop bag onboard?» underlines the «I» of «can I
  /// take» too, and not only the first «I» of the line.
  ///
  /// Compared in the canonical form of speech coverage ([SpeechMatch.words]) — letter case aside, contractions spelt
  /// out, and with [rules] the day's own foldings (abbreviations, numbers in words).
  static List<WordRange> wordsIn(String line, Iterable<String?> parts, [SpeechRules rules = SpeechRules.none]) {
    final vocabulary = <String>{
      for (final part in parts)
        if (part != null) ...SpeechMatch.words(part, rules),
    };
    if (vocabulary.isEmpty) return const [];

    return [
      for (final m in _word.allMatches(line))
        if (SpeechMatch.words(m[0]!, rules) case final tokens
            when tokens.isNotEmpty && tokens.every(vocabulary.contains))
          (start: m.start, end: m.end),
    ];
  }
}
