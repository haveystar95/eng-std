/// КОНТРАКТ ПЛАНА — `docs/plan-api.md`, OpenAPI тег `Plans` (наряд PLAN-GEN → DAY-UI).
///
/// Сервер отдаёт день ЦЕЛИКОМ: пять этапов, полный список карточек с пейлоадом, состояния,
/// метрики. Клиент ничего не собирает — он читает, показывает и присылает вердикт. Всё здесь —
/// зеркало схем `Plan*` и ничего сверх: ни одного поля, которого нет на проводе, ни одного
/// числа, посчитанного на телефоне (те, что считает клиент, — формат дат и чисел, — живут в UI).
///
/// Имена дневной стороны начинаются с `Day…`, плановой — с `Plan…`: так они не сталкиваются со
/// старыми моделями входа в план, которые ещё живут в `plan_models.dart` до наряда PLAN-UI.
library;

/// `beginner` | `intermediate` — уровень плана. Уровень решает состав дня НА СЕРВЕРЕ; клиенту он
/// нужен только для подписи «Начинающий · Средний» на плите.
enum PlanLevel2 {
  beginner('beginner'),
  intermediate('intermediate');

  const PlanLevel2(this.wire);
  final String wire;

  static PlanLevel2 fromWire(String? v) =>
      v == intermediate.wire ? intermediate : beginner;
}

/// Состояние плана — `Plan.status`.
enum PlanState {
  building('building'),
  unclear('unclear'),
  failed('failed'),
  ready('ready'),
  active('active'),
  finished('finished'),
  overdue('overdue');

  const PlanState(this.wire);
  final String wire;

  static PlanState fromWire(String? v) =>
      PlanState.values.firstWhere((s) => s.wire == v, orElse: () => PlanState.building);
}

/// `locked` | `open` | `in_progress` | `closed` — ЭФФЕКТИВНОЕ состояние дня (сервер уже учёл дату
/// и предыдущий день).
enum DayStatus {
  locked('locked'),
  open('open'),
  inProgress('in_progress'),
  closed('closed');

  const DayStatus(this.wire);
  final String wire;

  static DayStatus fromWire(String? v) =>
      DayStatus.values.firstWhere((s) => s.wire == v, orElse: () => DayStatus.locked);
}

/// Пять этапов дня — в том порядке, в каком их ходят. Названия для экрана — в строках `day.*`.
enum DayStage {
  words('words'),
  phrases('phrases'),
  dialogue('dialogue'),
  listen('listen'),
  speak('speak');

  const DayStage(this.wire);
  final String wire;

  static DayStage fromWire(String? v) =>
      DayStage.values.firstWhere((s) => s.wire == v, orElse: () => DayStage.words);

  /// «Этап N из 5».
  int get ordinal => index + 1;
}

/// `locked` | `current` | `done` | `absent`.
enum DayStageState {
  locked('locked'),
  current('current'),
  done('done'),
  absent('absent');

  const DayStageState(this.wire);
  final String wire;

  static DayStageState fromWire(String? v) =>
      DayStageState.values.firstWhere((s) => s.wire == v, orElse: () => DayStageState.locked);
}

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

/// `word` | `phrase` | `exchange`.
enum DayUnitKind {
  word('word'),
  phrase('phrase'),
  exchange('exchange');

  const DayUnitKind(this.wire);
  final String wire;

  static DayUnitKind fromWire(String? v) =>
      DayUnitKind.values.firstWhere((k) => k.wire == v, orElse: () => DayUnitKind.word);
}

/// `pending` | `passed` | `failed` — состояние единицы программы (маркер 4л в кабинете).
enum DayUnitState {
  pending('pending'),
  passed('passed'),
  failed('failed');

  const DayUnitState(this.wire);
  final String wire;

  static DayUnitState fromWire(String? v) =>
      DayUnitState.values.firstWhere((s) => s.wire == v, orElse: () => DayUnitState.pending);
}

class PlanImage {
  const PlanImage({required this.url, this.author, this.authorUrl});

  final String url;
  final String? author;
  final String? authorUrl;

  static PlanImage? fromJson(Map<String, dynamic>? j) {
    final url = (j?['url'] as String?)?.trim() ?? '';
    if (url.isEmpty) return null;
    return PlanImage(url: url, author: j?['author'] as String?, authorUrl: j?['author_url'] as String?);
  }
}

/// КОГДА день стоит в календаре: `label_native` уже готов для «сегодня»/«завтра», дату форматирует
/// клиент.
class PlanDaySlot {
  const PlanDaySlot({required this.code, this.date, this.labelNative});

  final String code;
  final String? date;
  final String? labelNative;

  factory PlanDaySlot.fromJson(Map<String, dynamic>? j) => PlanDaySlot(
    code: (j?['code'] as String?) ?? 'unscheduled',
    date: j?['date'] as String?,
    labelNative: j?['label_native'] as String?,
  );
}

/// Один день маршрута — `PlanDayRoute`.
class PlanDayRoute {
  const PlanDayRoute({
    required this.id,
    required this.number,
    required this.type,
    required this.status,
    required this.slot,
    required this.cardsTotal,
    required this.cardsDone,
    required this.minutesSpent,
    this.sceneId,
    this.titleNative,
    this.titleTarget,
    this.teachesNative,
    this.lessonStatus,
    this.opensOn,
  });

  final String id;
  final int number;

  /// `scene` | `review` | `rehearsal`.
  final String type;
  final DayStatus status;
  final String? sceneId;
  final String? titleNative;
  final String? titleTarget;
  final String? teachesNative;

  /// `pending` | `building` | `ready` | `failed` | null.
  final String? lessonStatus;
  final String? opensOn;
  final PlanDaySlot slot;
  final int cardsTotal;
  final int cardsDone;
  final int minutesSpent;

  factory PlanDayRoute.fromJson(Map<String, dynamic> j) => PlanDayRoute(
    id: (j['id'] as String?) ?? '',
    number: (j['number'] as num?)?.toInt() ?? 1,
    type: (j['type'] as String?) ?? 'scene',
    status: DayStatus.fromWire(j['status'] as String?),
    sceneId: j['scene_id'] as String?,
    titleNative: j['title_native'] as String?,
    titleTarget: j['title_target'] as String?,
    teachesNative: j['teaches_native'] as String?,
    lessonStatus: j['lesson_status'] as String?,
    opensOn: j['opens_on'] as String?,
    slot: PlanDaySlot.fromJson(j['slot'] as Map<String, dynamic>?),
    cardsTotal: (j['cards_total'] as num?)?.toInt() ?? 0,
    cardsDone: (j['cards_done'] as num?)?.toInt() ?? 0,
    minutesSpent: (j['minutes_spent'] as num?)?.toInt() ?? 0,
  );
}

class PlanScene {
  const PlanScene({
    required this.id,
    required this.titleNative,
    required this.titleTarget,
    required this.teachesNative,
    required this.goalsNative,
    required this.lessonStatus,
    this.learnerRoleNative,
    this.partnerRoleNative,
    this.partnerRoleTarget,
    this.image,
    this.dayNumber,
  });

  final String id;
  final String titleNative;
  final String titleTarget;
  final String teachesNative;
  final List<String> goalsNative;
  final String lessonStatus;
  final String? learnerRoleNative;
  final String? partnerRoleNative;
  final String? partnerRoleTarget;
  final PlanImage? image;
  final int? dayNumber;

  factory PlanScene.fromJson(Map<String, dynamic> j) => PlanScene(
    id: (j['id'] as String?) ?? '',
    titleNative: (j['title_native'] as String?) ?? '',
    titleTarget: (j['title_target'] as String?) ?? '',
    teachesNative: (j['teaches_native'] as String?) ?? '',
    goalsNative: _strings(j['goals_native']),
    lessonStatus: (j['lesson_status'] as String?) ?? 'pending',
    learnerRoleNative: j['learner_role_native'] as String?,
    partnerRoleNative: j['partner_role_native'] as String?,
    partnerRoleTarget: j['partner_role_target'] as String?,
    image: PlanImage.fromJson(j['image'] as Map<String, dynamic>?),
    dayNumber: (j['day_number'] as num?)?.toInt(),
  );
}

class PlanRescuePhrase {
  const PlanRescuePhrase({required this.textTarget, required this.textNative, required this.pronunciationNative});

  final String textTarget;
  final String textNative;
  final String pronunciationNative;

  factory PlanRescuePhrase.fromJson(Map<String, dynamic> j) => PlanRescuePhrase(
    textTarget: (j['text_target'] as String?) ?? '',
    textNative: (j['text_native'] as String?) ?? '',
    pronunciationNative: (j['pronunciation_native'] as String?) ?? '',
  );
}

/// План целиком — `GET /plans/current`, `GET /plans/{id}`.
class Plan {
  const Plan({
    required this.id,
    required this.status,
    required this.goalText,
    required this.targetLang,
    required this.nativeLang,
    required this.level,
    required this.daysTotal,
    required this.days,
    required this.scenes,
    required this.rescueKit,
    required this.routeSummary,
    this.titleNative,
    this.eventNative,
    this.untilPhrase,
    this.overdueNative,
    this.eventDate,
    this.daysLeft,
    this.coverImage,
    this.collectionId,
    this.currentDay,
  });

  final String id;
  final PlanState status;
  final String goalText;
  final String targetLang;
  final String nativeLang;
  final PlanLevel2 level;
  final int daysTotal;
  final String? titleNative;
  final String? eventNative;
  final String? untilPhrase;
  final String? overdueNative;
  final String? eventDate;
  final int? daysLeft;
  final String routeSummary;
  final PlanImage? coverImage;
  final String? collectionId;
  final PlanDayRoute? currentDay;
  final List<PlanDayRoute> days;
  final List<PlanScene> scenes;
  final List<PlanRescuePhrase> rescueKit;

  factory Plan.fromJson(Map<String, dynamic> j) => Plan(
    id: (j['id'] as String?) ?? '',
    status: PlanState.fromWire(j['status'] as String?),
    goalText: (j['goal_text'] as String?) ?? '',
    targetLang: (j['target_lang'] as String?) ?? 'en',
    nativeLang: (j['native_lang'] as String?) ?? 'ru',
    level: PlanLevel2.fromWire(j['level'] as String?),
    daysTotal: (j['days_total'] as num?)?.toInt() ?? 0,
    titleNative: j['title_native'] as String?,
    eventNative: j['event_native'] as String?,
    untilPhrase: j['until_phrase'] as String?,
    overdueNative: j['overdue_native'] as String?,
    eventDate: j['event_date'] as String?,
    daysLeft: (j['days_left'] as num?)?.toInt(),
    routeSummary: (j['route_summary'] as String?) ?? '',
    coverImage: PlanImage.fromJson(j['cover_image'] as Map<String, dynamic>?),
    collectionId: j['collection_id'] as String?,
    currentDay: j['current_day'] is Map<String, dynamic>
        ? PlanDayRoute.fromJson(j['current_day'] as Map<String, dynamic>)
        : null,
    days: _list(j['days'], PlanDayRoute.fromJson),
    scenes: _list(j['scenes'], PlanScene.fromJson),
    rescueKit: _list(j['rescue_kit'], PlanRescuePhrase.fromJson),
  );

  PlanScene? sceneById(String? id) {
    if (id == null) return null;
    for (final s in scenes) {
      if (s.id == id) return s;
    }
    return null;
  }
}

/// Прогресс одного этапа в кабинете — `PlanStageProgress`.
class DayStageProgress {
  const DayStageProgress({required this.stage, required this.total, required this.done, required this.state});

  final DayStage stage;
  final int total;
  final int done;
  final DayStageState state;

  factory DayStageProgress.fromJson(Map<String, dynamic> j) => DayStageProgress(
    stage: DayStage.fromWire(j['stage'] as String?),
    total: (j['total'] as num?)?.toInt() ?? 0,
    done: (j['done'] as num?)?.toInt() ?? 0,
    state: DayStageState.fromWire(j['state'] as String?),
  );

  int get remaining => (total - done).clamp(0, total);
}

/// Единица программы дня — `PlanProgramUnit`.
class DayUnit {
  const DayUnit({
    required this.unitKind,
    required this.unitRef,
    required this.sceneId,
    required this.source,
    required this.cardsTotal,
    required this.cardsDone,
    required this.state,
    this.textTarget,
    this.textNative,
  });

  final DayUnitKind unitKind;
  final String unitRef;
  final String sceneId;
  final String? textTarget;
  final String? textNative;
  final DayCardSource source;
  final int cardsTotal;
  final int cardsDone;
  final DayUnitState state;

  factory DayUnit.fromJson(Map<String, dynamic> j) => DayUnit(
    unitKind: DayUnitKind.fromWire(j['unit_kind'] as String?),
    unitRef: (j['unit_ref'] as String?) ?? '',
    sceneId: (j['scene_id'] as String?) ?? '',
    textTarget: j['text_target'] as String?,
    textNative: j['text_native'] as String?,
    source: DayCardSource.fromWire(j['source'] as String?),
    cardsTotal: (j['cards_total'] as num?)?.toInt() ?? 0,
    cardsDone: (j['cards_done'] as num?)?.toInt() ?? 0,
    state: DayUnitState.fromWire(j['state'] as String?),
  );
}

/// Метрики закрытого дня — `PlanDayMetrics`. Считает СЕРВЕР; клиент только форматирует.
class DayMetrics {
  const DayMetrics({
    required this.cardsTotal,
    required this.cardsDone,
    required this.minutesSpent,
    this.firstTryShare,
    this.hardestUnitKind,
    this.hardestUnitRef,
    this.hardestUnitText,
  });

  final int cardsTotal;
  final int cardsDone;
  final int minutesSpent;
  final double? firstTryShare;
  final DayUnitKind? hardestUnitKind;
  final String? hardestUnitRef;
  final String? hardestUnitText;

  factory DayMetrics.fromJson(Map<String, dynamic> j) => DayMetrics(
    cardsTotal: (j['cards_total'] as num?)?.toInt() ?? 0,
    cardsDone: (j['cards_done'] as num?)?.toInt() ?? 0,
    minutesSpent: (j['minutes_spent'] as num?)?.toInt() ?? 0,
    firstTryShare: (j['first_try_share'] as num?)?.toDouble(),
    hardestUnitKind: j['hardest_unit_kind'] == null
        ? null
        : DayUnitKind.fromWire(j['hardest_unit_kind'] as String?),
    hardestUnitRef: j['hardest_unit_ref'] as String?,
    hardestUnitText: j['hardest_unit_text'] as String?,
  );

  /// «84 %» — процент с первого раза, округлённый; null без оценённых карточек.
  int? get firstTryPercent => firstTryShare == null ? null : (firstTryShare! * 100).round();
}

/// «Кабинет дня» — `GET /plans/{id}/days/{n}`.
class DayRoom {
  const DayRoom({
    required this.planId,
    required this.day,
    required this.goalsNative,
    required this.stages,
    required this.program,
    required this.sheetAvailable,
    this.scene,
    this.metrics,
  });

  final String planId;
  final PlanDayRoute day;
  final PlanScene? scene;
  final List<String> goalsNative;
  final List<DayStageProgress> stages;
  final DayMetrics? metrics;
  final List<DayUnit> program;
  final bool sheetAvailable;

  factory DayRoom.fromJson(Map<String, dynamic> j) => DayRoom(
    planId: (j['plan_id'] as String?) ?? '',
    day: PlanDayRoute.fromJson(j['day'] as Map<String, dynamic>),
    scene: j['scene'] is Map<String, dynamic> ? PlanScene.fromJson(j['scene'] as Map<String, dynamic>) : null,
    goalsNative: _strings(j['goals_native']),
    stages: _list(j['stages'], DayStageProgress.fromJson),
    metrics: j['metrics'] is Map<String, dynamic> ? DayMetrics.fromJson(j['metrics'] as Map<String, dynamic>) : null,
    program: _list(j['program'], DayUnit.fromJson),
    sheetAvailable: (j['sheet_available'] as bool?) ?? false,
  );

  DayStageProgress? stage(DayStage stage) {
    for (final s in stages) {
      if (s.stage == stage) return s;
    }
    return null;
  }

  /// Этап, на котором стоит день, — первый `current`.
  DayStageProgress? get currentStage {
    for (final s in stages) {
      if (s.state == DayStageState.current) return s;
    }
    return null;
  }

  int get cardsTotal => stages.fold(0, (n, s) => n + s.total);
  int get cardsDone => stages.fold(0, (n, s) => n + s.done);
  int get cardsRemaining => cardsTotal - cardsDone;

  Iterable<DayUnit> get words => program.where((u) => u.unitKind == DayUnitKind.word);
  Iterable<DayUnit> get phrases => program.where((u) => u.unitKind == DayUnitKind.phrase);
  Iterable<DayUnit> get exchanges => program.where((u) => u.unitKind == DayUnitKind.exchange);
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
  final DayStage stage;
  final int position;
  final DayCardKind kind;
  final DayCardSource source;
  final String? sourceDayId;
  final DayUnitKind unitKind;
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
      stage: DayStage.fromWire(j['stage'] as String?),
      position: (j['position'] as num?)?.toInt() ?? 0,
      kind: kind,
      source: DayCardSource.fromWire(j['source'] as String?),
      sourceDayId: j['source_day_id'] as String?,
      unitKind: DayUnitKind.fromWire(j['unit_kind'] as String?),
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
  final DayStatus status;
  final List<DayCard> cards;

  factory DayCards.fromJson(Map<String, dynamic> j) => DayCards(
    planId: (j['plan_id'] as String?) ?? '',
    dayId: (j['day_id'] as String?) ?? '',
    number: (j['number'] as num?)?.toInt() ?? 1,
    status: DayStatus.fromWire(j['status'] as String?),
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

/// Термин шита — `PlanTerm` (`GET …/sheet`).
class DayTerm {
  const DayTerm({
    required this.id,
    required this.sceneId,
    required this.kind,
    required this.ref,
    required this.textTarget,
    required this.textNative,
    required this.simplifiedVariants,
    this.pronunciationNative,
    this.definitionTarget,
    this.exampleTarget,
    this.exampleNative,
    this.speakingKey,
    this.image,
  });

  final String id;
  final String sceneId;

  /// `word` | `chunk` | `phrase`.
  final String kind;
  final String ref;
  final String textTarget;
  final String textNative;
  final String? pronunciationNative;
  final String? definitionTarget;
  final String? exampleTarget;
  final String? exampleNative;
  final String? speakingKey;
  final List<String> simplifiedVariants;
  final PlanImage? image;

  factory DayTerm.fromJson(Map<String, dynamic> j) => DayTerm(
    id: (j['id'] as String?) ?? '',
    sceneId: (j['scene_id'] as String?) ?? '',
    kind: (j['kind'] as String?) ?? 'word',
    ref: (j['ref'] as String?) ?? '',
    textTarget: (j['text_target'] as String?) ?? '',
    textNative: (j['text_native'] as String?) ?? '',
    pronunciationNative: j['pronunciation_native'] as String?,
    definitionTarget: j['definition_target'] as String?,
    exampleTarget: j['example_target'] as String?,
    exampleNative: j['example_native'] as String?,
    speakingKey: j['speaking_key'] as String?,
    simplifiedVariants: _strings(j['simplified_variants']),
    image: PlanImage.fromJson(j['image'] as Map<String, dynamic>?),
  );
}

class DaySheet {
  const DaySheet({required this.planId, required this.number, required this.words, required this.phrases});

  final String planId;
  final int number;
  final List<DayTerm> words;
  final List<DayTerm> phrases;

  factory DaySheet.fromJson(Map<String, dynamic> j) => DaySheet(
    planId: (j['plan_id'] as String?) ?? '',
    number: (j['number'] as num?)?.toInt() ?? 1,
    words: _list(j['words'], DayTerm.fromJson),
    phrases: _list(j['phrases'], DayTerm.fromJson),
  );

  DayTerm? byRef(String ref) {
    for (final t in [...words, ...phrases]) {
      if (t.ref == ref) return t;
    }
    return null;
  }
}

List<String> _strings(Object? v) =>
    [for (final e in (v as List?) ?? const []) if (e is String && e.trim().isNotEmpty) e];

List<T> _list<T>(Object? v, T Function(Map<String, dynamic>) f) =>
    [for (final e in (v as List?) ?? const []) if (e is Map<String, dynamic>) f(e)];
