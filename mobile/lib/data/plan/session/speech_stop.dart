/// WHEN TO STOP RECORDING (polish pass SESSION-1b′ on the phone, item 6) — without waiting for silence.
///
/// Voice pass: as soon as the recognizer's partial result already passes the kind's rule (coverage of the
/// expected text — all words for a string of up to two words, 70 % for a longer one, and for `phrase_other_slot`
/// also all the words of the slot) and has not changed for [covered], recording stops and a pass is recorded. No
/// coverage — recording is closed by 2 s of silence, as before. Judged kind (`phrase_own_slot`): the frame is
/// covered and at least one word beyond it is heard — pause [judged], stop, and a question to the judge.
///
/// Pure functions: the time «how long to wait for an unchanged partial result», or null — wait for silence.
library;

import 'speech_coverage.dart';

abstract final class SpeechStop {
  /// Passed — stop if the partial result has not changed for this long.
  static const Duration covered = Duration(milliseconds: 500);

  /// Judged kind — the frame and at least one word beyond it — stop after a pause this long.
  static const Duration judged = Duration(milliseconds: 800);

  /// Voice card: [accepts] is the kind's pass rule.
  static Duration? voice(String heard, bool Function(String heard) accepts) =>
      heard.trim().isNotEmpty && accepts(heard) ? covered : null;

  /// Judged card: the frame without the slot [framePart] is covered to [min] and ≥ 1 word beyond it is heard.
  static Duration? judge(String heard, String framePart, double min, Set<String> articles) {
    if (heard.trim().isEmpty || !SpeechCoverage.covers(heard, framePart, min, articles)) return null;
    return SpeechCoverage.extraWords(heard, framePart, articles) >= 1 ? judged : null;
  }
}
