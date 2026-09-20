/// SESSION PASS RULES — what the client is entitled to write and how it checks an answer (work orders SESSION-1b,
/// SESSION-1c).
///
/// The client grades, without the network: choice — the option id against `correct`, tiles — against `expected`,
/// voice — by the card's own `speech_mode` ([SpeechMatch], work order FIX-2 item 2). `speak_answer` is graded only
/// by the server. The consequences (the copy at the end of the stage, the unit's return) are handled by the server —
/// they are not here.
///
/// Pure functions, not a single widget.
library;

import 'package:flutter/foundation.dart';

import '../plan_models.dart';
import 'session_models.dart';
import 'speech_match.dart';

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
    SessionKind.speakEcho ||
    SessionKind.speakRetell => _voice,
    SessionKind.speakAnswer => _judged,
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

  /// The pass of a word or phrase voice card by what was heard — the card's own `speech_mode`, the day's own
  /// [SpeechRules]. «Скажи целиком» is not here: it goes in rounds, and a round is passed by [roundAccepted].
  static bool voiceAccepted(CardPayload payload, String heard, SpeechRules rules) => switch (payload) {
    WordRepeatPayload(:final expectedText, :final speechMode) =>
      SpeechMatch.said(heard, expectedText, speechMode, rules),
    PhraseRepeatPayload(:final expectedText, :final speechMode) =>
      SpeechMatch.said(heard, expectedText, speechMode, rules),
    // The dialogue (SESSION-1c, section 2): the learner says their own line, so the key is the frame's own words —
    // the slot is anyone's, in either voice mode (`speech_mode: free`, measured on the same `FrameParts::part`).
    DialogueAnswerPayload(:final frame, :final speechMode) =>
      SpeechMatch.said(heard, framePart(frame.frameTarget), speechMode, rules),
    SpeakEchoPayload(:final expectedText, :final speechMode) =>
      SpeechMatch.said(heard, expectedText, speechMode, rules),
    // «Say your line again» (35-4, BACK-TAILS-1 §1.1): the learner's own line, said as it stands — and no judge.
    SpeakRetellPayload(:final expectedText, :final speechMode) =>
      SpeechMatch.said(heard, expectedText, speechMode, rules),
    _ => false,
  };

  /// THE PHONE'S VERDICT WHERE THERE IS NO JUDGE TO ASK — «Once more» from the day summary (FIX-1 §5). The server
  /// refuses a card of a walked day (`plan_card_answered`), so a replay grades what the judge would have graded the
  /// way every voice card is graded: the KEY — the frame's own words, in the `free` mode. What went into the window
  /// is anyone's; that is what the judge was for, and a replay does not pretend to have one.
  ///
  /// Both kinds that ask the judge land here, and both ask it about the SAME thing: `speak_answer` about the whole
  /// card, «Скажи целиком» about its own-word round (its value rounds never reach this — they are graded on the
  /// phone anyway). So both are read off the frame, never off a round's expected phrase: a learner saying their own
  /// word would never match the phrase the round was dealt with.
  ///
  /// Before this, a replay accepted ANY speech at all — the first sound heard closed the card as a pass.
  static bool replayAccepted(CardPayload payload, String heard, SpeechRules rules) => switch (payload) {
    SpeakAnswerPayload(:final frame, :final speechMode) =>
      SpeechMatch.said(heard, framePart(frame.frameTarget), speechMode, rules),
    PhraseOtherSlotPayload(:final frame, :final ownRound) =>
      SpeechMatch.said(heard, framePart(frame.frameTarget), ownRound?.speechMode ?? SpeechMode.free, rules),
    _ => voiceAccepted(payload, heard, rules),
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

  /// A VALUE ROUND of «Скажи целиком» (work order FIX-2, item 5): the whole phrase with that value, in the card's
  /// own mode. The rounds are the SERVER'S — the phone no longer works out which value comes next.
  static bool roundAccepted(PhraseOtherSlotPayload payload, CardSayWholeRound round, String heard, SpeechRules rules) =>
      SpeechMatch.said(heard, round.expectedText, payload.speechMode, rules);

  /// What should be spoken — for the live line and the hint to the recognizer.
  static String expectedSpeech(CardPayload payload) => switch (payload) {
    WordRepeatPayload(:final expectedText) => expectedText,
    PhraseRepeatPayload(:final expectedText) => expectedText,
    PhraseOtherSlotPayload(:final rounds) when rounds.isNotEmpty => rounds.first.expectedText,
    PhraseOtherSlotPayload(:final frame) => frame.parts.before + frame.parts.after,
    DialogueAnswerPayload(:final ownLine) => ownLine.textTarget,
    SpeakEchoPayload(:final expectedText) => expectedText,
    SpeakRetellPayload(:final expectedText) => expectedText,
    SpeakAnswerPayload(:final frame) => framePart(frame.frameTarget),
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
