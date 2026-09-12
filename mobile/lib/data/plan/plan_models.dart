/// THE PLAN AS THE SERVER SENDS IT — `docs/plan-api.md`, OpenAPI tag `Plans`, наряд PLAN-UI.
///
/// Read models only, one per wire schema, snake_case in and Dart out. Nothing here is derived:
/// the days, the slots, the countdown phrase, the route summary and every string that inflects
/// the event arrive READY from the server («Что клиенту НЕ надо считать»). What the client owns is
/// the formatting of dates and numbers, and that lives beside the widgets, not here.
///
/// Every enum reads the wire as an OPEN set: a code this build has never heard of falls into an
/// `unknown` member rather than throwing, so a newer server cannot take the tab down.
library;

/// `Plan.status` / `PlanBuild.status` / `PlanSummary.status`.
enum PlanStatus {
  building,
  unclear,
  failed,
  ready,
  active,
  finished,
  overdue,
  unknown;

  static PlanStatus fromWire(String? s) => switch (s) {
    'building' => building,
    'unclear' => unclear,
    'failed' => failed,
    'ready' => ready,
    'active' => active,
    'finished' => finished,
    'overdue' => overdue,
    _ => unknown,
  };

  /// The plan the tab shows as «идёт» — active, or active and past its date.
  bool get isLive => this == active || this == overdue;
}

/// `PlanDayRoute.type`.
enum PlanDayType {
  scene,
  review,
  rehearsal,
  unknown;

  static PlanDayType fromWire(String? s) => switch (s) {
    'scene' => scene,
    'review' => review,
    'rehearsal' => rehearsal,
    _ => unknown,
  };
}

/// `PlanDayRoute.status` — EFFECTIVE for today, the server says.
enum PlanDayStatus {
  locked,
  open,
  inProgress,
  closed,
  unknown;

  static PlanDayStatus fromWire(String? s) => switch (s) {
    'locked' => locked,
    'open' => open,
    'in_progress' => inProgress,
    'closed' => closed,
    _ => unknown,
  };
}

/// `PlanDaySlot.code` — WHEN a day sits in the learner's calendar.
enum PlanSlotCode {
  today,
  tomorrow,
  date,
  past,
  unscheduled,
  unknown;

  static PlanSlotCode fromWire(String? s) => switch (s) {
    'today' => today,
    'tomorrow' => tomorrow,
    'date' => date,
    'past' => past,
    'unscheduled' => unscheduled,
    _ => unknown,
  };
}

/// `Plan.level` — the two the entry offers (кадр 22-2).
enum PlanLevel {
  beginner,
  intermediate;

  String get wire => name;

  static PlanLevel fromWire(String? s) => s == 'intermediate' ? intermediate : beginner;
}

/// `PlanScene.lesson_status` / `PlanDayRoute.lesson_status`.
enum LessonStatus {
  pending,
  building,
  ready,
  failed,
  unknown;

  static LessonStatus? fromWire(String? s) => switch (s) {
    null => null,
    'pending' => pending,
    'building' => building,
    'ready' => ready,
    'failed' => failed,
    _ => unknown,
  };
}

/// `PlanStageProgress.stage` — the five stages of a day, in their order (плита 4н).
enum PlanStage {
  words,
  phrases,
  dialogue,
  listen,
  speak,
  unknown;

  static PlanStage fromWire(String? s) => switch (s) {
    'words' => words,
    'phrases' => phrases,
    'dialogue' => dialogue,
    'listen' => listen,
    'speak' => speak,
    _ => unknown,
  };

  /// Имя этапа на проводе — им день закрывает этап (`POST …/stages/{stage}/close`, наряд DAY-UI).
  String get wire => name;

  /// «Этап N из 5» на входе в этап (кадр 23-2a). У [unknown] номера нет — этап, которого эта
  /// сборка не знает, в шапке не считается.
  int get ordinal => index + 1;

  /// Пять этапов дня в порядке хода, без «неизвестного».
  static List<PlanStage> get known =>
      PlanStage.values.where((s) => s != PlanStage.unknown).toList();
}

/// `PlanStageProgress.state`.
enum PlanStageState {
  locked,
  current,
  done,
  absent,
  unknown;

  static PlanStageState fromWire(String? s) => switch (s) {
    'locked' => locked,
    'current' => current,
    'done' => done,
    'absent' => absent,
    _ => unknown,
  };
}

/// `PlanProgramUnit.unit_kind`.
enum PlanUnitKind {
  word,
  phrase,
  exchange,
  unknown;

  static PlanUnitKind fromWire(String? s) => switch (s) {
    'word' => word,
    'phrase' => phrase,
    'exchange' => exchange,
    _ => unknown,
  };
}

/// `PlanProgramUnit.state`.
enum PlanUnitState {
  pending,
  passed,
  failed,
  unknown;

  static PlanUnitState fromWire(String? s) => switch (s) {
    'pending' => pending,
    'passed' => passed,
    'failed' => failed,
    _ => unknown,
  };
}

/// `PlanProgramUnit.source`.
enum PlanUnitSource {
  today,
  returned,
  unknown;

  static PlanUnitSource fromWire(String? s) => switch (s) {
    'today' => today,
    'returned' => returned,
    _ => unknown,
  };
}

/// A stock photo with the credit its licence asks for (`PlanImage`).
class PlanImage {
  const PlanImage({required this.url, this.author, this.authorUrl});

  final String url;
  final String? author;
  final String? authorUrl;

  static PlanImage? fromJson(Map<String, dynamic>? j) {
    final url = j?['url'];
    if (url is! String || url.isEmpty) return null;

    return PlanImage(
      url: url,
      author: j!['author'] as String?,
      authorUrl: j['author_url'] as String?,
    );
  }
}

/// `PlanVersions` — which server build and which prompt files answered.
class PlanVersions {
  const PlanVersions({required this.build, required this.promptPlan, required this.promptLesson});

  final String build;
  final String promptPlan;
  final String promptLesson;

  factory PlanVersions.fromJson(Map<String, dynamic>? j) => PlanVersions(
    build: (j?['build'] as String?) ?? '',
    promptPlan: (j?['prompt_plan'] as String?) ?? '',
    promptLesson: (j?['prompt_lesson'] as String?) ?? '',
  );

  /// «server abc1234 · plan-builder-v2 · lesson-v3» — what the day stub prints as the contract.
  String get line => [build, promptPlan, promptLesson].where((s) => s.isNotEmpty).join(' · ');
}

/// `PlanBuild` — what the client polls after `POST /plans`.
class PlanBuild {
  const PlanBuild({
    required this.id,
    required this.status,
    required this.scenesCount,
    required this.versions,
    this.unclearReason,
    this.failReason,
  });

  final String id;
  final PlanStatus status;
  final int scenesCount;
  final PlanVersions versions;
  final String? unclearReason;
  final String? failReason;

  factory PlanBuild.fromJson(Map<String, dynamic> j) => PlanBuild(
    id: j['id'] as String,
    status: PlanStatus.fromWire(j['status'] as String?),
    scenesCount: (j['scenes_count'] as num?)?.toInt() ?? 0,
    versions: PlanVersions.fromJson(j['versions'] as Map<String, dynamic>?),
    unclearReason: j['unclear_reason'] as String?,
    failReason: j['fail_reason'] as String?,
  );

  /// Still being asked of the model — keep polling.
  bool get isBuilding => status == PlanStatus.building;
}

/// `PlanSummary` — one row of `GET /plans` (the «Завершённые планы» list reads it).
class PlanRow {
  const PlanRow({
    required this.id,
    required this.status,
    required this.goalText,
    required this.daysTotal,
    required this.createdAt,
    this.titleNative,
    this.eventDate,
    this.collectionId,
    this.finishedAt,
  });

  final String id;
  final PlanStatus status;
  final String goalText;
  final int daysTotal;
  final DateTime createdAt;
  final String? titleNative;
  final String? eventDate;
  final String? collectionId;
  final DateTime? finishedAt;

  factory PlanRow.fromJson(Map<String, dynamic> j) => PlanRow(
    id: j['id'] as String,
    status: PlanStatus.fromWire(j['status'] as String?),
    goalText: (j['goal_text'] as String?) ?? '',
    daysTotal: (j['days_total'] as num?)?.toInt() ?? 0,
    createdAt: DateTime.tryParse((j['created_at'] as String?) ?? '') ?? DateTime(1970),
    titleNative: j['title_native'] as String?,
    eventDate: j['event_date'] as String?,
    collectionId: j['collection_id'] as String?,
    finishedAt: DateTime.tryParse((j['finished_at'] as String?) ?? ''),
  );

  /// What the list row prints — the plan's name when it has one, the goal otherwise.
  String get title => (titleNative ?? '').trim().isNotEmpty ? titleNative!.trim() : goalText;
}

/// `PlanDaySlot` — the right slot of a route row. `labelNative` is READY for `today` / `tomorrow`;
/// a `date` slot is formatted by the client; `past` is the day it was closed.
class PlanDaySlot {
  const PlanDaySlot({required this.code, this.date, this.labelNative});

  final PlanSlotCode code;

  /// `YYYY-MM-DD` in the learner's calendar, or null.
  final String? date;
  final String? labelNative;

  factory PlanDaySlot.fromJson(Map<String, dynamic>? j) => PlanDaySlot(
    code: PlanSlotCode.fromWire(j?['code'] as String?),
    date: j?['date'] as String?,
    labelNative: j?['label_native'] as String?,
  );
}

/// `PlanDayRoute` — one day on the route.
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
    this.closedAt,
  });

  final String id;
  final int number;
  final PlanDayType type;
  final PlanDayStatus status;
  final PlanDaySlot slot;
  final int cardsTotal;
  final int cardsDone;
  final int minutesSpent;
  final String? sceneId;
  final String? titleNative;
  final String? titleTarget;
  final String? teachesNative;
  final LessonStatus? lessonStatus;
  final String? opensOn;
  final DateTime? closedAt;

  factory PlanDayRoute.fromJson(Map<String, dynamic> j) => PlanDayRoute(
    id: j['id'] as String,
    number: (j['number'] as num?)?.toInt() ?? 0,
    type: PlanDayType.fromWire(j['type'] as String?),
    status: PlanDayStatus.fromWire(j['status'] as String?),
    slot: PlanDaySlot.fromJson(j['slot'] as Map<String, dynamic>?),
    cardsTotal: (j['cards_total'] as num?)?.toInt() ?? 0,
    cardsDone: (j['cards_done'] as num?)?.toInt() ?? 0,
    minutesSpent: (j['minutes_spent'] as num?)?.toInt() ?? 0,
    sceneId: j['scene_id'] as String?,
    titleNative: j['title_native'] as String?,
    titleTarget: j['title_target'] as String?,
    teachesNative: j['teaches_native'] as String?,
    lessonStatus: LessonStatus.fromWire(j['lesson_status'] as String?),
    opensOn: j['opens_on'] as String?,
    closedAt: DateTime.tryParse((j['closed_at'] as String?) ?? ''),
  );

  bool get isClosed => status == PlanDayStatus.closed;
  bool get isInProgress => status == PlanDayStatus.inProgress;

  /// A day whose lesson the server is still writing (кадр 22-5a), or failed to (22-5c).
  bool get lessonBuilding =>
      lessonStatus == LessonStatus.building || lessonStatus == LessonStatus.pending;
  bool get lessonFailed => lessonStatus == LessonStatus.failed;
}

/// `PlanScene` — the model's brief for one situation.
class PlanScene {
  const PlanScene({
    required this.id,
    required this.order,
    required this.priority,
    required this.titleNative,
    required this.titleTarget,
    required this.teachesNative,
    required this.goalsNative,
    required this.lessonStatus,
    this.learnerRoleNative,
    this.partnerRoleNative,
    this.image,
    this.dayNumber,
    this.lessonFailReason,
  });

  final String id;
  final int order;

  /// 1 is the core — the scene that is never dropped (409 `plan_core_scene`).
  final int priority;
  final String titleNative;
  final String titleTarget;
  final String teachesNative;
  final List<String> goalsNative;
  final LessonStatus lessonStatus;

  /// Кто в сцене ученик и кто собеседник — «ВРАЧ ГОВОРИТ» над репликой и «ТЫ» в диалоге
  /// (наряд DAY-UI, кадры 23-6 … 23-8). Отдаёт сервер: роль принадлежит сцене, не экрану.
  final String? learnerRoleNative;
  final String? partnerRoleNative;
  final PlanImage? image;
  final int? dayNumber;
  final String? lessonFailReason;

  factory PlanScene.fromJson(Map<String, dynamic> j) => PlanScene(
    id: j['id'] as String,
    order: (j['order'] as num?)?.toInt() ?? 0,
    priority: (j['priority'] as num?)?.toInt() ?? 0,
    titleNative: (j['title_native'] as String?) ?? '',
    titleTarget: (j['title_target'] as String?) ?? '',
    teachesNative: (j['teaches_native'] as String?) ?? '',
    goalsNative: [for (final g in (j['goals_native'] as List?) ?? const []) g.toString()],
    learnerRoleNative: j['learner_role_native'] as String?,
    partnerRoleNative: j['partner_role_native'] as String?,
    lessonStatus: LessonStatus.fromWire(j['lesson_status'] as String?) ?? LessonStatus.unknown,
    image: PlanImage.fromJson(j['image'] as Map<String, dynamic>?),
    dayNumber: (j['day_number'] as num?)?.toInt(),
    lessonFailReason: j['lesson_fail_reason'] as String?,
  );

  bool get isCore => priority == 1;
}

/// `PlanRescuePhrase` — one of the five (кадр 21-2b).
class PlanRescuePhrase {
  const PlanRescuePhrase({
    required this.textTarget,
    required this.textNative,
    required this.pronunciationNative,
  });

  final String textTarget;
  final String textNative;
  final String pronunciationNative;

  factory PlanRescuePhrase.fromJson(Map<String, dynamic> j) => PlanRescuePhrase(
    textTarget: (j['text_target'] as String?) ?? '',
    textNative: (j['text_native'] as String?) ?? '',
    pronunciationNative: (j['pronunciation_native'] as String?) ?? '',
  );
}

/// `Plan` — the plan, whole: tab and preview alike.
class Plan {
  const Plan({
    required this.id,
    required this.status,
    required this.goalText,
    required this.targetLang,
    required this.nativeLang,
    required this.level,
    required this.daysTotal,
    required this.daysRequested,
    required this.routeSummary,
    required this.days,
    required this.scenes,
    required this.rescueKit,
    required this.versions,
    required this.raw,
    this.daysShortenedFrom,
    this.eventDate,
    this.daysLeft,
    this.titleNative,
    this.titleTarget,
    this.eventNative,
    this.untilPhrase,
    this.overdueNative,
    this.coverImage,
    this.collectionId,
    this.unclearReason,
    this.failReason,
    this.currentDay,
    this.startedAt,
    this.finishedAt,
  });

  final String id;
  final PlanStatus status;
  final String goalText;
  final String targetLang;
  final String nativeLang;
  final PlanLevel level;
  final int daysTotal;
  final int daysRequested;
  final int? daysShortenedFrom;
  final String? eventDate;
  final int? daysLeft;
  final String? titleNative;
  final String? titleTarget;
  final String? eventNative;

  /// «До приёма · 5 дней» — READY; null without a date or once the event has passed.
  final String? untilPhrase;

  /// «Приём был вчера» — present only while the plan is overdue.
  final String? overdueNative;

  /// «5 дней · 3 ситуации, 1 повторение, репетиция» — READY.
  final String routeSummary;
  final PlanImage? coverImage;
  final String? collectionId;
  final String? unclearReason;
  final String? failReason;

  /// The first day that is not closed; null when every day is closed.
  final PlanDayRoute? currentDay;
  final List<PlanDayRoute> days;
  final List<PlanScene> scenes;
  final List<PlanRescuePhrase> rescueKit;
  final PlanVersions versions;
  final DateTime? startedAt;
  final DateTime? finishedAt;

  /// The JSON as it arrived — what the tab caches for the offline read.
  final Map<String, dynamic> raw;

  factory Plan.fromJson(Map<String, dynamic> j) => Plan(
    id: j['id'] as String,
    status: PlanStatus.fromWire(j['status'] as String?),
    goalText: (j['goal_text'] as String?) ?? '',
    targetLang: (j['target_lang'] as String?) ?? '',
    nativeLang: (j['native_lang'] as String?) ?? '',
    level: PlanLevel.fromWire(j['level'] as String?),
    daysTotal: (j['days_total'] as num?)?.toInt() ?? 0,
    daysRequested: (j['days_requested'] as num?)?.toInt() ?? 0,
    daysShortenedFrom: (j['days_shortened_from'] as num?)?.toInt(),
    eventDate: j['event_date'] as String?,
    daysLeft: (j['days_left'] as num?)?.toInt(),
    titleNative: j['title_native'] as String?,
    titleTarget: j['title_target'] as String?,
    eventNative: j['event_native'] as String?,
    untilPhrase: j['until_phrase'] as String?,
    overdueNative: j['overdue_native'] as String?,
    routeSummary: (j['route_summary'] as String?) ?? '',
    coverImage: PlanImage.fromJson(j['cover_image'] as Map<String, dynamic>?),
    collectionId: j['collection_id'] as String?,
    unclearReason: j['unclear_reason'] as String?,
    failReason: j['fail_reason'] as String?,
    currentDay: j['current_day'] is Map<String, dynamic>
        ? PlanDayRoute.fromJson(j['current_day'] as Map<String, dynamic>)
        : null,
    days: [
      for (final d in (j['days'] as List?) ?? const [])
        if (d is Map<String, dynamic>) PlanDayRoute.fromJson(d),
    ],
    scenes: [
      for (final s in (j['scenes'] as List?) ?? const [])
        if (s is Map<String, dynamic>) PlanScene.fromJson(s),
    ],
    rescueKit: [
      for (final r in (j['rescue_kit'] as List?) ?? const [])
        if (r is Map<String, dynamic>) PlanRescuePhrase.fromJson(r),
    ],
    versions: PlanVersions.fromJson(j['versions'] as Map<String, dynamic>?),
    startedAt: DateTime.tryParse((j['started_at'] as String?) ?? ''),
    finishedAt: DateTime.tryParse((j['finished_at'] as String?) ?? ''),
    raw: j,
  );

  /// The scene a route day is about, when it has one.
  PlanScene? sceneOf(PlanDayRoute day) {
    final id = day.sceneId;
    if (id == null) return null;
    for (final s in scenes) {
      if (s.id == id) return s;
    }

    return null;
  }

  /// The name the plan's collection carries — the plan's own title.
  /// Сцена по id — день знает только `scene_id` своей карточки (наряд DAY-UI).
  PlanScene? sceneById(String? id) {
    if (id == null) return null;
    for (final s in scenes) {
      if (s.id == id) return s;
    }

    return null;
  }

  String get displayTitle => (titleNative ?? '').trim().isNotEmpty ? titleNative!.trim() : goalText;

  /// КОРОТКОЕ НАЗВАНИЕ ПЛАНА для шапки (кадр 21-2, Literata 30) — или null, и тогда шапка берёт
  /// фолбэк 21-6 (формулировка человека Inter 21/600 в три строки).
  ///
  /// Отличается от [displayTitle] ровно этим: там пустое название молча подменяется целью и
  /// рисуется теми же 30 Literata, а шапке нужно ЗНАТЬ, что названия нет, — у фолбэка свой кегль.
  String? get shortTitle {
    final t = (titleNative ?? '').trim();

    return t.isEmpty ? null : t;
  }

  /// How many days are closed — what the header's progress line and «День N из M» read.
  int get closedDays => days.where((d) => d.isClosed).length;

  /// Every day closed and nothing left to open.
  bool get allDaysClosed => days.isNotEmpty && currentDay == null;
}

/// `PlanStageProgress` — one stage of the day room, with its count (плита 4н).
class PlanStageProgress {
  const PlanStageProgress({
    required this.stage,
    required this.total,
    required this.done,
    required this.state,
  });

  final PlanStage stage;
  final int total;
  final int done;
  final PlanStageState state;

  factory PlanStageProgress.fromJson(Map<String, dynamic> j) => PlanStageProgress(
    stage: PlanStage.fromWire(j['stage'] as String?),
    total: (j['total'] as num?)?.toInt() ?? 0,
    done: (j['done'] as num?)?.toInt() ?? 0,
    state: PlanStageState.fromWire(j['state'] as String?),
  );
}

/// `PlanProgramUnit` — one unit of the day's program and where it stands.
class PlanProgramUnit {
  const PlanProgramUnit({
    required this.unitKind,
    required this.unitRef,
    this.sceneId = '',
    required this.source,
    required this.state,
    required this.cardsTotal,
    required this.cardsDone,
    this.textTarget,
    this.textNative,
  });

  final PlanUnitKind unitKind;
  final String unitRef;

  /// Сцена, которой единица принадлежит, — по ней шит находит термин (наряд DAY-UI).
  final String sceneId;
  final PlanUnitSource source;
  final PlanUnitState state;
  final int cardsTotal;
  final int cardsDone;
  final String? textTarget;
  final String? textNative;

  factory PlanProgramUnit.fromJson(Map<String, dynamic> j) => PlanProgramUnit(
    unitKind: PlanUnitKind.fromWire(j['unit_kind'] as String?),
    unitRef: (j['unit_ref'] as String?) ?? '',
    sceneId: (j['scene_id'] as String?) ?? '',
    source: PlanUnitSource.fromWire(j['source'] as String?),
    state: PlanUnitState.fromWire(j['state'] as String?),
    cardsTotal: (j['cards_total'] as num?)?.toInt() ?? 0,
    cardsDone: (j['cards_done'] as num?)?.toInt() ?? 0,
    textTarget: j['text_target'] as String?,
    textNative: j['text_native'] as String?,
  );
}

/// `PlanDayMetrics` — filled once the day is closed.
class PlanDayMetrics {
  const PlanDayMetrics({
    required this.cardsTotal,
    required this.cardsDone,
    required this.minutesSpent,
    this.firstTryShare,
    this.hardestUnitText,
    this.hardestUnitKind,
    this.hardestUnitRef,
  });

  final int cardsTotal;
  final int cardsDone;
  final int minutesSpent;
  final double? firstTryShare;
  final String? hardestUnitText;

  /// Какая именно единица далась труднее всего — по ней кабинет считает попытки (23-0c).
  final PlanUnitKind? hardestUnitKind;
  final String? hardestUnitRef;

  factory PlanDayMetrics.fromJson(Map<String, dynamic> j) => PlanDayMetrics(
    cardsTotal: (j['cards_total'] as num?)?.toInt() ?? 0,
    cardsDone: (j['cards_done'] as num?)?.toInt() ?? 0,
    minutesSpent: (j['minutes_spent'] as num?)?.toInt() ?? 0,
    firstTryShare: (j['first_try_share'] as num?)?.toDouble(),
    hardestUnitText: j['hardest_unit_text'] as String?,
    hardestUnitKind: j['hardest_unit_kind'] == null
        ? null
        : PlanUnitKind.fromWire(j['hardest_unit_kind'] as String?),
    hardestUnitRef: j['hardest_unit_ref'] as String?,
  );
}

/// `PlanDayRoom` — «Кабинет дня»: the tab reads it for the plate's stage rows (кадр 21-2) and
/// the closed-day card (21-4); the room itself is DAY-UI's.
class PlanDayRoom {
  const PlanDayRoom({
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
  final List<PlanStageProgress> stages;
  final PlanDayMetrics? metrics;
  final List<PlanProgramUnit> program;
  final bool sheetAvailable;

  factory PlanDayRoom.fromJson(Map<String, dynamic> j) => PlanDayRoom(
    planId: (j['plan_id'] as String?) ?? '',
    day: PlanDayRoute.fromJson(j['day'] as Map<String, dynamic>),
    scene: j['scene'] is Map<String, dynamic>
        ? PlanScene.fromJson(j['scene'] as Map<String, dynamic>)
        : null,
    goalsNative: [for (final g in (j['goals_native'] as List?) ?? const []) g.toString()],
    stages: [
      for (final s in (j['stages'] as List?) ?? const [])
        if (s is Map<String, dynamic>) PlanStageProgress.fromJson(s),
    ],
    metrics: j['metrics'] is Map<String, dynamic>
        ? PlanDayMetrics.fromJson(j['metrics'] as Map<String, dynamic>)
        : null,
    program: [
      for (final u in (j['program'] as List?) ?? const [])
        if (u is Map<String, dynamic>) PlanProgramUnit.fromJson(u),
    ],
    sheetAvailable: j['sheet_available'] == true,
  );

  /// Today's own NEW words — «начни отсюда · 8 новых слов» (plan.plate.stage.sub.start).
  int get newWordsCount => program
      .where((u) => u.unitKind == PlanUnitKind.word && u.source == PlanUnitSource.today)
      .length;

  /// Units that failed twice and RETURN on the next content day — «Вернутся в день N · K карточки».
  int get returningUnits => program.where((u) => u.state == PlanUnitState.failed).length;

  /// Cards the day still owes — every stage's remainder.
  int get cardsLeft => stages.fold(0, (sum, s) => sum + (s.total - s.done));
}
