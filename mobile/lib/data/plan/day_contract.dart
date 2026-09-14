/// КАРТОЧКИ ДНЯ — `docs/plan-api.md`, OpenAPI тег `Plans` (наряды PLAN-GEN → DAY-UI).
///
/// Сам план, маршрут, сцены и день читает `plan_models.dart` — он один на таб и на день; окно дня —
/// `day_window.dart`. Здесь ровно то, чего там нет: состав дня карточками (`POST …/open`, `GET
/// …/cards`) и вердикт по карточке. Ни одного поля, которого нет на проводе; то, что сессия считает
/// сама (минуты входа в этап), живёт в `day_rules.dart`.
library;

import 'plan_models.dart';

/// Вид карточки — `PlanCard.kind`. Порядок и состав задаёт сервер; клиент только рендерит.
enum DayCardKind {
  wordIntro('word_intro'),
  wordSay('word_say'),
  wordChoose('word_choose'),
  wordCloze('word_cloze'),
  phraseIntro('phrase_intro'),
  phraseRepeat('phrase_repeat'),
  phraseAssemble('phrase_assemble'),
  dialogueRead('dialogue_read'),
  listenQuestion('listen_question'),
  listenAssemble('listen_assemble'),
  answerChoose('answer_choose'),
  answerAssemble('answer_assemble'),
  speak('speak');

  const DayCardKind(this.wire);
  final String wire;

  static DayCardKind? fromWire(String? v) {
    for (final k in DayCardKind.values) {
      if (k.wire == v) return k;
    }
    return null;
  }

  /// Карточки, которые проходятся микрофоном: у них нет «неверно», только «ещё раз» и «Пропустить».
  bool get isSpoken => this == wordSay || this == phraseRepeat || this == speak;

  /// Карточки, где выбирают вариант (4л).
  bool get isChoice => this == wordChoose || this == wordCloze || this == listenQuestion || this == answerChoose;

  /// Карточки, где собирают из плиток (12b / 23-7e / 23-7f).
  bool get isAssembly => this == phraseAssemble || this == listenAssemble || this == answerAssemble;

  /// Знакомства — без оценки, один выход «Понятно».
  bool get isIntro => this == wordIntro || this == phraseIntro || this == dialogueRead;
}

/// `today` | `returned`.
enum DayCardSource {
  today('today'),
  returned('returned');

  const DayCardSource(this.wire);
  final String wire;

  static DayCardSource fromWire(String? v) => v == returned.wire ? returned : today;
}

/// Результат карточки — то, что клиент присылает и что сервер хранит.
enum DayCardResult {
  passed('passed'),
  hinted('hinted'),
  failed('failed'),
  skipped('skipped');

  const DayCardResult(this.wire);
  final String wire;

  static DayCardResult? fromWire(String? v) {
    for (final r in DayCardResult.values) {
      if (r.wire == v) return r;
    }
    return null;
  }
}

/// Реплика собеседника или ученика внутри пейлоада обмена.
class DayMessage {
  const DayMessage({
    required this.speaker,
    required this.textTarget,
    required this.textNative,
    this.roleTarget,
    this.roleNative,
    this.pronunciationNative,
    this.speakingKey,
    this.audioId,
  });

  /// `A` — собеседник, `B` — ученик.
  final String speaker;
  final String? roleTarget;
  final String? roleNative;
  final String textTarget;
  final String textNative;
  final String? pronunciationNative;
  final String? speakingKey;
  final String? audioId;

  bool get isPartner => speaker == 'A';

  static DayMessage? fromJson(Map<String, dynamic>? j, {String? audioId}) {
    if (j == null) return null;
    return DayMessage(
      speaker: (j['speaker'] as String?) ?? 'A',
      roleTarget: j['role_target'] as String?,
      roleNative: j['role_native'] as String?,
      textTarget: (j['text_target'] as String?) ?? '',
      textNative: (j['text_native'] as String?) ?? '',
      pronunciationNative: j['pronunciation_native'] as String?,
      speakingKey: j['speaking_key'] as String?,
      audioId: audioId ?? j['audio_id'] as String?,
    );
  }
}

/// Один обмен ленты диалога (`dialogue_read`).
class DayExchange {
  const DayExchange({required this.step, required this.initiator, required this.messages, this.audioId});

  final int step;

  /// `A` | `B` — кто начинает.
  final String initiator;
  final List<DayMessage> messages;

  /// Озвучка реплики собеседника этого обмена.
  final String? audioId;

  factory DayExchange.fromJson(Map<String, dynamic> j) {
    final audioId = j['audio_id'] as String?;
    return DayExchange(
      step: (j['step'] as num?)?.toInt() ?? 0,
      initiator: (j['initiator'] as String?) ?? 'A',
      audioId: audioId,
      messages: [
        for (final m in (j['messages'] as List?) ?? const [])
          if (m is Map<String, dynamic>)
            DayMessage.fromJson(m, audioId: (m['speaker'] as String?) == 'A' ? audioId : null)!,
      ],
    );
  }
}

/// Вариант ответа на карточке выбора — `{text, correct}` или `{text_target, text_native, correct}`.
class DayOption {
  const DayOption({required this.text, required this.correct, this.textNative});

  final String text;
  final String? textNative;
  final bool correct;

  factory DayOption.fromJson(Map<String, dynamic> j) => DayOption(
    text: (j['text'] as String?) ?? (j['text_target'] as String?) ?? '',
    textNative: j['text_native'] as String?,
    correct: (j['correct'] as bool?) ?? false,
  );
}

/// Карточка дня — `PlanCard`. Пейлоад разобран в типизированные поля по видам; всё, чего у вида
/// нет, — null или пусто.
class DayCard {
  const DayCard({
    required this.id,
    required this.stage,
    required this.position,
    required this.kind,
    required this.source,
    required this.unitKind,
    required this.unitRef,
    required this.attempts,
    required this.returns,
    required this.payload,
    this.sourceDayId,
    this.retryOf,
    this.result,
  });

  final String id;
  final PlanStage stage;
  final int position;
  final DayCardKind kind;
  final DayCardSource source;
  final String? sourceDayId;
  final PlanUnitKind unitKind;
  final String unitRef;
  final Map<String, dynamic> payload;
  final String? retryOf;
  final DayCardResult? result;
  final int attempts;
  final bool returns;

  static DayCard? fromJson(Map<String, dynamic> j) {
    final kind = DayCardKind.fromWire(j['kind'] as String?);
    // Вид, которого эта сборка не знает, не рендерится вовсе — честнее, чем гадать.
    if (kind == null) return null;
    return DayCard(
      id: (j['id'] as String?) ?? '',
      stage: PlanStage.fromWire(j['stage'] as String?),
      position: (j['position'] as num?)?.toInt() ?? 0,
      kind: kind,
      source: DayCardSource.fromWire(j['source'] as String?),
      sourceDayId: j['source_day_id'] as String?,
      unitKind: PlanUnitKind.fromWire(j['unit_kind'] as String?),
      unitRef: (j['unit_ref'] as String?) ?? '',
      payload: (j['payload'] as Map?)?.cast<String, dynamic>() ?? const {},
      retryOf: j['retry_of'] as String?,
      result: DayCardResult.fromWire(j['result'] as String?),
      attempts: (j['attempts'] as num?)?.toInt() ?? 0,
      returns: (j['returns'] as bool?) ?? false,
    );
  }

  bool get isAnswered => result != null;
  bool get isReturned => source == DayCardSource.returned;

  // ── общие поля пейлоада ──

  String get sceneId => (payload['scene_id'] as String?) ?? '';
  String? get planTermId => payload['plan_term_id'] as String?;
  String get textTarget => (payload['text_target'] as String?) ?? '';
  String get textNative => (payload['text_native'] as String?) ?? '';
  String? get pronunciationNative => payload['pronunciation_native'] as String?;
  PlanImage? get image => PlanImage.fromJson(payload['image'] as Map<String, dynamic>?);
  String? get definitionTarget => payload['definition_target'] as String?;
  String? get exampleTarget => payload['example_target'] as String?;
  String? get exampleNative => payload['example_native'] as String?;
  String? get speakingKey => payload['speaking_key'] as String?;

  /// `word_say` / `phrase_repeat` / `speak` — что должно прозвучать, и порог покрытия.
  String get expected => (payload['expected'] as String?) ?? '';
  double get coverage => (payload['coverage'] as num?)?.toDouble() ?? 1.0;
  List<String> get variants => _strings(payload['variants']);
  String? get hintKey => (payload['hints'] as Map?)?['key'] as String?;
  String? get hintText => (payload['hints'] as Map?)?['text'] as String?;
  String? get taskNative => payload['task_native'] as String?;

  /// `word_choose` — `translation` | `definition`; `listen_question` — `native` | `target`.
  String? get mode => payload['mode'] as String?;
  String? get language => payload['language'] as String?;
  String? get prompt => payload['prompt'] as String?;
  String? get question => payload['question'] as String?;
  String? get explanationNative => payload['explanation_native'] as String?;
  List<DayOption> get options => [
    for (final o in (payload['options'] as List?) ?? const [])
      if (o is Map<String, dynamic>) DayOption.fromJson(o),
  ];

  /// `word_cloze` — предложение с `___` и его перевод.
  String? get sentenceTarget => payload['sentence_target'] as String?;
  String? get sentenceNative => payload['sentence_native'] as String?;

  /// Сборка — ответ и плитки.
  String get answer => (payload['answer'] as String?) ?? '';
  List<String> get tiles => _strings(payload['tiles']);
  String? get promptNative => payload['prompt_native'] as String?;

  /// Обмен — шаг, реплика собеседника, озвучка.
  int? get exchangeStep => (payload['exchange_step'] as num?)?.toInt();
  String? get initiator => payload['initiator'] as String?;
  DayMessage? get partner =>
      DayMessage.fromJson(payload['partner'] as Map<String, dynamic>?, audioId: audioId);
  String? get audioId => payload['audio_id'] as String?;

  /// `dialogue_read`.
  bool get translationsCollapsed => (payload['translations_collapsed'] as bool?) ?? false;
  List<DayExchange> get exchanges => _list(payload['exchanges'], DayExchange.fromJson);
}

/// `GET /plans/{id}/days/{n}/cards` и `POST …/open`.
class DayCards {
  const DayCards({required this.planId, required this.dayId, required this.number, required this.status, required this.cards});

  final String planId;
  final String dayId;
  final int number;
  final PlanDayStatus status;
  final List<DayCard> cards;

  factory DayCards.fromJson(Map<String, dynamic> j) => DayCards(
    planId: (j['plan_id'] as String?) ?? '',
    dayId: (j['day_id'] as String?) ?? '',
    number: (j['number'] as num?)?.toInt() ?? 1,
    status: PlanDayStatus.fromWire(j['status'] as String?),
    cards: [
      for (final c in (j['cards'] as List?) ?? const [])
        if (c is Map<String, dynamic>) ?DayCard.fromJson(c),
    ],
  );
}

/// `POST …/cards/{id}/answer` → карточка и, после первой ошибки, её повтор в конце этапа.
class DayAnswerOutcome {
  const DayAnswerOutcome({required this.card, this.requeued});

  final DayCard card;
  final DayCard? requeued;

  factory DayAnswerOutcome.fromJson(Map<String, dynamic> j) => DayAnswerOutcome(
    card: DayCard.fromJson(j['card'] as Map<String, dynamic>)!,
    requeued: j['requeued'] is Map<String, dynamic> ? DayCard.fromJson(j['requeued'] as Map<String, dynamic>) : null,
  );
}

List<String> _strings(Object? v) =>
    [for (final e in (v as List?) ?? const []) if (e is String && e.trim().isNotEmpty) e];

List<T> _list<T>(Object? v, T Function(Map<String, dynamic>) f) =>
    [for (final e in (v as List?) ?? const []) if (e is Map<String, dynamic>) f(e)];
