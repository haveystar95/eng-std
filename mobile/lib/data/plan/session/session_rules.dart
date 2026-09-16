/// SESSION PASS RULES — what the client is entitled to write and how it checks an answer (work order SESSION-1b).
///
/// The client grades, without the network: choice — the option id against `correct`, tiles — against `expected`,
/// voice — speech coverage by `coverage_min` ([SpeechCoverage]). Judged kinds are graded only by the server. The
/// consequences (the copy at the end of the stage, the unit's return) are handled by the server — they are not
/// here.
///
/// Pure functions, not a single widget.
library;

import 'package:flutter/foundation.dart';

import 'session_models.dart';
import 'speech_coverage.dart';

/// A piece of the assembled phrase string: a tile or a slot with a filler.
sealed class AssemblyPiece {
  const AssemblyPiece();
}

/// A tray tile — by its position in `tiles`: two identical tiles remain different tiles.
class TilePiece extends AssemblyPiece {
  const TilePiece(this.index, this.text);

  final int index;
  final String text;

  @override
  bool operator ==(Object other) => other is TilePiece && other.index == index && other.text == text;

  @override
  int get hashCode => Object.hash(index, text);
}

/// The frame's slot with the chosen filler.
class SlotPiece extends AssemblyPiece {
  const SlotPiece(this.filler);

  final CardFiller filler;

  @override
  bool operator ==(Object other) => other is SlotPiece && other.filler.index == filler.index;

  @override
  int get hashCode => filler.index.hashCode;
}

abstract final class SessionRules {
  /// Voice card: after this many attempts without a pass it is closed as `skipped`.
  static const int voiceAttempts = 2;

  /// WHAT THE SERVER ACCEPTS (`CardKind::allows`, `docs/plan-api.md` «What the client sends»); any other
  /// result — 422.
  static Set<SessionResult> serverAccepts(SessionKind kind) => switch (kind.grading) {
    SessionGrading.choice => const {SessionResult.passed, SessionResult.hinted, SessionResult.failed, SessionResult.skipped},
    SessionGrading.voice => const {SessionResult.passed, SessionResult.hinted, SessionResult.skipped},
    SessionGrading.judge => const {SessionResult.skipped},
    SessionGrading.pass => const {SessionResult.passed, SessionResult.skipped},
  };

  /// WHAT THIS CLIENT WRITES (work order SESSION-1b, section 1) — narrower than what the server accepts: choice
  /// and tiles — `passed` | `failed`; voice — `passed` | `skipped` and never `failed`; judged kind — only
  /// `skipped` («Skip»); walkthrough — `passed`.
  static Set<SessionResult> clientWrites(SessionKind kind) => switch (kind.grading) {
    SessionGrading.choice => const {SessionResult.passed, SessionResult.failed},
    SessionGrading.voice => const {SessionResult.passed, SessionResult.skipped},
    SessionGrading.judge => const {SessionResult.skipped},
    SessionGrading.pass => const {SessionResult.passed},
  };

  /// A check before sending: a result this client does not write is a program error, not an answer.
  static bool mayWrite(SessionKind kind, SessionResult result) => clientWrites(kind).contains(result);

  /// Choice: the option id = `correct`.
  static bool choiceCorrect(ChoicePayload payload, String optionId) => optionId == payload.correct;

  /// `word_assemble`: the assembled result = `expected` in order, word for word (one spelling with the tiles).
  static bool wordAssembled(List<String> placed, List<String> expected) => listEquals(placed, expected);

  /// The expected phrase string: the frame's words and the slot at position `slot_at`.
  static List<Object> _expectedSequence(PhraseAssemblePayload p) {
    final seq = <Object>[...p.expectedWords];
    final at = p.slotAt.clamp(0, seq.length);
    seq.insert(at, _Slot(p.fillerIndex));
    return seq;
  }

  static Object _asSequenceItem(AssemblyPiece piece) => switch (piece) {
    TilePiece(:final text) => text,
    SlotPiece(:final filler) => _Slot(filler.index),
  };

  /// `phrase_assemble`: the words = `expected.words` in order, the slot at position `slot_at`, the filler —
  /// `expected.filler_index`.
  static bool phraseAssembled(PhraseAssemblePayload payload, List<AssemblyPiece> pieces) =>
      listEquals(pieces.map(_asSequenceItem).toList(), _expectedSequence(payload));

  /// The first position where the assembled result diverged from the expected one (or where it falls short) —
  /// for the error outline.
  static int firstMismatch(List<Object> placed, List<Object> expected) {
    for (var i = 0; i < placed.length; i++) {
      if (i >= expected.length || placed[i] != expected[i]) return i;
    }
    return placed.length < expected.length ? placed.length : -1;
  }

  /// The position of the first error in a phrase assembled from tiles; -1 — there is no error.
  static int phraseMismatch(PhraseAssemblePayload payload, List<AssemblyPiece> pieces) =>
      firstMismatch(pieces.map(_asSequenceItem).toList(), _expectedSequence(payload));

  /// What should have stood at the error's position: a tile word or the slot's filler (null — an extra at the end).
  static ({String? word, int? fillerIndex})? phraseExpectedAt(PhraseAssemblePayload payload, int at) {
    final seq = _expectedSequence(payload);
    if (at < 0 || at >= seq.length) return null;
    final item = seq[at];
    return item is _Slot ? (word: null, fillerIndex: item.fillerIndex) : (word: item as String, fillerIndex: null);
  }

  /// `phrase_combine`: the frame = `correct_frame`; the filler — any of `chips`.
  static bool combineCorrect(PhraseCombinePayload payload, String frameRef) => frameRef == payload.correctFrame;

  /// The pass of a word or phrase voice card by what was heard.
  ///
  /// `phrase_other_slot` — the frame and the slot separately: coverage of `expected_text` by `coverage_min` AND
  /// all the words of `slot_expected` in what was heard.
  static bool voiceAccepted(CardPayload payload, String heard, Set<String> articles) => switch (payload) {
    WordRepeatPayload(:final expectedText, :final coverageMin) =>
      SpeechCoverage.covers(heard, expectedText, coverageMin, articles),
    PhraseRepeatPayload(:final expectedText, :final coverageMin) =>
      SpeechCoverage.covers(heard, expectedText, coverageMin, articles),
    PhraseOtherSlotPayload(:final expectedText, :final slotExpected, :final coverageMin) =>
      SpeechCoverage.covers(heard, expectedText, coverageMin, articles) &&
          SpeechCoverage.covers(heard, slotExpected, SpeechCoverage.all, articles),
    _ => false,
  };

  /// The separate pass of `phrase_other_slot`: the frame (coverage of the string) and the slot (all the words of
  /// the slot).
  static ({bool frame, bool slot}) otherSlotParts(PhraseOtherSlotPayload p, String heard, Set<String> articles) => (
    frame: SpeechCoverage.covers(heard, p.expectedText, p.coverageMin, articles),
    slot: SpeechCoverage.covers(heard, p.slotExpected, SpeechCoverage.all, articles),
  );

  /// What should be spoken — for the live line and the hint to the recognizer.
  static String expectedSpeech(CardPayload payload) => switch (payload) {
    WordRepeatPayload(:final expectedText) => expectedText,
    PhraseRepeatPayload(:final expectedText) => expectedText,
    PhraseOtherSlotPayload(:final expectedText) => expectedText,
    PhraseOwnSlotPayload(:final frame) => frame.parts.before + frame.parts.after,
    _ => '',
  };

  /// The first word of the string — capitalized when the string is assembled in full (tiles arrive lowercase).
  static String capitalized(String text) {
    if (text.isEmpty) return text;
    return text[0].toUpperCase() + text.substring(1);
  }
}

@immutable
class _Slot {
  const _Slot(this.fillerIndex);

  final int? fillerIndex;

  @override
  bool operator ==(Object other) => other is _Slot && other.fillerIndex == fillerIndex;

  @override
  int get hashCode => fillerIndex.hashCode;
}
