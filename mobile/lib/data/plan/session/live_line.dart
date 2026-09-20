/// MICROPHONE LIVE LINE (canvas 30-3): what the recognizer has heard so far, word by word — words matching the
/// expected text in sage, the last word, while recording is on, in grey, the rest in ink.
///
/// Pure function: words are compared in the canonical form of speech coverage ([SpeechMatch.words]) and as a
/// multiset — a word that occurs once in the expected text is painted sage once. A surface word of the
/// recognizer may yield several canonical ones («doesn't» → `does not`, «X-ray» → `x ray`): it counts as
/// matched only when all of them matched. Gluing of two adjacent expected words without a space
/// («workschedule») is shown as two words (polish pass SESSION-1b′, item 6).
library;

import 'speech_match.dart';

/// The color of a live line word.
enum LiveTone {
  /// Matched the expected text — sage.
  matched,

  /// The last word, while recording is on — grey: the recognizer is still refining it.
  pending,

  /// Did not match — ink.
  plain,
}

/// One word of the live line — as the recognizer wrote it.
typedef LiveWord = ({String text, LiveTone tone});

abstract final class LiveLine {
  /// The words of [heard] with a color. [listening] — recording is still on: the last word is grey.
  static List<LiveWord> of(String heard, String expected, {required bool listening}) {
    final surface = heard.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
    if (surface.isEmpty) return const [];
    final expectedWords = SpeechMatch.words(expected);
    final available = <String, int>{};
    for (final w in expectedWords) {
      available[w] = (available[w] ?? 0) + 1;
    }
    final out = <LiveWord>[];
    for (var i = 0; i < surface.length; i++) {
      final last = listening && i == surface.length - 1;
      final tokens = SpeechMatch.words(surface[i]);
      // Gluing of two adjacent expected words — two words of the line.
      final glued = tokens.length == 1 && !available.containsKey(tokens.single)
          ? SpeechMatch.unglue(tokens.single, expectedWords)
          : null;
      final pieces = glued == null ? [(text: surface[i], tokens: tokens)] : [for (final w in glued) (text: w, tokens: [w])];
      for (final piece in pieces) {
        if (last) {
          out.add((text: piece.text, tone: LiveTone.pending));
          continue;
        }
        final need = <String, int>{};
        for (final t in piece.tokens) {
          need[t] = (need[t] ?? 0) + 1;
        }
        final matched = piece.tokens.isNotEmpty && need.entries.every((e) => (available[e.key] ?? 0) >= e.value);
        if (matched) {
          for (final e in need.entries) {
            available[e.key] = available[e.key]! - e.value;
          }
        }
        out.add((text: piece.text, tone: matched ? LiveTone.matched : LiveTone.plain));
      }
    }
    return out;
  }
}
