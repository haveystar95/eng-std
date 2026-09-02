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

  /// The day may still turn into material — the «собираю день N» screen polls while this is true.
  ///
  /// Both states, deliberately: a `pending` day may have a job queued this second, and a poller
  /// that stopped at the queue boundary would give up on a day that was about to arrive.
  bool get isBuilding => this == PlanDayStatus.pending || this == PlanDayStatus.generating;

  /// Nobody has taken this day yet. It may be queued and it may be waiting its turn — either way
  /// nothing is being written right now.
  ///
  /// Apart from [isGenerating] because a LABEL must tell them apart, even though a poller must not.
  /// `generating` is written by exactly one thing — the worker that already holds the day — so it
  /// alone means «собирается». The list said «Собирается» over both, and the live run photographed
  /// two untouched `pending` days with zero attempts wearing it (Д-20).
  bool get isQueued => this == PlanDayStatus.pending;

  /// A worker holds this day right now.
  bool get isGenerating => this == PlanDayStatus.generating;
}

/// How many times the server will CLAIM a day before it stops — `PlanDay::MAX_ATTEMPTS`.
///
/// Mirrored rather than fetched because it is a constant of the domain, and the client needs it for
/// one question only: is this failure retryable. A day past it is a day the server will never build,
/// so a screen that still offers «Собрать день» is offering a button that cannot work.
const int _maxGenerationAttempts = 2;

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
    required this.generationAttempts,
    required this.failReason,
    required this.failCode,
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

  /// CLAIMS, not failures, and the server's cap is two ({@link PlanDay::MAX_ATTEMPTS}).
  ///
  /// The client needs this to tell two states apart that look identical without it: a day that
  /// failed and WILL be tried again, and a day the server will never claim again. Offering «Собрать
  /// день» in the second case is a button that cannot work — the same class of bug as a client gate
  /// looser than the server's.
  final int generationAttempts;

  /// Why the last attempt failed, in the server's words. NEVER shown: it is Russian prose written
  /// for the server's own logs, and the app has two languages. [failCode] is the half that crosses.
  final String? failReason;

  /// The CODE of the first fatal violation — `day.example_is_a_term` and the rest, or null unless
  /// the day is `failed`.
  ///
  /// The screen switches on it and writes the sentence itself. Before it existed there was one
  /// hard-coded sentence for every failure there is, and a day that had died on an example
  /// duplicating its own card told the owner the model had answered in the wrong language (Д-19).
  final String? failCode;

  final int termBudget;

  /// The server has spent its two claims: this day cannot be built again, ever.
  bool get outOfAttempts =>
      status == PlanDayStatus.failed && generationAttempts >= _maxGenerationAttempts;
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
    generationAttempts: (j['generation_attempts'] as num?)?.toInt() ?? 0,
    failReason: j['fail_reason'] as String?,
    failCode: j['fail_code'] as String?,
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
    required this.eventFeedback,
    required this.goalTerms,
    required this.computed,
    required this.days,
    this.cardsTotal = 0,
    this.stageAClosed = 0,
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

  /// HOW MANY CARDS THE PLAN HAS WRITTEN, AND HOW MANY HAVE CLOSED STAGE A — `stage_census`.
  ///
  /// The plain count the plan card shows instead of [readiness] until the canonical formula arrives
  /// with P2-v0.4/SIT-1. Readiness counts the cards that reached their LAST stage (a line after B, a
  /// word after C), so a day that closes stage A moves its cards ONTO stage B and the percentage
  /// honestly stays at zero — which is what the owner read after walking fifty-six cards (02.09).
  final int cardsTotal, stageAClosed;

  /// Of the cards written, the ones whose stage A is still open. Never negative: a server that has
  /// not learned to send the census yet answers zero for both, and zero minus zero is zero.
  int get stageALeft => (cardsTotal - stageAClosed).clamp(0, cardsTotal);

  /// «На приёме сказал 5 из 6» — the positions in [canAlready] the learner ticked after the event.
  ///
  /// NULL means they were never asked; an EMPTY list means they were asked and used none of it.
  /// The finished-plan screen says a different sentence for each, which is the whole reason the
  /// column is nullable on the server.
  final List<int>? eventFeedback;

  final List<String> goalTerms;
  final PlanComputed? computed;
  final List<PlanDay> days;

  int get readinessPercent => (readiness * 100).round();

  /// «Ты уже можешь · 3 из 6».
  int get checkpointsHit => canAlready.where((c) => c.hit).length;

  /// Was «как прошло?» answered at all? The plan's own last question.
  bool get hasEventFeedback => eventFeedback != null;

  /// How many abilities the learner said they used at the event.
  int get eventFeedbackHits => eventFeedback?.length ?? 0;

  /// Did this ability come up at the event? False for every checkpoint until the question is
  /// answered, which is right: an unanswered question is not a «no».
  bool usedAtEvent(int index) => eventFeedback?.contains(index) ?? false;

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
    cardsTotal: ((j['stage_census'] as Map<String, dynamic>?)?['total'] as num?)?.toInt() ?? 0,
    stageAClosed:
        ((j['stage_census'] as Map<String, dynamic>?)?['stage_a_closed'] as num?)?.toInt() ?? 0,
    focusDayIndex: (j['focus_day_index'] as num?)?.toInt() ?? 1,
    nextDayIndex: (j['next_day_index'] as num?)?.toInt(),
    daysToEvent: (j['days_to_event'] as num?)?.toInt() ?? 0,
    deadlineTight: j['deadline_tight'] == true,
    canAlready: ((j['can_already'] as List?) ?? const [])
        .map((e) => PlanCheckpoint.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
    eventFeedback: j['event_feedback'] is List
        ? (j['event_feedback'] as List)
              .whereType<num>()
              .map((n) => n.toInt())
              .toList(growable: false)
        : null,
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
    this.kind,
    this.speaker,
  });

  final String termId;
  final String text;
  final String? translation;

  /// `word | phrase | idiom | phrasal_verb` — what the expression IS, lexically.
  final String type;

  /// `line | word | chunk` — what it DOES in this day, and null on a term written before plans
  /// carried the field. This is what the day screen splits on; see [isPhrase].
  final String? kind;

  final PlanStage stage;
  final bool stageComplete, finished;

  /// The day this term was INTRODUCED on. Equal to the day being shown for its own words; smaller
  /// for a word carried in from an earlier day («B · со дня 1»).
  final int fromDayIndex;

  /// Set apart as a spoken line — serif, with a terracotta rule — rather than as a register row.
  ///
  /// [kind] decides when the server sent one. The old rule («anything that is not one word is a
  /// line») survives only as the fallback for terms written before the field existed: it starts
  /// lying the moment a connector appears, because «deal with» is two words and a substitution.
  bool get isPhrase => kind == null ? type != 'word' : kind == 'line';

  /// `learner | role` — whose line this is, and null on anything that is not a plan line.
  final String? speaker;

  /// The INTERLOCUTOR's line: something to understand when it is said, never something to say.
  ///
  /// The register must mark it. Without the mark it stood among the learner's own phrases and read
  /// as one of them — «Hello. What seems to be the problem with your child?» in a list captioned
  /// «ФРАЗЫ ДНЯ» (Д-8).
  bool get isRoleLine => speaker == 'role';

  factory PlanTermRow.fromJson(Map<String, dynamic> j) => PlanTermRow(
    termId: (j['id'] as String?) ?? '',
    text: (j['text'] as String?) ?? '',
    translation: j['translation'] as String?,
    type: (j['type'] as String?) ?? 'word',
    kind: j['kind'] as String?,
    speaker: j['speaker'] as String?,
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
    this.section = sectionDay,
    this.origin,
    this.kind,
    this.speaker,
  });

  /// This task is the day's own material — it counts towards «день пройден».
  static const sectionDay = 'day';

  /// Top-up from the learner's ordinary queue — worth playing, not part of the day.
  static const sectionReview = 'review';

  final PlanStage stage;

  /// «3 из 4» inside the stage.
  final int ordinal, ofSteps;

  final int fromDayIndex;

  /// Three misses in a row on this stage — this one word is being dealt on gentler knobs.
  final bool softened;

  final SessionCard card;

  /// [sectionDay] or [sectionReview] — which side of the seam this task is on.
  final String section;

  /// Where a REVIEW card came from — «Отпуск в Италии». Null on the day's own material, which is
  /// today and needs no explanation. `kind` is `plan` or `collection`; the sentence is written
  /// here, because the wording is the client's and there are two languages of it.
  final PlanTaskOrigin? origin;

  /// `line | word | chunk` — what this card DOES in its day, and null outside a plan.
  ///
  /// The summary counts by it and the card is labelled by it. Before the server sent it here, the
  /// screen counted words in the card's TEXT, so a connector came out «фраза» and the day read
  /// «3 слова · 11 фраз» over 4 word + 2 chunk + 8 line (Д-5).
  final String? kind;

  /// `learner | role` — whose turn this line is, and null on anything that is not a plan line.
  ///
  /// A `role` line is the interlocutor's. It is dealt for recognition only and has to SAY so, or it
  /// is indistinguishable from a card the learner is meant to produce (Д-8).
  final String? speaker;

  /// The interlocutor's own line — never something the learner is asked to say.
  bool get isRoleLine => speaker == 'role';

  bool get isDay => section != sectionReview;

  factory PlanSessionTask.fromJson(Map<String, dynamic> j) {
    final fromDay = (j['from_day_index'] as num?)?.toInt() ?? 0;

    return PlanSessionTask(
      stage: PlanStage.fromWire(j['stage'] as String?),
      ordinal: (j['ordinal'] as num?)?.toInt() ?? 1,
      ofSteps: (j['of_steps'] as num?)?.toInt() ?? 1,
      fromDayIndex: fromDay,
      softened: j['softened'] == true,
      card: SessionCard.fromJson((j['card'] as Map<String, dynamic>?) ?? const {}),
      // The server names it; the fallback is the rule it names, for a payload written before the
      // field existed. `from_day_index: null` — decoded as 0 above — is a term of no day of this
      // plan, which is exactly what the top-up is.
      section: (j['section'] as String?) ?? (fromDay > 0 ? sectionDay : sectionReview),
      origin: PlanTaskOrigin.fromJson(j['origin'] as Map<String, dynamic>?),
      kind: j['kind'] as String?,
      speaker: j['speaker'] as String?,
    );
  }
}

/// Where a review card came from, as the server names it.
class PlanTaskOrigin {
  const PlanTaskOrigin({required this.kind, required this.title});

  /// `plan` — another plan of this learner's; `collection` — an ordinary folder.
  final String kind;

  /// «Отпуск в Италии» — the plan's name, or the folder's.
  final String title;

  bool get isPlan => kind == 'plan';

  static PlanTaskOrigin? fromJson(Map<String, dynamic>? j) {
    final title = (j?['title'] as String?)?.trim();
    if (title == null || title.isEmpty) return null;

    return PlanTaskOrigin(kind: (j?['kind'] as String?) ?? 'collection', title: title);
  }
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

  /// Every task that belongs to TODAY, in order — `tasks` minus the revision of earlier days.
  List<PlanSessionTask> get dayTasks => tasks.where((t) => t.isDay).toList(growable: false);

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
  bool isDayTaskAt(int i) => i >= 0 && i < tasks.length && tasks[i].isDay;

  @override
  String? kindAt(int i) => i >= 0 && i < tasks.length ? tasks[i].kind : null;

  @override
  String? speakerAt(int i) => i >= 0 && i < tasks.length ? tasks[i].speaker : null;

  @override
  int get dayTaskCount => dayTasks.length;

  @override
  ({String kind, String title})? originAt(int i) {
    if (i < 0 || i >= tasks.length) return null;
    final origin = tasks[i].origin;

    return origin == null ? null : (kind: origin.kind, title: origin.title);
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
    required this.role,
    required this.dayIndex,
  });

  final String termId, text;
  final String? translation;

  /// «How long has it been like this?» — what the person on the other side says, taken from the
  /// day's role brief. Null when the day had nobody to talk to, and then no cue is drawn: inventing
  /// one would be putting words in a mouth that is not there.
  final String? cue;

  /// «Врач-терапевт» — who says [cue].
  final String? role;

  final int dayIndex;

  factory RehearsalLine.fromJson(Map<String, dynamic> j) => RehearsalLine(
    termId: (j['id'] as String?) ?? '',
    text: (j['text'] as String?) ?? '',
    translation: j['translation'] as String?,
    cue: j['cue'] as String?,
    role: j['role'] as String?,
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

List<String> _strings(Object? raw) => ((raw as List?) ?? const [])
    .whereType<String>()
    .where((s) => s.trim().isNotEmpty)
    .toList(growable: false);
