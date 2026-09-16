/// КАРТОЧКИ СЕССИИ ДНЯ — реестр 28 тренажёров, как его отдаёт сервер (наряд SESSION-1b).
///
/// Контракт — `backend2/docs/plan-api.md`, «Карточки сессии», и схема `PlanCard` тега `Plans` в
/// `openapi/openapi.yaml`; вход клиента — фикстуры `backend2/docs/fixtures/day-doctor*.json`. Здесь
/// только чтение: конверт карточки, общие объекты (слово, каркас, наполнение, реплика, звук, фото) и
/// по одной модели на каждый раздаваемый вид — все 28, хотя экраны в 1b есть только у слов и фраз.
///
/// Разбор ЗАКРЫТЫЙ там, где карточку иначе нечем показать: у известного вида без обязательного поля
/// бросается [SessionContractError], и сессия эту карточку пропускает. Вид, которого эта сборка не
/// знает (и зарезервированный `listen_pairs`, у которого нет payload), — не ошибка: [SessionCard.fromJson]
/// отдаёт null, и карточка пропускается без запроса к серверу.
library;

import '../plan_models.dart';

/// Сервер прислал известный вид с payload, который этой сборке нечем показать.
class SessionContractError extends FormatException {
  const SessionContractError(String what) : super('session card: $what');
}

/// Как вид зачитывается — и что поэтому клиенту позволено записать (`CardKind::allows` на сервере).
enum SessionGrading {
  /// Выбор и плитки: клиент сверяет с `correct` / `expected`.
  choice,

  /// Голос: покрытие речи по `coverage_min`, две попытки без зачёта — `skipped`.
  voice,

  /// По смыслу — только сервер, `POST …/judge`.
  judge,

  /// Прохождение: «Понятно» / «Дальше».
  pass,
}

/// ВИД КАРТОЧКИ — 29 значений enum на сервере, раздаются 28.
///
/// [hasScreen] — у вида есть экран в этой сборке (1b: слова и фразы). Остальные читаются целиком, но
/// их этапы стоят «впереди», а вход в них заблокирован.
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

  /// Вид, которого эта сборка не знает, — null. `listen_pairs` сюда тоже не попадает: он в enum
  /// сервера, но не раздаётся, и payload у него нет.
  static SessionKind? fromWire(Object? wire) {
    for (final k in values) {
      if (k.wire == wire) return k;
    }
    return null;
  }

  /// Этапы, у которых в этой сборке есть экраны (наряд SESSION-1b, разд. 0).
  static const Set<PlanStage> stagesWithScreens = {PlanStage.words, PlanStage.phrases};

  bool get hasScreen => stagesWithScreens.contains(stage);
}

/// `result` карточки.
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

/// `unit {kind, ref}` — что карточка учит.
class SessionUnit {
  const SessionUnit({required this.kind, required this.ref, this.isDay = false});

  /// У единицы `day` здесь `unknown`: пункта программы у неё нет (см. [isDay]).
  final PlanUnitKind kind;

  /// `v3`, `p2`, `x4`, `day` или `L2`.
  final String ref;

  /// Единица `day` — весь визит: в программе её нет, и она никогда не возвращается.
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
// Общие объекты — одинаковые во всех payload.
// ──────────────────────────────────────────────────────────────────────────────────────────────────

/// Звук `{ref, url, duration_ms, voice}`. `url` — null, пока файла нет: тогда читает телефон.
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

/// Фото слова `{url, tone}`; `url` — null, если фото не нашлось, `tone` рисует подложку.
class CardImage {
  const CardImage({this.url, this.tone});

  final String? url;
  final String? tone;

  static CardImage? maybe(Object? j) {
    if (j is! Map<String, dynamic>) return null;
    return CardImage(url: _nonEmpty(j['url']), tone: _nonEmpty(j['tone']));
  }
}

/// Слово `term {ref, text_target, text_native, pronunciation_native, definition_target, image}`.
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

  /// Определение — на языке ЦЕЛИ: толкования на родном урок не несёт («Чего сервер не даёт»).
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

/// Наполнение окна каркаса.
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

  /// Вся фраза на родном с этим наполнением — «У него болит шея.».
  final String? nativeLine;

  /// Каркас, сказанный с этим наполнением (`p2.f3`).
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

/// Окно каркаса: подсказка и наполнения.
class CardSlot {
  const CardSlot({this.hintNative, required this.fillers});

  final String? hintNative;
  final List<CardFiller> fillers;

  factory CardSlot.fromJson(Map<String, dynamic> j) => CardSlot(
    hintNative: _nonEmpty(j['hint_native']),
    fillers: _list(j, 'fillers', CardFiller.fromJson),
  );
}

/// Знак окна в тексте каркаса.
const String kSlotMark = '___';

/// Каркас `frame {ref, kind, frame_target, frame_native, frame_pronunciation_native, slot}`.
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

  /// С `___` на месте окна; у каркаса без окна — целая фраза.
  final String frameTarget;
  final String frameNative;
  final String? framePronunciationNative;

  /// Null — у каркаса нет окна.
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

  /// Текст каркаса до и после окна; у каркаса без окна «после» пустое.
  ({String before, String after}) get parts => splitAtSlot(frameTarget);

  /// Каркас, сказанный с [value] в окне; у каркаса без окна — он сам.
  String filledWith(String value) => hasSlot ? frameTarget.replaceFirst(kSlotMark, value) : frameTarget;

  factory CardFrame.fromJson(Map<String, dynamic> j) => CardFrame(
    ref: _str(j, 'ref'),
    kind: (j['kind'] as String?) ?? 'answer',
    frameTarget: _str(j, 'frame_target'),
    frameNative: _str(j, 'frame_native'),
    framePronunciationNative: _nonEmpty(j['frame_pronunciation_native']),
    slot: j['slot'] is Map<String, dynamic> ? CardSlot.fromJson(j['slot'] as Map<String, dynamic>) : null,
  );
}

/// Текст до и после первого `___`.
({String before, String after}) splitAtSlot(String text) {
  final at = text.indexOf(kSlotMark);
  if (at < 0) return (before: text, after: '');
  return (before: text.substring(0, at), after: text.substring(at + kSlotMark.length));
}

/// Реплика `{ref, text_target, text_native, audio}`.
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

/// Своя реплика ученика: реплика плюс каркас, наполнение и ключ произнесения.
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

/// Реплика визита в ленте слушания: ещё роль и шаг обмена.
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

/// Обмен `{ref, step, kind}`.
class CardExchange {
  const CardExchange({required this.ref, required this.step, required this.kind});

  final String ref;
  final int step;

  /// `answer` | `ask` | `rescue`.
  final String kind;

  factory CardExchange.fromJson(Map<String, dynamic> j) =>
      CardExchange(ref: _str(j, 'ref'), step: _int(j, 'step'), kind: _str(j, 'kind'));
}

/// Вариант ответа `{id, text, audio?}`.
class CardOption {
  const CardOption({required this.id, required this.text, this.audio});

  final String id;
  final String text;
  final CardAudio? audio;

  factory CardOption.fromJson(Map<String, dynamic> j) =>
      CardOption(id: _str(j, 'id'), text: _str(j, 'text'), audio: CardAudio.maybe(j['audio']));
}

/// Отрезок `[начало, конец)` в символах строки.
typedef CardSpan = ({int start, int end});

CardSpan? _span(Object? j) {
  if (j is! List || j.length != 2 || j[0] is! num || j[1] is! num) return null;
  return (start: (j[0] as num).toInt(), end: (j[1] as num).toInt());
}

// ──────────────────────────────────────────────────────────────────────────────────────────────────
// Payload по видам.
// ──────────────────────────────────────────────────────────────────────────────────────────────────

/// Payload карточки — ровно одна модель на вид.
sealed class CardPayload {
  const CardPayload({required this.sceneId});

  final String sceneId;

  /// Все звуки карточки — докачка при входе в этап.
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

/// Вариантная карточка: варианты и id верного.
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

// ── Слова ─────────────────────────────────────────────────────────────────────────────────────────

/// Реплика дня, где звучит слово, и место слова в ней.
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

  /// Null, если слова нет в репликах.
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

/// `word_listen` (31-5): звук — и варианты-слова цели.
class WordListenPayload extends CardPayload with ChoicePayload {
  const WordListenPayload({required super.sceneId, this.audio, required this.options, required this.correct});

  final CardAudio? audio;
  @override
  final List<CardOption> options;
  @override
  final String correct;

  @override
  Iterable<CardAudio> get audios => [?audio];

  factory WordListenPayload.fromJson(Map<String, dynamic> j) => WordListenPayload(
    sceneId: _scene(j),
    audio: CardAudio.maybe(j['audio']),
    options: _list(j, 'options', CardOption.fromJson),
    correct: _str(j, 'correct'),
  );
}

/// `word_assemble` (31-6).
class WordAssemblePayload extends CardPayload {
  const WordAssemblePayload({required super.sceneId, required this.term, required this.tiles, required this.expected});

  final CardTerm term;
  final List<String> tiles;

  /// Собранное должно совпасть по порядку, слово в слово.
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

/// Строка `word_in_line`: текст цели с `___`, перевод, перевод с пропуском, звук.
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

// ── Фразы ─────────────────────────────────────────────────────────────────────────────────────────

/// Фраза дня, как она сказана в диалоге.
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

  /// Что собрать — предложение на родном.
  final String targetNative;

  /// Слова каркаса и лишние — в нижнем регистре, кроме «I».
  final List<String> tiles;
  final List<CardFiller> chips;
  final List<String> expectedWords;

  /// Место окна в собранной строке.
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

/// `phrase_choose_back` (32-3): фраза на цели — варианты на родном.
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

  /// Какое наполнение звучит.
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

  /// Ключ произнесения — подчёркивается латунью.
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

/// `phrase_other_slot` (32-7): каркас с ДРУГИМ наполнением, заданным на родном.
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

/// Каркас в `phrase_combine`: только текст.
class CardFrameText {
  const CardFrameText({required this.ref, required this.frameTarget, required this.frameNative});

  final String ref;
  final String frameTarget;
  final String frameNative;

  factory CardFrameText.fromJson(Map<String, dynamic> j) =>
      CardFrameText(ref: _str(j, 'ref'), frameTarget: _str(j, 'frame_target'), frameNative: _str(j, 'frame_native'));
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
  Iterable<CardAudio> get audios => [?partnerLine?.audio, ..._fillerAudios(chips)];

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

/// `phrase_own_slot` (32-9): своё окно, зачёт по смыслу — сервер.
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

// ── Диалог ────────────────────────────────────────────────────────────────────────────────────────

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

/// Режимы ответа в диалоге: чипы, голос с подсказкой, голос вслепую.
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

/// `dialogue_answer` (33-2…33-4) и `dialogue_ask` (33-5) — одни и те же ключи.
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

// ── Слушаю и отвечаю ─────────────────────────────────────────────────────────────────────────────

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

/// Где в ленте прозвучал ответ на вопрос.
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

// ── Говорю сам ────────────────────────────────────────────────────────────────────────────────────

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
// Конверт.
// ──────────────────────────────────────────────────────────────────────────────────────────────────

/// Карточка дня — конверт `{id, stage, position, kind, unit, source, source_day, retry_of, payload,
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

  /// `source: returned` — единица вернулась после двух провалов.
  final bool returned;

  /// Номер дня, на котором единица провалилась (только у вернувшейся).
  final int? sourceDay;
  final String? retryOf;
  final CardPayload payload;
  final SessionResult? result;
  final int attempts;

  /// Что осталось от последней попытки — как пришло.
  final Map<String, dynamic>? response;

  /// Провалена дважды — единица вернётся на следующий день.
  final bool returns;

  bool get isAnswered => result != null;

  /// Null — вид этой сборке не знаком: карточку пропускают, запроса о ней нет. Известный вид со
  /// сломанным payload — [SessionContractError].
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
// Разбор.
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

/// Доли и темпы приходят числом с дробной частью (`1.0` / `0.7`), но целое тоже число.
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
