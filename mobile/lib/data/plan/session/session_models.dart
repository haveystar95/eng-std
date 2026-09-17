/// DAY SESSION CARDS — the registry of 28 trainers, as the server sends it (work orders SESSION-1b, SESSION-1c).
///
/// The contract is `backend2/docs/plan-api.md`, «Session cards», and the `PlanCard` schema of the `Plans` tag in
/// `openapi/openapi.yaml`; the client's input is the fixtures `backend2/docs/fixtures/day-doctor*.json`. Here
/// there is only reading: the card envelope, the shared objects (word, frame, filler, line, sound, photo) and one
/// model per dealt kind — all 28, and since SESSION-1c every one of them has a screen.
///
/// Parsing FAILS CLOSED where the card cannot be shown otherwise: for a known kind without a required field
/// [SessionContractError] is thrown, and the session skips that card. A kind this build does not know (and the
/// reserved `listen_pairs`, which has no payload) is not an error: [SessionCard.fromJson] returns null, and the
/// card is skipped without a request to the server.
library;

import '../plan_models.dart';

/// The server sent a known kind with a payload that this build has no way to show.
class SessionContractError extends FormatException {
  const SessionContractError(String what) : super('session card: $what');
}

/// How a kind is graded — and therefore what the client is allowed to write (`CardKind::allows` on the server).
enum SessionGrading {
  /// Choice and tiles: the client checks against `correct` / `expected`.
  choice,

  /// Voice: speech coverage by `coverage_min`, two attempts without a pass — `skipped`.
  voice,

  /// By meaning — only the server, `POST …/judge`.
  judge,

  /// Walkthrough: «Got it» / «Next».
  pass,
}

/// CARD KIND — 29 enum values on the server, 28 are dealt; `listen_pairs` is never dealt and is not here.
enum SessionKind {
  wordIntro('word_intro', PlanStage.words, SessionGrading.pass),
  wordRepeat('word_repeat', PlanStage.words, SessionGrading.voice),
  wordChoose('word_choose', PlanStage.words, SessionGrading.choice),
  wordListen('word_listen', PlanStage.words, SessionGrading.choice),
  wordAssemble('word_assemble', PlanStage.words, SessionGrading.choice),
  wordInLine('word_in_line', PlanStage.words, SessionGrading.choice),
  phraseIntro('phrase_intro', PlanStage.phrases, SessionGrading.pass),
  phraseAssemble('phrase_assemble', PlanStage.phrases, SessionGrading.choice),
  phraseChooseBack('phrase_choose_back', PlanStage.phrases, SessionGrading.choice),
  phraseSlot('phrase_slot', PlanStage.phrases, SessionGrading.choice),
  phraseSlotListen('phrase_slot_listen', PlanStage.phrases, SessionGrading.choice),
  phraseRepeat('phrase_repeat', PlanStage.phrases, SessionGrading.voice),
  phraseOtherSlot('phrase_other_slot', PlanStage.phrases, SessionGrading.voice),
  phraseCombine('phrase_combine', PlanStage.phrases, SessionGrading.choice),
  phraseOwnSlot('phrase_own_slot', PlanStage.phrases, SessionGrading.judge),
  dialoguePartner('dialogue_partner', PlanStage.dialogue, SessionGrading.choice),
  dialogueAnswer('dialogue_answer', PlanStage.dialogue, SessionGrading.voice),
  dialogueAsk('dialogue_ask', PlanStage.dialogue, SessionGrading.voice),
  dialogueRescue('dialogue_rescue', PlanStage.dialogue, SessionGrading.pass),
  listenDialogue('listen_dialogue', PlanStage.listen, SessionGrading.pass),
  listenQuestion('listen_question', PlanStage.listen, SessionGrading.choice),
  listenReview('listen_review', PlanStage.listen, SessionGrading.pass),
  listenPredict('listen_predict', PlanStage.listen, SessionGrading.choice),
  listenPace('listen_pace', PlanStage.listen, SessionGrading.pass),
  listenNumber('listen_number', PlanStage.listen, SessionGrading.choice),
  speakAnswer('speak_answer', PlanStage.speak, SessionGrading.judge),
  speakEcho('speak_echo', PlanStage.speak, SessionGrading.voice),
  speakRetell('speak_retell', PlanStage.speak, SessionGrading.judge);

  const SessionKind(this.wire, this.stage, this.grading);

  final String wire;
  final PlanStage stage;
  final SessionGrading grading;

  /// A kind this build does not know — null. `listen_pairs` does not get here either: it is in the server's
  /// enum, but it is not dealt, and it has no payload.
  static SessionKind? fromWire(Object? wire) {
    for (final k in values) {
      if (k.wire == wire) return k;
    }
    return null;
  }
}

/// The card's `result`.
enum SessionResult {
  passed,
  hinted,
  failed,
  skipped;

  String get wire => name;

  static SessionResult? fromWire(Object? wire) {
    for (final r in values) {
      if (r.name == wire) return r;
    }
    return null;
  }
}

/// `unit {kind, ref}` — what the card teaches.
class SessionUnit {
  const SessionUnit({required this.kind, required this.ref, this.isDay = false});

  /// For the `day` unit this is `unknown`: it has no program item (see [isDay]).
  final PlanUnitKind kind;

  /// `v3`, `p2`, `x4`, `day` or `L2`.
  final String ref;

  /// The `day` unit is the whole visit: it is not in the program, and it never returns.
  final bool isDay;

  factory SessionUnit.fromJson(Map<String, dynamic> j) {
    final kind = j['kind'];
    return SessionUnit(kind: PlanUnitKind.fromWire(kind as String?), ref: _str(j, 'ref'), isDay: kind == 'day');
  }

  @override
  bool operator ==(Object other) => other is SessionUnit && other.kind == kind && other.ref == ref && other.isDay == isDay;

  @override
  int get hashCode => Object.hash(kind, ref, isDay);
}

// ──────────────────────────────────────────────────────────────────────────────────────────────────
// Shared objects — the same in every payload.
// ──────────────────────────────────────────────────────────────────────────────────────────────────

/// Sound `{ref, url, duration_ms, voice}`. `url` is null while there is no file: then the phone reads it aloud.
class CardAudio {
  const CardAudio({required this.ref, this.url, this.durationMs, required this.voice});

  final String ref;
  final String? url;
  final int? durationMs;

  /// `partner` | `learner`.
  final String voice;

  static CardAudio? maybe(Object? j) {
    if (j is! Map<String, dynamic>) return null;
    return CardAudio(
      ref: (j['ref'] as String?) ?? '',
      url: _nonEmpty(j['url']),
      durationMs: (j['duration_ms'] as num?)?.toInt(),
      voice: (j['voice'] as String?) ?? 'learner',
    );
  }
}

/// A word's photo `{url, tone}`; `url` is null if no photo was found, `tone` paints the backdrop.
class CardImage {
  const CardImage({this.url, this.tone});

  final String? url;
  final String? tone;

  static CardImage? maybe(Object? j) {
    if (j is! Map<String, dynamic>) return null;
    return CardImage(url: _nonEmpty(j['url']), tone: _nonEmpty(j['tone']));
  }
}

/// A word `term {ref, text_target, text_native, pronunciation_native, definition_target, image}`.
class CardTerm {
  const CardTerm({
    required this.ref,
    required this.textTarget,
    required this.textNative,
    this.pronunciationNative,
    this.definitionTarget,
    this.image,
  });

  final String ref;
  final String textTarget;
  final String textNative;
  final String? pronunciationNative;

  /// The definition is in the TARGET language: the lesson carries no explanation in the native language
  /// («What the server does not give»).
  final String? definitionTarget;
  final CardImage? image;

  factory CardTerm.fromJson(Map<String, dynamic> j) => CardTerm(
    ref: _str(j, 'ref'),
    textTarget: _str(j, 'text_target'),
    textNative: _str(j, 'text_native'),
    pronunciationNative: _nonEmpty(j['pronunciation_native']),
    definitionTarget: _nonEmpty(j['definition_target']),
    image: CardImage.maybe(j['image']),
  );
}

/// A filler of the frame's slot.
class CardFiller {
  const CardFiller({
    required this.index,
    required this.target,
    required this.native,
    this.pronunciationNative,
    required this.inDialogue,
    this.nativeLine,
    this.audio,
  });

  final int index;
  final String target;
  final String native;
  final String? pronunciationNative;
  final bool inDialogue;

  /// The whole phrase in the native language with this filler — «His neck hurts.» (native).
  final String? nativeLine;

  /// The frame spoken with this filler (`p2.f3`).
  final CardAudio? audio;

  factory CardFiller.fromJson(Map<String, dynamic> j) => CardFiller(
    index: _int(j, 'index'),
    target: _str(j, 'target'),
    native: _str(j, 'native'),
    pronunciationNative: _nonEmpty(j['pronunciation_native']),
    inDialogue: j['in_dialogue'] == true,
    nativeLine: _nonEmpty(j['native_line']),
    audio: CardAudio.maybe(j['audio']),
  );
}

/// The frame's slot: a hint and the fillers.
class CardSlot {
  const CardSlot({this.hintNative, required this.fillers});

  final String? hintNative;
  final List<CardFiller> fillers;

  factory CardSlot.fromJson(Map<String, dynamic> j) => CardSlot(
    hintNative: _nonEmpty(j['hint_native']),
    fillers: _list(j, 'fillers', CardFiller.fromJson),
  );
}

/// The slot mark in the frame's text.
const String kSlotMark = '___';

/// A frame `frame {ref, kind, frame_target, frame_native, frame_pronunciation_native, slot}`.
class CardFrame {
  const CardFrame({
    required this.ref,
    required this.kind,
    required this.frameTarget,
    required this.frameNative,
    this.framePronunciationNative,
    this.slot,
  });

  final String ref;

  /// `answer` | `ask`.
  final String kind;

  /// With `___` in place of the slot; for a frame without a slot — the whole phrase.
  final String frameTarget;
  final String frameNative;
  final String? framePronunciationNative;

  /// Null — the frame has no slot.
  final CardSlot? slot;

  bool get hasSlot => slot != null && frameTarget.contains(kSlotMark);

  List<CardFiller> get fillers => slot?.fillers ?? const [];

  CardFiller? filler(int? index) {
    if (index == null) return null;
    for (final f in fillers) {
      if (f.index == index) return f;
    }
    return null;
  }

  /// The frame's text before and after the slot; for a frame without a slot «after» is empty.
  ({String before, String after}) get parts => splitAtSlot(frameTarget);

  /// The frame spoken with [value] in the slot; for a frame without a slot — the frame itself.
  String filledWith(String value) => hasSlot ? frameTarget.replaceFirst(kSlotMark, value) : frameTarget;

  /// The frame as a whole phrase: with the filler [fillerIndex] (the one said in the dialogue), otherwise with the
  /// first filler from the dialogue, otherwise with the first one; for a frame without a slot — the frame itself.
  String spoken([int? fillerIndex]) {
    if (!hasSlot || fillers.isEmpty) return frameTarget;
    final said = filler(fillerIndex) ?? fillers.where((f) => f.inDialogue).firstOrNull ?? fillers.first;
    return filledWith(said.target);
  }

  factory CardFrame.fromJson(Map<String, dynamic> j) => CardFrame(
    ref: _str(j, 'ref'),
    kind: (j['kind'] as String?) ?? 'answer',
    frameTarget: _str(j, 'frame_target'),
    frameNative: _str(j, 'frame_native'),
    framePronunciationNative: _nonEmpty(j['frame_pronunciation_native']),
    slot: j['slot'] is Map<String, dynamic> ? CardSlot.fromJson(j['slot'] as Map<String, dynamic>) : null,
  );
}

/// The text before and after the first `___`.
({String before, String after}) splitAtSlot(String text) {
  final at = text.indexOf(kSlotMark);
  if (at < 0) return (before: text, after: '');
  return (before: text.substring(0, at), after: text.substring(at + kSlotMark.length));
}

/// A line `{ref, text_target, text_native, audio}`.
class CardLine {
  const CardLine({required this.ref, required this.textTarget, required this.textNative, this.audio});

  final String ref;
  final String textTarget;
  final String textNative;
  final CardAudio? audio;

  factory CardLine.fromJson(Map<String, dynamic> j) => CardLine(
    ref: _str(j, 'ref'),
    textTarget: _str(j, 'text_target'),
    textNative: _str(j, 'text_native'),
    audio: CardAudio.maybe(j['audio']),
  );

  static CardLine? maybe(Object? j) => j is Map<String, dynamic> ? CardLine.fromJson(j) : null;
}

/// The learner's own line: a line plus the frame, the filler and the pronunciation key.
class CardOwnLine extends CardLine {
  const CardOwnLine({
    required super.ref,
    required super.textTarget,
    required super.textNative,
    super.audio,
    this.frameRef,
    this.fillerIndex,
    this.key,
  });

  final String? frameRef;
  final int? fillerIndex;
  final String? key;

  factory CardOwnLine.fromJson(Map<String, dynamic> j) => CardOwnLine(
    ref: _str(j, 'ref'),
    textTarget: _str(j, 'text_target'),
    textNative: _str(j, 'text_native'),
    audio: CardAudio.maybe(j['audio']),
    frameRef: _nonEmpty(j['frame_ref']),
    fillerIndex: (j['filler_index'] as num?)?.toInt(),
    key: _nonEmpty(j['key']),
  );
}

/// A visit line in the listening feed: also the role and the exchange step.
class CardVisitLine extends CardLine {
  const CardVisitLine({
    required super.ref,
    required super.textTarget,
    required super.textNative,
    super.audio,
    required this.role,
    this.exchangeStep,
  });

  /// `partner` | `learner`.
  final String role;
  final int? exchangeStep;

  factory CardVisitLine.fromJson(Map<String, dynamic> j) => CardVisitLine(
    ref: _str(j, 'ref'),
    textTarget: _str(j, 'text_target'),
    textNative: _str(j, 'text_native'),
    audio: CardAudio.maybe(j['audio']),
    role: (j['role'] as String?) ?? 'partner',
    exchangeStep: (j['exchange_step'] as num?)?.toInt(),
  );
}

/// An exchange `{ref, step, kind}`.
class CardExchange {
  const CardExchange({required this.ref, required this.step, required this.kind});

  final String ref;
  final int step;

  /// `answer` | `ask` | `rescue`.
  final String kind;

  factory CardExchange.fromJson(Map<String, dynamic> j) =>
      CardExchange(ref: _str(j, 'ref'), step: _int(j, 'step'), kind: _str(j, 'kind'));
}

/// An answer option `{id, text, audio?}`.
class CardOption {
  const CardOption({required this.id, required this.text, this.audio});

  final String id;
  final String text;
  final CardAudio? audio;

  factory CardOption.fromJson(Map<String, dynamic> j) =>
      CardOption(id: _str(j, 'id'), text: _str(j, 'text'), audio: CardAudio.maybe(j['audio']));
}

/// A span `[start, end)` in characters of the string.
typedef CardSpan = ({int start, int end});

CardSpan? _span(Object? j) {
  if (j is! List || j.length != 2 || j[0] is! num || j[1] is! num) return null;
  return (start: (j[0] as num).toInt(), end: (j[1] as num).toInt());
}

// ──────────────────────────────────────────────────────────────────────────────────────────────────
// Payload by kind.
// ──────────────────────────────────────────────────────────────────────────────────────────────────

/// The card payload — exactly one model per kind.
sealed class CardPayload {
  const CardPayload({required this.sceneId});

  final String sceneId;

  /// All of the card's sounds — downloaded on entering the stage.
  Iterable<CardAudio> get audios;

  static CardPayload parse(SessionKind kind, Map<String, dynamic> j) => switch (kind) {
    SessionKind.wordIntro => WordIntroPayload.fromJson(j),
    SessionKind.wordRepeat => WordRepeatPayload.fromJson(j),
    SessionKind.wordChoose => WordChoosePayload.fromJson(j),
    SessionKind.wordListen => WordListenPayload.fromJson(j),
    SessionKind.wordAssemble => WordAssemblePayload.fromJson(j),
    SessionKind.wordInLine => WordInLinePayload.fromJson(j),
    SessionKind.phraseIntro => PhraseIntroPayload.fromJson(j),
    SessionKind.phraseAssemble => PhraseAssemblePayload.fromJson(j),
    SessionKind.phraseChooseBack => PhraseChooseBackPayload.fromJson(j),
    SessionKind.phraseSlot => PhraseSlotPayload.fromJson(j),
    SessionKind.phraseSlotListen => PhraseSlotListenPayload.fromJson(j),
    SessionKind.phraseRepeat => PhraseRepeatPayload.fromJson(j),
    SessionKind.phraseOtherSlot => PhraseOtherSlotPayload.fromJson(j),
    SessionKind.phraseCombine => PhraseCombinePayload.fromJson(j),
    SessionKind.phraseOwnSlot => PhraseOwnSlotPayload.fromJson(j),
    SessionKind.dialoguePartner => DialoguePartnerPayload.fromJson(j),
    SessionKind.dialogueAnswer || SessionKind.dialogueAsk => DialogueAnswerPayload.fromJson(j),
    SessionKind.dialogueRescue => DialogueRescuePayload.fromJson(j),
    SessionKind.listenDialogue => ListenDialoguePayload.fromJson(j),
    SessionKind.listenQuestion => ListenQuestionPayload.fromJson(j),
    SessionKind.listenReview => ListenReviewPayload.fromJson(j),
    SessionKind.listenPredict => ListenPredictPayload.fromJson(j),
    SessionKind.listenPace => ListenPacePayload.fromJson(j),
    SessionKind.listenNumber => ListenNumberPayload.fromJson(j),
    SessionKind.speakAnswer => SpeakAnswerPayload.fromJson(j),
    SessionKind.speakEcho => SpeakEchoPayload.fromJson(j),
    SessionKind.speakRetell => SpeakRetellPayload.fromJson(j),
  };
}

/// An option card: the options and the id of the correct one.
mixin ChoicePayload on CardPayload {
  List<CardOption> get options;
  String get correct;

  CardOption? get correctOption {
    for (final o in options) {
      if (o.id == correct) return o;
    }
    return null;
  }
}

Iterable<CardAudio> _optionAudios(List<CardOption> options) => [
  for (final o in options) ?o.audio,
];

Iterable<CardAudio> _fillerAudios(List<CardFiller> fillers) => [
  for (final f in fillers) ?f.audio,
];

// ── Words ─────────────────────────────────────────────────────────────────────────────────────────

/// The day's line where the word is heard, and the word's position in it.
class CardUsedIn {
  const CardUsedIn({required this.ref, this.lineRef, this.termSpan, required this.textTarget, required this.textNative});

  final String ref;
  final String? lineRef;
  final CardSpan? termSpan;
  final String textTarget;
  final String textNative;

  factory CardUsedIn.fromJson(Map<String, dynamic> j) => CardUsedIn(
    ref: (j['ref'] as String?) ?? '',
    lineRef: _nonEmpty(j['line_ref']),
    termSpan: _span(j['term_span']),
    textTarget: _str(j, 'text_target'),
    textNative: _str(j, 'text_native'),
  );
}

/// `word_intro` (31-1).
class WordIntroPayload extends CardPayload {
  const WordIntroPayload({required super.sceneId, required this.term, this.usedIn, this.termAudio, this.lineAudio});

  final CardTerm term;
  final CardUsedIn? usedIn;
  final CardAudio? termAudio;

  /// Null if the word is not in the lines.
  final CardAudio? lineAudio;

  @override
  Iterable<CardAudio> get audios => [?termAudio, ?lineAudio];

  factory WordIntroPayload.fromJson(Map<String, dynamic> j) {
    final audio = j['audio'] as Map<String, dynamic>? ?? const {};
    return WordIntroPayload(
      sceneId: _scene(j),
      term: CardTerm.fromJson(_map(j, 'term')),
      usedIn: j['used_in'] is Map<String, dynamic> ? CardUsedIn.fromJson(j['used_in'] as Map<String, dynamic>) : null,
      termAudio: CardAudio.maybe(audio['term']),
      lineAudio: CardAudio.maybe(audio['line']),
    );
  }
}

/// `word_repeat` (31-2).
class WordRepeatPayload extends CardPayload {
  const WordRepeatPayload({
    required super.sceneId,
    required this.term,
    required this.expectedText,
    required this.coverageMin,
    this.termAudio,
  });

  final CardTerm term;
  final String expectedText;
  final double coverageMin;
  final CardAudio? termAudio;

  @override
  Iterable<CardAudio> get audios => [?termAudio];

  factory WordRepeatPayload.fromJson(Map<String, dynamic> j) => WordRepeatPayload(
    sceneId: _scene(j),
    term: CardTerm.fromJson(_map(j, 'term')),
    expectedText: _str(j, 'expected_text'),
    coverageMin: _num(j, 'coverage_min'),
    termAudio: CardAudio.maybe((j['audio'] as Map<String, dynamic>?)?['term']),
  );
}

/// `word_choose` (31-3 `term_to_native` / 31-4 `native_to_term`).
class WordChoosePayload extends CardPayload with ChoicePayload {
  const WordChoosePayload({
    required super.sceneId,
    required this.direction,
    this.promptTextTarget,
    this.promptTextNative,
    this.promptImage,
    this.promptAudio,
    required this.options,
    required this.correct,
  });

  /// `term_to_native` | `native_to_term`.
  final String direction;
  final String? promptTextTarget;
  final String? promptTextNative;
  final CardImage? promptImage;
  final CardAudio? promptAudio;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  bool get termToNative => direction == 'term_to_native';

  @override
  Iterable<CardAudio> get audios => [?promptAudio, ..._optionAudios(options)];

  factory WordChoosePayload.fromJson(Map<String, dynamic> j) {
    final prompt = _map(j, 'prompt');
    final direction = _str(j, 'direction');
    if (direction != 'term_to_native' && direction != 'native_to_term') {
      throw SessionContractError('word_choose direction «$direction»');
    }
    return WordChoosePayload(
      sceneId: _scene(j),
      direction: direction,
      promptTextTarget: _nonEmpty(prompt['text_target']),
      promptTextNative: _nonEmpty(prompt['text_native']),
      promptImage: CardImage.maybe(prompt['image']),
      promptAudio: CardAudio.maybe(prompt['audio']),
      options: _list(j, 'options', CardOption.fromJson),
      correct: _str(j, 'correct'),
    );
  }
}

/// `word_listen` (31-5): a sound — and its options: translations in the native language (`direction:
/// term_to_native`, contract SESSION-1e), or target-language spellings on a day dealt before it (no `direction`).
class WordListenPayload extends CardPayload with ChoicePayload {
  const WordListenPayload({required super.sceneId, this.direction, this.audio, required this.options, required this.correct});

  /// `term_to_native`; null — a day dealt before SESSION-1e, whose options are spellings.
  final String? direction;
  final CardAudio? audio;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  /// The options are translations in the native language — drawn like the options of `word_choose` `term_to_native`.
  bool get nativeOptions => direction == 'term_to_native';

  @override
  Iterable<CardAudio> get audios => [?audio];

  factory WordListenPayload.fromJson(Map<String, dynamic> j) {
    final direction = j['direction'];
    if (direction != null && direction != 'term_to_native') {
      throw SessionContractError('word_listen direction «$direction»');
    }
    return WordListenPayload(
      sceneId: _scene(j),
      direction: direction as String?,
      audio: CardAudio.maybe(j['audio']),
      options: _list(j, 'options', CardOption.fromJson),
      correct: _str(j, 'correct'),
    );
  }
}

/// `word_assemble` (31-6).
class WordAssemblePayload extends CardPayload {
  const WordAssemblePayload({required super.sceneId, required this.term, required this.tiles, required this.expected});

  final CardTerm term;
  final List<String> tiles;

  /// The assembled result must match in order, word for word.
  final List<String> expected;

  @override
  Iterable<CardAudio> get audios => const [];

  factory WordAssemblePayload.fromJson(Map<String, dynamic> j) => WordAssemblePayload(
    sceneId: _scene(j),
    term: CardTerm.fromJson(_map(j, 'term')),
    tiles: _strings(j, 'tiles'),
    expected: _strings(j, 'expected'),
  );
}

/// The `word_in_line` line: the target text with `___`, the translation, the translation with a gap, the sound.
class CardGappedLine {
  const CardGappedLine({required this.ref, this.lineRef, required this.textTarget, required this.textNative, this.textNativeGapped, this.audio});

  final String ref;
  final String? lineRef;
  final String textTarget;
  final String textNative;
  final String? textNativeGapped;
  final CardAudio? audio;

  factory CardGappedLine.fromJson(Map<String, dynamic> j) => CardGappedLine(
    ref: (j['ref'] as String?) ?? '',
    lineRef: _nonEmpty(j['line_ref']),
    textTarget: _str(j, 'text_target'),
    textNative: _str(j, 'text_native'),
    textNativeGapped: _nonEmpty(j['text_native_gapped']),
    audio: CardAudio.maybe(j['audio']),
  );
}

/// `word_in_line` (31-7).
class WordInLinePayload extends CardPayload with ChoicePayload {
  const WordInLinePayload({required super.sceneId, required this.line, required this.options, required this.correct});

  final CardGappedLine line;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  @override
  Iterable<CardAudio> get audios => [?line.audio, ..._optionAudios(options)];

  factory WordInLinePayload.fromJson(Map<String, dynamic> j) => WordInLinePayload(
    sceneId: _scene(j),
    line: CardGappedLine.fromJson(_map(j, 'line')),
    options: _list(j, 'options', CardOption.fromJson),
    correct: _str(j, 'correct'),
  );
}

// ── Phrases ───────────────────────────────────────────────────────────────────────────────────────

/// The day's phrase as it is said in the dialogue.
class CardSaid {
  const CardSaid({required this.textTarget, required this.textNative, this.pronunciationNative, this.fillerIndex, this.audio});

  final String textTarget;
  final String textNative;
  final String? pronunciationNative;
  final int? fillerIndex;
  final CardAudio? audio;

  factory CardSaid.fromJson(Map<String, dynamic> j) => CardSaid(
    textTarget: _str(j, 'text_target'),
    textNative: _str(j, 'text_native'),
    pronunciationNative: _nonEmpty(j['pronunciation_native']),
    fillerIndex: (j['filler_index'] as num?)?.toInt(),
    audio: CardAudio.maybe(j['audio']),
  );
}

/// `phrase_intro` (32-1).
class PhraseIntroPayload extends CardPayload {
  const PhraseIntroPayload({required super.sceneId, required this.frame, required this.said});

  final CardFrame frame;
  final CardSaid said;

  @override
  Iterable<CardAudio> get audios => [?said.audio, ..._fillerAudios(frame.fillers)];

  factory PhraseIntroPayload.fromJson(Map<String, dynamic> j) => PhraseIntroPayload(
    sceneId: _scene(j),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    said: CardSaid.fromJson(_map(j, 'said')),
  );
}

/// `phrase_assemble` (32-2).
class PhraseAssemblePayload extends CardPayload {
  const PhraseAssemblePayload({
    required super.sceneId,
    required this.frame,
    required this.targetNative,
    required this.tiles,
    required this.chips,
    required this.expectedWords,
    required this.slotAt,
    required this.fillerIndex,
  });

  final CardFrame frame;

  /// What to assemble — a sentence in the native language.
  final String targetNative;

  /// The frame's words and extra ones — lowercase, except «I».
  final List<String> tiles;
  final List<CardFiller> chips;
  final List<String> expectedWords;

  /// The slot's position in the assembled string.
  final int slotAt;
  final int? fillerIndex;

  @override
  Iterable<CardAudio> get audios => _fillerAudios(chips);

  factory PhraseAssemblePayload.fromJson(Map<String, dynamic> j) {
    final expected = _map(j, 'expected');
    return PhraseAssemblePayload(
      sceneId: _scene(j),
      frame: CardFrame.fromJson(_map(j, 'frame')),
      targetNative: _str(j, 'target_native'),
      tiles: _strings(j, 'tiles'),
      chips: _list(j, 'chips', CardFiller.fromJson),
      expectedWords: _strings(expected, 'words'),
      slotAt: _int(expected, 'slot_at'),
      fillerIndex: (expected['filler_index'] as num?)?.toInt(),
    );
  }
}

/// `phrase_choose_back` (32-3): the phrase in the target language — options in the native language.
class PhraseChooseBackPayload extends CardPayload with ChoicePayload {
  const PhraseChooseBackPayload({
    required super.sceneId,
    required this.textTarget,
    this.pronunciationNative,
    this.fillerIndex,
    this.audio,
    required this.options,
    required this.correct,
  });

  final String textTarget;
  final String? pronunciationNative;
  final int? fillerIndex;
  final CardAudio? audio;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  @override
  Iterable<CardAudio> get audios => [?audio];

  factory PhraseChooseBackPayload.fromJson(Map<String, dynamic> j) {
    final prompt = _map(j, 'prompt');
    return PhraseChooseBackPayload(
      sceneId: _scene(j),
      textTarget: _str(prompt, 'text_target'),
      pronunciationNative: _nonEmpty(prompt['pronunciation_native']),
      fillerIndex: (prompt['filler_index'] as num?)?.toInt(),
      audio: CardAudio.maybe(prompt['audio']),
      options: _list(j, 'options', CardOption.fromJson),
      correct: _str(j, 'correct'),
    );
  }
}

/// `phrase_slot` (32-4).
class PhraseSlotPayload extends CardPayload with ChoicePayload {
  const PhraseSlotPayload({required super.sceneId, required this.frame, required this.promptNative, required this.options, required this.correct});

  final CardFrame frame;
  final String promptNative;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  @override
  Iterable<CardAudio> get audios => _optionAudios(options);

  factory PhraseSlotPayload.fromJson(Map<String, dynamic> j) => PhraseSlotPayload(
    sceneId: _scene(j),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    promptNative: _str(j, 'prompt_native'),
    options: _list(j, 'options', CardOption.fromJson),
    correct: _str(j, 'correct'),
  );
}

/// `phrase_slot_listen` (32-5).
class PhraseSlotListenPayload extends CardPayload with ChoicePayload {
  const PhraseSlotListenPayload({
    required super.sceneId,
    required this.frame,
    required this.fillerIndex,
    this.audio,
    required this.options,
    required this.correct,
  });

  final CardFrame frame;

  /// Which filler is heard.
  final int fillerIndex;
  final CardAudio? audio;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  @override
  Iterable<CardAudio> get audios => [?audio];

  factory PhraseSlotListenPayload.fromJson(Map<String, dynamic> j) => PhraseSlotListenPayload(
    sceneId: _scene(j),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    fillerIndex: _int(j, 'filler_index'),
    audio: CardAudio.maybe(j['audio']),
    options: _list(j, 'options', CardOption.fromJson),
    correct: _str(j, 'correct'),
  );
}

/// `phrase_repeat` (32-6).
class PhraseRepeatPayload extends CardPayload {
  const PhraseRepeatPayload({
    required super.sceneId,
    required this.frame,
    this.fillerIndex,
    required this.expectedText,
    this.key,
    required this.coverageMin,
    this.audio,
  });

  final CardFrame frame;
  final int? fillerIndex;
  final String expectedText;

  /// The pronunciation key — underlined in brass.
  final String? key;
  final double coverageMin;
  final CardAudio? audio;

  @override
  Iterable<CardAudio> get audios => [?audio];

  factory PhraseRepeatPayload.fromJson(Map<String, dynamic> j) => PhraseRepeatPayload(
    sceneId: _scene(j),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    fillerIndex: (j['filler_index'] as num?)?.toInt(),
    expectedText: _str(j, 'expected_text'),
    key: _nonEmpty(j['key']),
    coverageMin: _num(j, 'coverage_min'),
    audio: CardAudio.maybe(j['audio']),
  );
}

/// `phrase_other_slot` (32-7): the frame with a DIFFERENT filler, given in the native language.
class PhraseOtherSlotPayload extends CardPayload {
  const PhraseOtherSlotPayload({
    required super.sceneId,
    required this.frame,
    required this.fillerIndex,
    required this.taskNative,
    required this.expectedText,
    required this.slotExpected,
    this.key,
    required this.coverageMin,
  });

  final CardFrame frame;
  final int fillerIndex;
  final String taskNative;
  final String expectedText;
  final String slotExpected;
  final String? key;
  final double coverageMin;

  @override
  Iterable<CardAudio> get audios => const [];

  factory PhraseOtherSlotPayload.fromJson(Map<String, dynamic> j) => PhraseOtherSlotPayload(
    sceneId: _scene(j),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    fillerIndex: _int(j, 'filler_index'),
    taskNative: _str(j, 'task_native'),
    expectedText: _str(j, 'expected_text'),
    slotExpected: _str(j, 'slot_expected'),
    key: _nonEmpty(j['key']),
    coverageMin: _num(j, 'coverage_min'),
  );
}

/// A frame in `phrase_combine`: text only.
class CardFrameText {
  const CardFrameText({required this.ref, required this.frameTarget, required this.frameNative, this.said});

  final String ref;
  final String frameTarget;
  final String frameNative;

  /// The phrase said with this frame in the dialogue — if the server sent it (`frames[].said`).
  final CardSaid? said;

  factory CardFrameText.fromJson(Map<String, dynamic> j) => CardFrameText(
    ref: _str(j, 'ref'),
    frameTarget: _str(j, 'frame_target'),
    frameNative: _str(j, 'frame_native'),
    said: j['said'] is Map<String, dynamic> ? CardSaid.fromJson(j['said'] as Map<String, dynamic>) : null,
  );
}

/// `phrase_combine` (32-8).
class PhraseCombinePayload extends CardPayload {
  const PhraseCombinePayload({
    required super.sceneId,
    required this.exchange,
    this.partnerLine,
    required this.frames,
    required this.correctFrame,
    required this.chips,
    this.correctFiller,
  });

  final CardExchange exchange;
  final CardLine? partnerLine;
  final List<CardFrameText> frames;
  final String correctFrame;
  final List<CardFiller> chips;
  final int? correctFiller;

  @override
  Iterable<CardAudio> get audios => [?partnerLine?.audio, for (final f in frames) ?f.said?.audio, ..._fillerAudios(chips)];

  factory PhraseCombinePayload.fromJson(Map<String, dynamic> j) => PhraseCombinePayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    partnerLine: CardLine.maybe(j['partner_line']),
    frames: _list(j, 'frames', CardFrameText.fromJson),
    correctFrame: _str(j, 'correct_frame'),
    chips: _list(j, 'chips', CardFiller.fromJson),
    correctFiller: (j['correct_filler'] as num?)?.toInt(),
  );
}

/// `phrase_own_slot` (32-9): an own slot; the pass by meaning is the server's.
class PhraseOwnSlotPayload extends CardPayload {
  const PhraseOwnSlotPayload({
    required super.sceneId,
    required this.frame,
    this.partnerLine,
    required this.taskNative,
    required this.examples,
    required this.chips,
    this.key,
    required this.coverageMin,
  });

  final CardFrame frame;
  final CardLine? partnerLine;
  final String taskNative;
  final List<String> examples;
  final List<CardFiller> chips;
  final String? key;
  final double coverageMin;

  @override
  Iterable<CardAudio> get audios => _fillerAudios(chips);

  factory PhraseOwnSlotPayload.fromJson(Map<String, dynamic> j) => PhraseOwnSlotPayload(
    sceneId: _scene(j),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    partnerLine: CardLine.maybe(j['partner_line']),
    taskNative: _str(j, 'task_native'),
    examples: _strings(j, 'examples'),
    chips: _list(j, 'chips', CardFiller.fromJson),
    key: _nonEmpty(j['key']),
    coverageMin: _num(j, 'coverage_min'),
  );
}

// ── Dialogue ──────────────────────────────────────────────────────────────────────────────────────

/// `dialogue_partner` (33-1).
class DialoguePartnerPayload extends CardPayload with ChoicePayload {
  const DialoguePartnerPayload({
    required super.sceneId,
    required this.exchange,
    required this.partnerLine,
    required this.questionNative,
    required this.options,
    required this.correct,
  });

  final CardExchange exchange;
  final CardLine partnerLine;
  final String questionNative;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  @override
  Iterable<CardAudio> get audios => [?partnerLine.audio];

  factory DialoguePartnerPayload.fromJson(Map<String, dynamic> j) => DialoguePartnerPayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    partnerLine: CardLine.fromJson(_map(j, 'partner_line')),
    questionNative: _str(j, 'question_native'),
    options: _list(j, 'options', CardOption.fromJson),
    correct: _str(j, 'correct'),
  );
}

/// Answer modes in the dialogue: chips, voice with a hint, blind voice.
class CardAnswerModes {
  const CardAnswerModes({required this.chips, required this.voiceHint, required this.voiceBlind});

  final List<CardFiller> chips;
  final String voiceHint;
  final String voiceBlind;

  factory CardAnswerModes.fromJson(Map<String, dynamic> j) => CardAnswerModes(
    chips: _list(j, 'chips', CardFiller.fromJson),
    voiceHint: _str(j, 'voice_hint'),
    voiceBlind: _str(j, 'voice_blind'),
  );
}

/// `dialogue_answer` (33-2…33-4) and `dialogue_ask` (33-5) — the same keys.
class DialogueAnswerPayload extends CardPayload {
  const DialogueAnswerPayload({
    required super.sceneId,
    required this.exchange,
    this.partnerLine,
    required this.ownLine,
    required this.frame,
    required this.modes,
    required this.coverageMin,
  });

  final CardExchange exchange;
  final CardLine? partnerLine;
  final CardOwnLine ownLine;
  final CardFrame frame;
  final CardAnswerModes modes;
  final double coverageMin;

  @override
  Iterable<CardAudio> get audios => [?partnerLine?.audio, ?ownLine.audio];

  factory DialogueAnswerPayload.fromJson(Map<String, dynamic> j) => DialogueAnswerPayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    partnerLine: CardLine.maybe(j['partner_line']),
    ownLine: CardOwnLine.fromJson(_map(j, 'own_line')),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    modes: CardAnswerModes.fromJson(_map(j, 'modes')),
    coverageMin: _num(j, 'coverage_min'),
  );
}

/// `dialogue_rescue` (33-6).
class DialogueRescuePayload extends CardPayload {
  const DialogueRescuePayload({
    required super.sceneId,
    required this.exchange,
    this.askedLine,
    required this.rescueLine,
    required this.partnerRepeat,
    required this.slowRate,
    required this.expectedText,
    required this.coverageMin,
  });

  final CardExchange exchange;
  final CardLine? askedLine;
  final CardLine rescueLine;
  final CardLine partnerRepeat;
  final double slowRate;
  final String expectedText;
  final double coverageMin;

  @override
  Iterable<CardAudio> get audios => [?askedLine?.audio, ?rescueLine.audio, ?partnerRepeat.audio];

  factory DialogueRescuePayload.fromJson(Map<String, dynamic> j) => DialogueRescuePayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    askedLine: CardLine.maybe(j['asked_line']),
    rescueLine: CardLine.fromJson(_map(j, 'rescue_line')),
    partnerRepeat: CardLine.fromJson(_map(j, 'partner_repeat')),
    slowRate: _num(j, 'slow_rate'),
    expectedText: _str(j, 'expected_text'),
    coverageMin: _num(j, 'coverage_min'),
  );
}

// ── Listen and answer ────────────────────────────────────────────────────────────────────────────

/// `listen_dialogue` (34-1).
class ListenDialoguePayload extends CardPayload {
  const ListenDialoguePayload({required super.sceneId, required this.lines, this.totalMs});

  final List<CardVisitLine> lines;
  final int? totalMs;

  @override
  Iterable<CardAudio> get audios => [for (final l in lines) ?l.audio];

  factory ListenDialoguePayload.fromJson(Map<String, dynamic> j) => ListenDialoguePayload(
    sceneId: _scene(j),
    lines: _list(j, 'lines', CardVisitLine.fromJson),
    totalMs: (j['total_ms'] as num?)?.toInt(),
  );
}

/// `listen_question` (34-2).
class ListenQuestionPayload extends CardPayload with ChoicePayload {
  const ListenQuestionPayload({
    required super.sceneId,
    required this.questionRef,
    required this.questionNative,
    required this.options,
    required this.correct,
    this.exchangeStep,
  });

  final String questionRef;
  final String questionNative;
  @override
  final List<CardOption> options;
  @override
  final String correct;
  final int? exchangeStep;

  @override
  Iterable<CardAudio> get audios => const [];

  factory ListenQuestionPayload.fromJson(Map<String, dynamic> j) {
    final question = _map(j, 'question');
    return ListenQuestionPayload(
      sceneId: _scene(j),
      questionRef: _str(question, 'ref'),
      questionNative: _str(question, 'text_native'),
      options: _list(j, 'options', CardOption.fromJson),
      correct: _str(j, 'correct'),
      exchangeStep: (j['exchange_step'] as num?)?.toInt(),
    );
  }
}

/// Where in the feed the answer to the question was heard.
class CardReviewAnswer {
  const CardReviewAnswer({required this.questionRef, this.exchangeStep, this.lineRef, this.span});

  final String questionRef;
  final int? exchangeStep;
  final String? lineRef;
  final CardSpan? span;

  factory CardReviewAnswer.fromJson(Map<String, dynamic> j) => CardReviewAnswer(
    questionRef: _str(j, 'question_ref'),
    exchangeStep: (j['exchange_step'] as num?)?.toInt(),
    lineRef: _nonEmpty(j['line_ref']),
    span: _span(j['span']),
  );
}

/// `listen_review` (34-3).
class ListenReviewPayload extends CardPayload {
  const ListenReviewPayload({required super.sceneId, required this.lines, this.totalMs, required this.answers});

  final List<CardVisitLine> lines;
  final int? totalMs;
  final List<CardReviewAnswer> answers;

  @override
  Iterable<CardAudio> get audios => [for (final l in lines) ?l.audio];

  factory ListenReviewPayload.fromJson(Map<String, dynamic> j) => ListenReviewPayload(
    sceneId: _scene(j),
    lines: _list(j, 'lines', CardVisitLine.fromJson),
    totalMs: (j['total_ms'] as num?)?.toInt(),
    answers: _list(j, 'answers', CardReviewAnswer.fromJson),
  );
}

/// `listen_predict` (34-5).
class ListenPredictPayload extends CardPayload with ChoicePayload {
  const ListenPredictPayload({
    required super.sceneId,
    required this.exchange,
    required this.ownLine,
    required this.options,
    required this.correct,
    required this.partnerLine,
  });

  final CardExchange exchange;
  final CardLine ownLine;
  @override
  final List<CardOption> options;
  @override
  final String correct;
  final CardLine partnerLine;

  @override
  Iterable<CardAudio> get audios => [?ownLine.audio, ?partnerLine.audio];

  factory ListenPredictPayload.fromJson(Map<String, dynamic> j) => ListenPredictPayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    ownLine: CardLine.fromJson(_map(j, 'own_line')),
    options: _list(j, 'options', CardOption.fromJson),
    correct: _str(j, 'correct'),
    partnerLine: CardLine.fromJson(_map(j, 'partner_line')),
  );
}

/// `listen_pace` (34-6).
class ListenPacePayload extends CardPayload {
  const ListenPacePayload({required super.sceneId, required this.exchange, required this.partnerLine, required this.rates});

  final CardExchange exchange;
  final CardLine partnerLine;
  final List<double> rates;

  @override
  Iterable<CardAudio> get audios => [?partnerLine.audio];

  factory ListenPacePayload.fromJson(Map<String, dynamic> j) => ListenPacePayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    partnerLine: CardLine.fromJson(_map(j, 'partner_line')),
    rates: [
      for (final r in (j['rates'] as List?) ?? const [])
        if (r is num) r.toDouble(),
    ],
  );
}

/// `listen_number` (34-7).
class ListenNumberPayload extends CardPayload with ChoicePayload {
  const ListenNumberPayload({required super.sceneId, required this.line, this.span, required this.options, required this.correct});

  final CardVisitLine line;
  final CardSpan? span;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  @override
  Iterable<CardAudio> get audios => [?line.audio];

  factory ListenNumberPayload.fromJson(Map<String, dynamic> j) => ListenNumberPayload(
    sceneId: _scene(j),
    line: CardVisitLine.fromJson(_map(j, 'line')),
    span: _span(j['span']),
    options: _list(j, 'options', CardOption.fromJson),
    correct: _str(j, 'correct'),
  );
}

// ── Speak myself ──────────────────────────────────────────────────────────────────────────────────

/// `speak_answer` (35-2).
class SpeakAnswerPayload extends CardPayload {
  const SpeakAnswerPayload({
    required super.sceneId,
    required this.exchange,
    this.partnerLine,
    required this.ownLine,
    required this.taskNative,
    required this.frame,
    required this.hint,
    this.key,
    required this.coverageMin,
  });

  final CardExchange exchange;
  final CardLine? partnerLine;
  final CardOwnLine ownLine;
  final String taskNative;
  final CardFrame frame;
  final String hint;
  final String? key;
  final double coverageMin;

  @override
  Iterable<CardAudio> get audios => [?partnerLine?.audio];

  factory SpeakAnswerPayload.fromJson(Map<String, dynamic> j) => SpeakAnswerPayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    partnerLine: CardLine.maybe(j['partner_line']),
    ownLine: CardOwnLine.fromJson(_map(j, 'own_line')),
    taskNative: _str(j, 'task_native'),
    frame: CardFrame.fromJson(_map(j, 'frame')),
    hint: _str(j, 'hint'),
    key: _nonEmpty(j['key']),
    coverageMin: _num(j, 'coverage_min'),
  );
}

/// `speak_echo` (35-3).
class SpeakEchoPayload extends CardPayload {
  const SpeakEchoPayload({
    required super.sceneId,
    required this.exchange,
    required this.partnerLine,
    required this.expectedText,
    required this.coverageMin,
    required this.pauseMs,
  });

  final CardExchange exchange;
  final CardLine partnerLine;
  final String expectedText;
  final double coverageMin;
  final int pauseMs;

  @override
  Iterable<CardAudio> get audios => [?partnerLine.audio];

  factory SpeakEchoPayload.fromJson(Map<String, dynamic> j) => SpeakEchoPayload(
    sceneId: _scene(j),
    exchange: CardExchange.fromJson(_map(j, 'exchange')),
    partnerLine: CardLine.fromJson(_map(j, 'partner_line')),
    expectedText: _str(j, 'expected_text'),
    coverageMin: _num(j, 'coverage_min'),
    pauseMs: _int(j, 'pause_ms'),
  );
}

/// `speak_retell` (35-4).
class SpeakRetellPayload extends CardPayload {
  const SpeakRetellPayload({
    required super.sceneId,
    required this.exchange,
    required this.partnerLine,
    required this.revealTarget,
    required this.revealNative,
  });

  final CardExchange exchange;
  final CardLine partnerLine;
  final String revealTarget;
  final String revealNative;

  @override
  Iterable<CardAudio> get audios => [?partnerLine.audio];

  factory SpeakRetellPayload.fromJson(Map<String, dynamic> j) {
    final reveal = _map(j, 'reveal');
    return SpeakRetellPayload(
      sceneId: _scene(j),
      exchange: CardExchange.fromJson(_map(j, 'exchange')),
      partnerLine: CardLine.fromJson(_map(j, 'partner_line')),
      revealTarget: _str(reveal, 'text_target'),
      revealNative: _str(reveal, 'text_native'),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────────────────────────
// Envelope.
// ──────────────────────────────────────────────────────────────────────────────────────────────────

/// A day card — the envelope `{id, stage, position, kind, unit, source, source_day, retry_of, payload,
/// result, attempts, response}`.
class SessionCard {
  const SessionCard({
    required this.id,
    required this.stage,
    required this.position,
    required this.kind,
    required this.unit,
    required this.returned,
    this.sourceDay,
    this.retryOf,
    required this.payload,
    this.result,
    required this.attempts,
    this.response,
    this.returns = false,
  });

  final String id;
  final PlanStage stage;
  final int position;
  final SessionKind kind;
  final SessionUnit unit;

  /// `source: returned` — the unit returned after two failures.
  final bool returned;

  /// The number of the day on which the unit failed (only for a returned one).
  final int? sourceDay;
  final String? retryOf;
  final CardPayload payload;
  final SessionResult? result;
  final int attempts;

  /// What is left of the last attempt — as it arrived.
  final Map<String, dynamic>? response;

  /// Failed twice — the unit will return the next day.
  final bool returns;

  bool get isAnswered => result != null;

  /// The card as it was dealt — no result, no attempts, nothing heard; `returns` stays the server's fact about the
  /// unit. For «Once more» (SESSION-2a §4), which walks a stage again on the phone only.
  SessionCard fresh() => SessionCard(
    id: id,
    stage: stage,
    position: position,
    kind: kind,
    unit: unit,
    returned: returned,
    sourceDay: sourceDay,
    retryOf: retryOf,
    payload: payload,
    attempts: 0,
    returns: returns,
  );

  /// Null — the kind is unknown to this build: the card is skipped, and there is no request about it. A known
  /// kind with a broken payload — [SessionContractError].
  static SessionCard? fromJson(Map<String, dynamic> j) {
    final kind = SessionKind.fromWire(j['kind']);
    if (kind == null) return null;
    final payload = j['payload'];
    if (payload is! Map<String, dynamic>) throw SessionContractError('${kind.wire} without payload');
    final stage = PlanStage.fromWire(j['stage'] as String?);
    if (stage == PlanStage.unknown) throw SessionContractError('${kind.wire} stage «${j['stage']}»');
    final unit = j['unit'];
    return SessionCard(
      id: _str(j, 'id'),
      stage: stage,
      position: _int(j, 'position'),
      kind: kind,
      unit: unit is Map<String, dynamic>
          ? SessionUnit.fromJson(unit)
          : SessionUnit(
              kind: PlanUnitKind.fromWire(j['unit_kind'] as String?),
              ref: (j['unit_ref'] as String?) ?? '',
              isDay: j['unit_kind'] == 'day',
            ),
      returned: j['source'] == 'returned',
      sourceDay: (j['source_day'] as num?)?.toInt(),
      retryOf: _nonEmpty(j['retry_of']),
      payload: CardPayload.parse(kind, payload),
      result: SessionResult.fromWire(j['result']),
      attempts: (j['attempts'] as num?)?.toInt() ?? 0,
      response: j['response'] is Map<String, dynamic> ? j['response'] as Map<String, dynamic> : null,
      returns: j['returns'] == true,
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────────────────────────
// Parsing.
// ──────────────────────────────────────────────────────────────────────────────────────────────────

String _scene(Map<String, dynamic> j) => _str(j, 'scene_id');

String _str(Map<String, dynamic> j, String key) {
  final v = j[key];
  if (v is String) return v;
  throw SessionContractError('«$key» is ${v.runtimeType}');
}

String? _nonEmpty(Object? v) => v is String && v.trim().isNotEmpty ? v : null;

int _int(Map<String, dynamic> j, String key) {
  final v = j[key];
  if (v is num) return v.toInt();
  throw SessionContractError('«$key» is ${v.runtimeType}');
}

/// Shares and rates arrive as a number with a fractional part (`1.0` / `0.7`), but an integer is a number too.
double _num(Map<String, dynamic> j, String key) {
  final v = j[key];
  if (v is num) return v.toDouble();
  throw SessionContractError('«$key» is ${v.runtimeType}');
}

Map<String, dynamic> _map(Map<String, dynamic> j, String key) {
  final v = j[key];
  if (v is Map<String, dynamic>) return v;
  throw SessionContractError('«$key» is ${v.runtimeType}');
}

List<String> _strings(Map<String, dynamic> j, String key) {
  final v = j[key];
  if (v is! List) throw SessionContractError('«$key» is ${v.runtimeType}');
  return [for (final e in v) if (e is String) e];
}

List<T> _list<T>(Map<String, dynamic> j, String key, T Function(Map<String, dynamic>) parse) {
  final v = j[key];
  if (v is! List) throw SessionContractError('«$key» is ${v.runtimeType}');
  return [for (final e in v) if (e is Map<String, dynamic>) parse(e)];
}
