/// SESSION PASS RULES — what the client is entitled to write and how it checks an answer (work orders SESSION-1b,
/// SESSION-1c).
///
/// The client grades, without the network: choice — the option id against `correct`, tiles — against `expected`,
/// voice — speech coverage by `coverage_min` ([SpeechCoverage]). Judged kinds are graded only by the server. The
/// consequences (the copy at the end of the stage, the unit's return) are handled by the server — they are not
/// here.
///
/// Pure functions, not a single widget.
library;

import 'package:flutter/foundation.dart';

import '../plan_models.dart';
import 'session_models.dart';
import 'speech_coverage.dart';
import 'voice_rounds.dart';

/// HOW A DIALOGUE VOICE CARD ASKS (`dialogue_answer` / `dialogue_ask`, work order SESSION-1c, section 2). The card
/// carries data for every mode (`modes`); the client picks one by the plan's level and «No hints» (30-1).
enum DialogueMode {
  /// Beginner: the frame with an empty slot in the own bubble, `modes.chips` under it — any chip is right.
  chips('chips'),

  /// Intermediate: the whole own line (`modes.voice_hint`) in the bubble, the answer by voice.
  voiceHint('voice_hint'),

  /// «No hints»: the frame with an empty slot, the answer by voice — the frame covered, any slot.
  voiceBlind('voice_blind');

  const DialogueMode(this.wire);

  /// `response.mode` of the answer.
  final String wire;
}

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

  /// WHAT THIS CLIENT WRITES (work orders SESSION-1b, section 1; SESSION-1c, section 1) — narrower than what the
  /// server accepts, kind by kind, so a new kind cannot slip in without a decision: choice and tiles — `passed` |
  /// `failed` (a chips answer of the dialogue is always right — `passed`); voice — `passed` | `skipped`, never
  /// `failed`; judged — only `skipped` («Skip»: the pass is the judge's); walkthrough — `passed`.
  static Set<SessionResult> clientWrites(SessionKind kind) => switch (kind) {
    SessionKind.wordChoose ||
    SessionKind.wordListen ||
    SessionKind.wordAssemble ||
    SessionKind.wordInLine ||
    SessionKind.phraseAssemble ||
    SessionKind.phraseChooseBack ||
    SessionKind.phraseSlot ||
    SessionKind.phraseSlotListen ||
    SessionKind.phraseCombine ||
    SessionKind.dialoguePartner ||
    // Listening questions: the first failure is final — the server deals no copy (`requeued: null`).
    SessionKind.listenQuestion ||
    SessionKind.listenPredict ||
    SessionKind.listenNumber => _choice,
    SessionKind.wordRepeat ||
    SessionKind.phraseRepeat ||
    SessionKind.phraseOtherSlot ||
    SessionKind.dialogueAnswer ||
    SessionKind.dialogueAsk ||
    SessionKind.speakEcho => _voice,
    SessionKind.phraseOwnSlot || SessionKind.speakAnswer || SessionKind.speakRetell => _judged,
    SessionKind.wordIntro ||
    SessionKind.phraseIntro ||
    SessionKind.dialogueRescue ||
    SessionKind.listenDialogue ||
    SessionKind.listenReview ||
    SessionKind.listenPace => _walkthrough,
  };

  static const Set<SessionResult> _choice = {SessionResult.passed, SessionResult.failed};
  static const Set<SessionResult> _voice = {SessionResult.passed, SessionResult.skipped};
  static const Set<SessionResult> _judged = {SessionResult.skipped};
  static const Set<SessionResult> _walkthrough = {SessionResult.passed};

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
    // The dialogue (SESSION-1c, section 2): the frame's own words covered by `coverage_min` — the slot is anyone's,
    // in either voice mode; the server measured `coverage_min` on the same part (`FrameParts::part`).
    DialogueAnswerPayload(:final frame, :final coverageMin) =>
      SpeechCoverage.covers(heard, framePart(frame.frameTarget), coverageMin, articles),
    SpeakEchoPayload(:final expectedText, :final coverageMin) =>
      SpeechCoverage.covers(heard, expectedText, coverageMin, articles),
    _ => false,
  };

  /// THE FRAME'S OWN WORDS — the frame outside its slot, without the closing mark: what must be heard for the frame
  /// to have been said, whatever went into the slot. A mirror of the server's `FrameParts::part()`:
  /// `I'd like a ___, please.` → `I'd like a, please`.
  static String framePart(String frameTarget) {
    var text = frameTarget.trim().replaceFirst(RegExp(r'[.!?…]+$'), '').trimRight();
    text = text.replaceAll(RegExp(r'_{3,}'), ' ');
    text = text.replaceAll(RegExp(r'\s+'), ' ');
    text = text.replaceAllMapped(RegExp(r'\s+([.,!?;:…])'), (m) => m[1]!);
    return text.trim();
  }

  /// The mode a dialogue voice card asks in (SESSION-1c, section 2): «No hints» — blind voice at any level; beginner
  /// — chips; intermediate — voice with the whole line as a hint. A frame without a slot has no chips to choose
  /// from, so a beginner answers it by voice with the line on screen.
  static DialogueMode dialogueMode(DialogueAnswerPayload payload, {required PlanLevel level, required bool noHints}) {
    if (noHints) return DialogueMode.voiceBlind;
    if (level == PlanLevel.beginner && payload.frame.hasSlot && payload.modes.chips.isNotEmpty) return DialogueMode.chips;
    return DialogueMode.voiceHint;
  }

  /// What a dialogue voice card expects to hear — the reference of the live line: the whole own line when it is on
  /// screen, the frame's own words when the slot is blind.
  static String dialogueExpected(DialogueAnswerPayload payload, DialogueMode mode) => switch (mode) {
    DialogueMode.voiceHint => payload.modes.voiceHint,
    DialogueMode.chips || DialogueMode.voiceBlind => framePart(payload.frame.frameTarget),
  };

  /// THE RECOGNIZER'S LANGUAGE BY KIND (SESSION-1c, section 1): the target language everywhere, except the retelling
  /// (`speak_retell`), which is said in the native language.
  static String speechLang(SessionKind kind, {required String targetLang, required String nativeLang}) =>
      kind == SessionKind.speakRetell ? nativeLang : targetLang;

  /// The separate pass of `phrase_other_slot`: the frame (coverage of the phrase) and the slot (all the words of the
  /// meaning in it). [expectedText] and [slotExpected] — the chip the learner chose (32-7, SESSION-2b §1); by
  /// default the filler the card came with.
  static ({bool frame, bool slot}) otherSlotParts(
    PhraseOtherSlotPayload p,
    String heard,
    Set<String> articles, {
    String? expectedText,
    String? slotExpected,
  }) => (
    frame: SpeechCoverage.covers(heard, expectedText ?? p.expectedText, p.coverageMin, articles),
    slot: SpeechCoverage.covers(heard, slotExpected ?? p.slotExpected, SpeechCoverage.all, articles),
  );

  /// A round of `phrase_repeat` (polish pass SESSION-1b′, item 12): the round's phrase covered by the card's
  /// `coverage_min`. Round 1 is the card's own pass ([voiceAccepted]).
  static bool roundAccepted(PhraseRepeatPayload payload, VoiceRound round, String heard, Set<String> articles) =>
      SpeechCoverage.covers(heard, round.expectedText, payload.coverageMin, articles);

  /// What should be spoken — for the live line and the hint to the recognizer.
  static String expectedSpeech(CardPayload payload) => switch (payload) {
    WordRepeatPayload(:final expectedText) => expectedText,
    PhraseRepeatPayload(:final expectedText) => expectedText,
    PhraseOtherSlotPayload(:final expectedText) => expectedText,
    PhraseOwnSlotPayload(:final frame) => frame.parts.before + frame.parts.after,
    DialogueAnswerPayload(:final ownLine) => ownLine.textTarget,
    SpeakEchoPayload(:final expectedText) => expectedText,
    SpeakAnswerPayload(:final frame) => framePart(frame.frameTarget),
    // The retelling is in the native language and judged by meaning — there is nothing to match word by word.
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
