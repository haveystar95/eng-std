/// THE PLAN on the client — «цель + дата + минуты» in, a dated mechanism out.
///
/// A separate file from `models.dart` on purpose: the plan is a whole feature with a payload of its
/// own (`/api/v1/plans`), and it is the one part of the app that is NOT mirrored into the local DB.
/// Everything else here reads from drift; the plan is read live, because its two headline numbers —
/// готовность and the focus day — are derived by the server from the review log on every read and a
/// cached copy of them would be a second, slower opinion about where the learner is.
///
/// The wire shapes are `backend2`'s `PlanResource` / `PlanSessionResource`, field for field.
library;

import 'models.dart' show PlanSessionEnvelope, SessionCard, StudySession;

/// Where a plan is in its life. `draft` has no days and cost nothing; `active` is the commitment.
enum PlanStatus {
  draft,
  active,
  paused,
  completed,
  abandoned;

  static PlanStatus fromWire(String? v) =>
      PlanStatus.values.firstWhere((s) => s.name == v, orElse: () => PlanStatus.draft);

  /// A plan the learner is on right now — the one the home card and the tab both draw.
  bool get isRunning => this == PlanStatus.active || this == PlanStatus.paused;
}

/// `intro` — a teaching day. `final` — the run-through, which introduces no new words.
enum PlanDayKind {
  intro,
  finalRun;

  static PlanDayKind fromWire(String? v) => v == 'final' ? PlanDayKind.finalRun : PlanDayKind.intro;
}

/// A day's generation state. `ready` = written and studiable; `done` = its stage A is closed.
enum PlanDayStatus {
  pending,
  generating,
  ready,
  failed,
  done;

  static PlanDayStatus fromWire(String? v) =>
      PlanDayStatus.values.firstWhere((s) => s.name == v, orElse: () => PlanDayStatus.pending);

  /// Is there material to open? A day still being written has a title and nothing else.
  bool get hasMaterial => this == PlanDayStatus.ready || this == PlanDayStatus.done;

  /// The server is working on it — the «собираю день N» screen polls while this is true.
  bool get isBuilding => this == PlanDayStatus.pending || this == PlanDayStatus.generating;
}

/// The three stages of the plan's own ladder. Rendered as a brass A / B / C beside a word.
enum PlanStage {
  a,
  b,
  c;

  static PlanStage fromWire(String? v) =>
      PlanStage.values.firstWhere((s) => s.name == v, orElse: () => PlanStage.a);

  String get letter => name.toUpperCase();
}

/// «Как сейчас говоришь» — four human sentences, not A1/B2. The wire values are the server's.
enum PlanLevel {
  zero('zero'),
  basic('basic'),
  conversational('conversational'),
  fluent('fluent');

  const PlanLevel(this.wire);
  final String wire;

  static PlanLevel fromWire(String? v) =>
      PlanLevel.values.firstWhere((s) => s.wire == v, orElse: () => PlanLevel.basic);
}

/// One line of «Ты уже можешь»: an ability the plan promises, and whether it has been confirmed.
///
/// [hit] is written by the CONVERSATION (CONV-1) and is false for every checkpoint until that lands.
/// The screen draws «—» for those, which is the honest picture: the ability is taught, and nobody
/// has yet said it out loud to anyone.
class PlanCheckpoint {
  const PlanCheckpoint({required this.text, required this.dayIndex, required this.hit});

  final String text;
  final int dayIndex;
  final bool hit;

  factory PlanCheckpoint.fromJson(Map<String, dynamic> j) => PlanCheckpoint(
    text: (j['text'] as String?) ?? '',
    dayIndex: (j['day_index'] as num?)?.toInt() ?? 0,
    hit: j['hit'] == true,
  );
}

/// An ability that did NOT fit in the days available — the «срок мал» card's own list.
class PlanDroppedSkill {
  const PlanDroppedSkill({required this.outcome, required this.estTerms});

  final String outcome;
  final int estTerms;

  factory PlanDroppedSkill.fromJson(Map<String, dynamic> j) => PlanDroppedSkill(
    outcome: (j['outcome'] as String?) ?? '',
    estTerms: (j['est_terms'] as num?)?.toInt() ?? 0,
  );
}

/// One day as the SERVER's arithmetic planned it — the preview's own row.
///
/// Distinct from [PlanDay], which is a day that EXISTS: this one carries the counts the preview
/// needs («5 слов · 4 фразы») and is available before a single day has been generated.
class PlanComputedDay {
  const PlanComputedDay({
    required this.index,
    required this.kind,
    required this.title,
    required this.scheduledOn,
    required this.termBudget,
    required this.phraseCount,
    required this.wordCount,
    required this.outcomes,
    required this.checkpoints,
  });

  final int index;
  final PlanDayKind kind;
  final String title;
  final String? scheduledOn;
  final int termBudget, phraseCount, wordCount;
  final List<String> outcomes, checkpoints;

  factory PlanComputedDay.fromJson(Map<String, dynamic> j) => PlanComputedDay(
    index: (j['index'] as num?)?.toInt() ?? 0,
    kind: PlanDayKind.fromWire(j['kind'] as String?),
    title: (j['title'] as String?) ?? '',
    scheduledOn: j['scheduled_on'] as String?,
    termBudget: (j['term_budget'] as num?)?.toInt() ?? 0,
    phraseCount: (j['phrase_count'] as num?)?.toInt() ?? 0,
    wordCount: (j['word_count'] as num?)?.toInt() ?? 0,
    outcomes: _strings(j['outcome']),
    checkpoints: _strings(j['checkpoints']),
  );
}

/// The server's calendar arithmetic, verbatim. Nothing here is re-derived on the device: the model
/// proposes days, the server decides them, and a client that recounted would be a third opinion.
class PlanComputed {
  const PlanComputed({
    required this.need,
    required this.capacity,
    required this.introDays,
    required this.restDays,
    required this.fits,
    required this.step,
    required this.dropReason,
    required this.finalSameDay,
    required this.dropped,
    required this.days,
  });

  final int need, capacity, introDays, restDays, step;
  final bool fits, finalSameDay;

  /// `deadline` — the event is too near. `cap` — the plan wanted more days than a plan may have.
  /// Two different sentences on the «срок мал» card and two different decisions for the learner.
  final String? dropReason;

  final List<PlanDroppedSkill> dropped;
  final List<PlanComputedDay> days;

  /// How many teaching days there are — «подготовка N дней + прогон».
  int get introDayCount => days.where((d) => d.kind == PlanDayKind.intro).length;

  /// «~18 фраз и слов» — the whole plan's material, as planned.
  int get totalTerms => days.fold(0, (sum, d) => sum + d.termBudget);

  factory PlanComputed.fromJson(Map<String, dynamic> j) => PlanComputed(
    need: (j['need'] as num?)?.toInt() ?? 0,
    capacity: (j['capacity'] as num?)?.toInt() ?? 0,
    introDays: (j['intro_days'] as num?)?.toInt() ?? 0,
    restDays: (j['rest_days'] as num?)?.toInt() ?? 0,
    fits: j['fits'] != false,
    step: (j['step'] as num?)?.toInt() ?? 1,
    dropReason: j['drop_reason'] as String?,
    finalSameDay: j['final_same_day'] == true,
    dropped: ((j['dropped_skills'] as List?) ?? const [])
        .map((e) => PlanDroppedSkill.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
    days: ((j['days'] as List?) ?? const [])
        .map((e) => PlanComputedDay.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}

/// A day that EXISTS — a row in `learning_plan_days`, with its own generation status and, once it is
/// written, an ordinary collection behind it.
class PlanDay {
  const PlanDay({
    required this.id,
    required this.index,
    required this.kind,
    required this.title,
    required this.scheduledOn,
    required this.collectionId,
    required this.status,
    required this.failReason,
    required this.termBudget,
    required this.outcomes,
    required this.checkpoints,
    required this.topics,
    required this.role,
  });

  final String id;
  final int index;
  final PlanDayKind kind;
  final String title;
  final String? scheduledOn;
  final String? collectionId;
  final PlanDayStatus status;
  final String? failReason;
  final int termBudget;
  final List<String> outcomes, checkpoints, topics;

  /// Who the learner will be talking to on this day («Врач-терапевт»). CONV-1 opens it; until then
  /// it is what the locked «Разговор» block on the day screen names.
  final Map<String, dynamic>? role;

  String? get roleTitle {
    final r = role;
    if (r == null) return null;
    final name = r['title'] ?? r['name'] ?? r['who'];

    return name is String && name.trim().isNotEmpty ? name.trim() : null;
  }

  factory PlanDay.fromJson(Map<String, dynamic> j) => PlanDay(
    id: (j['id'] as String?) ?? '',
    index: (j['index'] as num?)?.toInt() ?? 0,
    kind: PlanDayKind.fromWire(j['kind'] as String?),
    title: (j['title'] as String?) ?? '',
    scheduledOn: j['scheduled_on'] as String?,
    collectionId: j['collection_id'] as String?,
    status: PlanDayStatus.fromWire(j['status'] as String?),
    failReason: j['fail_reason'] as String?,
    termBudget: (j['term_budget'] as num?)?.toInt() ?? 0,
    outcomes: _strings(j['outcome']),
    checkpoints: _strings(j['checkpoints']),
    topics: _strings(j['topics']),
    role: j['role'] as Map<String, dynamic>?,
  );
}

/// A whole plan, structure and all — `GET /plans/active` and `GET /plans/{id}`.
class LearningPlan {
  const LearningPlan({
    required this.id,
    required this.status,
    required this.title,
    required this.goalText,
    required this.goalRestated,
    required this.supportLang,
    required this.targetLang,
    required this.level,
    required this.eventDate,
    required this.minutesPerDay,
    required this.startedAt,
    required this.completedAt,
    required this.readiness,
    required this.focusDayIndex,
    required this.nextDayIndex,
    required this.daysToEvent,
    required this.deadlineTight,
    required this.canAlready,
    required this.goalTerms,
    required this.computed,
    required this.days,
  });

  final String id;
  final PlanStatus status;
  final String title, goalText;
  final String? goalRestated;
  final String supportLang, targetLang;
  final PlanLevel level;

  /// `Y-m-d`. The event, not a deadline the app invented.
  final String eventDate;
  final int minutesPerDay;
  final String? startedAt, completedAt;

  /// 0…1. `0.6 × чек-пойнты, сказанные вслух + 0.4 × слова на ступени C` — and the first half is a
  /// literal zero until CONV-1 lands, so the number can only ever grow, never be revised down.
  final double readiness;

  /// The day the learner is ON, derived by the server from the review log on every read.
  final int focusDayIndex;
  final int? nextDayIndex;

  /// Whole days to the event. 0 = today, negative = it has passed.
  final int daysToEvent;

  /// What is LEFT no longer fits in the days that are left. Nothing is cut on the strength of it —
  /// it is the «срок мал» card's trigger, and the decision is the learner's.
  final bool deadlineTight;

  final List<PlanCheckpoint> canAlready;
  final List<String> goalTerms;
  final PlanComputed? computed;
  final List<PlanDay> days;

  int get readinessPercent => (readiness * 100).round();

  /// «Ты уже можешь · 3 из 6».
  int get checkpointsHit => canAlready.where((c) => c.hit).length;

  /// The teaching days, in order. The final run-through is not one of them.
  List<PlanDay> get introDays => days.where((d) => d.kind == PlanDayKind.intro).toList();

  PlanDay? get focusDay => dayAt(focusDayIndex);

  PlanDay? dayAt(int index) {
    for (final day in days) {
      if (day.index == index) return day;
    }

    return null;
  }

  PlanComputedDay? computedDayAt(int index) {
    for (final day in computed?.days ?? const <PlanComputedDay>[]) {
      if (day.index == index) return day;
    }

    return null;
  }

  factory LearningPlan.fromJson(Map<String, dynamic> j) => LearningPlan(
    id: (j['id'] as String?) ?? '',
    status: PlanStatus.fromWire(j['status'] as String?),
    title: (j['title'] as String?) ?? '',
    goalText: (j['goal_text'] as String?) ?? '',
    goalRestated: j['goal_restated'] as String?,
    supportLang: (j['support_lang'] as String?) ?? 'ru',
    targetLang: (j['target_lang'] as String?) ?? 'en',
    level: PlanLevel.fromWire(j['level'] as String?),
    eventDate: (j['event_date'] as String?) ?? '',
    minutesPerDay: (j['minutes_per_day'] as num?)?.toInt() ?? 20,
    startedAt: j['started_at'] as String?,
    completedAt: j['completed_at'] as String?,
    readiness: (j['readiness'] as num?)?.toDouble() ?? 0,
    focusDayIndex: (j['focus_day_index'] as num?)?.toInt() ?? 1,
    nextDayIndex: (j['next_day_index'] as num?)?.toInt(),
    daysToEvent: (j['days_to_event'] as num?)?.toInt() ?? 0,
    deadlineTight: j['deadline_tight'] == true,
    canAlready: ((j['can_already'] as List?) ?? const [])
        .map((e) => PlanCheckpoint.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
    goalTerms: _strings(j['goal_terms']),
    computed: j['computed'] is Map<String, dynamic>
        ? PlanComputed.fromJson(j['computed'] as Map<String, dynamic>)
        : null,
    days: ((j['days'] as List?) ?? const [])
        .map((e) => PlanDay.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}

/// A plan as a LIST ROW — the finished plan at the top of the tab and the archive under it
/// (кадр 1c · 11). Deliberately thin: an archive row says three words, and a full read of each plan
/// would run the server's per-day progress computation to say them.
class PlanSummary {
  const PlanSummary({
    required this.id,
    required this.status,
    required this.title,
    required this.eventDate,
    required this.dayCount,
    required this.completedAt,
  });

  final String id;
  final PlanStatus status;
  final String title, eventDate;
  final int dayCount;
  final String? completedAt;

  factory PlanSummary.fromJson(Map<String, dynamic> j) => PlanSummary(
    id: (j['id'] as String?) ?? '',
    status: PlanStatus.fromWire(j['status'] as String?),
    title: (j['title'] as String?) ?? '',
    eventDate: (j['event_date'] as String?) ?? '',
    dayCount: (j['day_count'] as num?)?.toInt() ?? 0,
    completedAt: j['completed_at'] as String?,
  );
}

/// One word or phrase of a day, with the stage it stands on — the day screen's registry.
class PlanTermRow {
  const PlanTermRow({
    required this.termId,
    required this.text,
    required this.translation,
    required this.type,
    required this.stage,
    required this.stageComplete,
    required this.finished,
    required this.fromDayIndex,
  });

  final String termId;
  final String text;
  final String? translation;

  /// `word | phrase | idiom | phrasal_verb`. The day screen splits on it: phrases are set in serif
  /// with a terracotta rule, words are a compact register.
  final String type;

  final PlanStage stage;
  final bool stageComplete, finished;

  /// The day this term was INTRODUCED on. Equal to the day being shown for its own words; smaller
  /// for a word carried in from an earlier day («B · со дня 1»).
  final int fromDayIndex;

  bool get isPhrase => type != 'word';

  factory PlanTermRow.fromJson(Map<String, dynamic> j) => PlanTermRow(
    termId: (j['id'] as String?) ?? '',
    text: (j['text'] as String?) ?? '',
    translation: j['translation'] as String?,
    type: (j['type'] as String?) ?? 'word',
    stage: PlanStage.fromWire(j['stage'] as String?),
    stageComplete: j['stage_complete'] == true,
    finished: j['finished'] == true,
    fromDayIndex: (j['from_day_index'] as num?)?.toInt() ?? 0,
  );
}

/// One day with everything the day screen needs — `GET /plans/{id}/days/{n}`.
///
/// The day carries its plan's binding lists with it, so rendering one day is ONE request: a second
/// call for the plan just to learn the support language would be a round trip for data the server
/// already had in hand.
class PlanDayDetail {
  const PlanDayDetail({
    required this.day,
    required this.planId,
    required this.planTitle,
    required this.supportLang,
    required this.targetLang,
    required this.terms,
  });

  final PlanDay day;
  final String planId, planTitle, supportLang, targetLang;

  /// Every term of the day, its own first and then the ones carried in from earlier days.
  final List<PlanTermRow> terms;

  List<PlanTermRow> get phrases => terms.where((t) => t.isPhrase && t.fromDayIndex == day.index).toList();
  List<PlanTermRow> get words => terms.where((t) => !t.isPhrase && t.fromDayIndex == day.index).toList();

  /// Words and phrases still in flight from earlier days — the «back · pain · week · B · со дня 1»
  /// row. Carried ones are only ever listed when they are NOT finished: a word that has lived its
  /// three stages has nothing left to say on today's screen.
  List<PlanTermRow> get carried => terms.where((t) => t.fromDayIndex != day.index).toList();

  factory PlanDayDetail.fromJson(Map<String, dynamic> j) => PlanDayDetail(
    day: PlanDay.fromJson(j),
    planId: (j['plan_id'] as String?) ?? '',
    planTitle: (j['plan_title'] as String?) ?? '',
    supportLang: (j['support_lang'] as String?) ?? 'ru',
    targetLang: (j['target_lang'] as String?) ?? 'en',
    terms: ((j['terms'] as List?) ?? const [])
        .map((e) => PlanTermRow.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}

/// The plan's envelope around one ordinary study card.
///
/// The card itself is the app's own [SessionCard], rendered by the same exercise widget as every
/// other card in the product — «механика тренажёров не меняется» is a rule, and reusing the card is
/// how it is kept. What the envelope adds is the three things the plan session says and an ordinary
/// one cannot: which stage this is, where in the stage, and which day the word came from.
class PlanSessionTask {
  const PlanSessionTask({
    required this.stage,
    required this.ordinal,
    required this.ofSteps,
    required this.fromDayIndex,
    required this.softened,
    required this.card,
  });

  final PlanStage stage;

  /// «3 из 4» inside the stage.
  final int ordinal, ofSteps;

  final int fromDayIndex;

  /// Three misses in a row on this stage — this one word is being dealt on gentler knobs.
  final bool softened;

  final SessionCard card;

  factory PlanSessionTask.fromJson(Map<String, dynamic> j) => PlanSessionTask(
    stage: PlanStage.fromWire(j['stage'] as String?),
    ordinal: (j['ordinal'] as num?)?.toInt() ?? 1,
    ofSteps: (j['of_steps'] as num?)?.toInt() ?? 1,
    fromDayIndex: (j['from_day_index'] as num?)?.toInt() ?? 0,
    softened: j['softened'] == true,
    card: SessionCard.fromJson((j['card'] as Map<String, dynamic>?) ?? const {}),
  );
}

/// A whole plan session — `POST /plans/{id}/days/{n}/session`.
///
/// It IS a [StudySession] once [asStudySession] unwraps it: the same cards, played by the same
/// exercise widgets and graded the same way. What it adds is the envelope the session screen reads
/// through [PlanSessionEnvelope].
class PlanSession implements PlanSessionEnvelope {
  const PlanSession({
    required this.sessionId,
    required this.planId,
    required this.dayIndex,
    required this.strict,
    required this.tasks,
  });

  final String sessionId;
  @override
  final String planId;
  @override
  final int dayIndex;

  /// FALSE means this day was opened out of turn: a soft run over its material that schedules
  /// nothing and closes no stage. The day screen says so above the button.
  @override
  final bool strict;

  final List<PlanSessionTask> tasks;

  /// The plan session as the ordinary session screen consumes it — cards in order, envelope beside.
  StudySession asStudySession() => StudySession(
    sessionId: sessionId,
    cards: tasks.map((t) => t.card).toList(growable: false),
    plan: this,
  );

  @override
  String? stageLetterAt(int i) => i >= 0 && i < tasks.length ? tasks[i].stage.letter : null;

  @override
  ({int ordinal, int of})? stepAt(int i) {
    if (i < 0 || i >= tasks.length) return null;
    final task = tasks[i];

    return (ordinal: task.ordinal, of: task.ofSteps);
  }

  @override
  int? carriedFromAt(int i) {
    if (i < 0 || i >= tasks.length) return null;
    final from = tasks[i].fromDayIndex;

    // Only when it says something: for the day's own words this equals the day being studied, and
    // «слово из дня 2» inside day 2 is a line that costs a row and adds nothing.
    return from > 0 && from < dayIndex ? from : null;
  }

  factory PlanSession.fromJson(Map<String, dynamic> j) => PlanSession(
    sessionId: (j['session_id'] as String?) ?? '',
    planId: (j['plan_id'] as String?) ?? '',
    dayIndex: (j['day_index'] as num?)?.toInt() ?? 1,
    strict: j['strict'] != false,
    tasks: ((j['tasks'] as List?) ?? const [])
        .map((e) => PlanSessionTask.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}

/// One phrase of the pre-event rehearsal (кадр 15) — `POST /plans/{id}/rehearsal`.
///
/// Phrases only, and deliberately: three minutes before walking in, the point is to HEAR yourself
/// say the sentence, not to be tested on the words inside it.
class RehearsalLine {
  const RehearsalLine({
    required this.termId,
    required this.text,
    required this.translation,
    required this.cue,
    required this.dayIndex,
  });

  final String termId, text;
  final String? translation;

  /// «Врач спросит: „How long has it been like this?"» — the question this sentence answers, when
  /// the day's role brief named one.
  final String? cue;

  final int dayIndex;

  factory RehearsalLine.fromJson(Map<String, dynamic> j) => RehearsalLine(
    termId: (j['id'] as String?) ?? '',
    text: (j['text'] as String?) ?? '',
    translation: j['translation'] as String?,
    cue: j['cue'] as String?,
    dayIndex: (j['day_index'] as num?)?.toInt() ?? 0,
  );
}

/// The rehearsal, whole.
class PlanRehearsal {
  const PlanRehearsal({required this.planId, required this.title, required this.lines});

  final String planId, title;
  final List<RehearsalLine> lines;

  factory PlanRehearsal.fromJson(Map<String, dynamic> j) => PlanRehearsal(
    planId: (j['plan_id'] as String?) ?? '',
    title: (j['title'] as String?) ?? '',
    lines: ((j['lines'] as List?) ?? const [])
        .map((e) => RehearsalLine.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}

/// «На приёме сказал 5 из 6» — what the learner ticked after the event.
class PlanEventFeedback {
  const PlanEventFeedback({required this.used, required this.total, required this.checkpoints});

  final int used, total;
  final List<PlanCheckpoint> checkpoints;

  factory PlanEventFeedback.fromJson(Map<String, dynamic> j) => PlanEventFeedback(
    used: (j['used'] as num?)?.toInt() ?? 0,
    total: (j['total'] as num?)?.toInt() ?? 0,
    checkpoints: ((j['checkpoints'] as List?) ?? const [])
        .map((e) => PlanCheckpoint.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}

List<String> _strings(Object? raw) => ((raw as List?) ?? const [])
    .whereType<String>()
    .where((s) => s.trim().isNotEmpty)
    .toList(growable: false);
