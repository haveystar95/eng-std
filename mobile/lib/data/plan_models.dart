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

import 'line_audio.dart';
import 'models.dart'
    show
        ExerciseMode,
        PlanDayStateWire,
        PlanDialogue,
        PlanSessionEnvelope,
        PlanSituation,
        SceneRunKnobs,
        SessionCard,
        StudySession;

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

/// ОДНО СЛОВО О ДНЕ — «не начат» · «идёт» · «пройден» (наряд DAY-FIX-2, Ч.3).
///
/// Считает СЕРВЕР, и только он: вкладка «План», экран дня и шапка присеста читают это поле, и ни
/// один из них не держит счётчика «осталось N». Отдельно от [PlanDayStatus], который про СБОРКУ
/// дня: `ready` день может быть нетронутым или пройденным наполовину.
enum PlanDayState {
  notStarted,
  inProgress,
  done;

  /// Открытый набор: код, которого эта сборка не знает, читается как «идёт»; поля нет вовсе
  /// (сервер до DAY-FIX-2) — день ещё не начат, потому что о нём ничего не известно.
  static PlanDayState fromWire(String? v) => switch (v) {
    null || PlanDayStateWire.notStarted => PlanDayState.notStarted,
    PlanDayStateWire.done => PlanDayState.done,
    _ => PlanDayState.inProgress,
  };
}

/// The three stages of the plan's own ladder. Rendered as a brass A / B / C beside a word.
enum PlanStage {
  a,
  b,
  c;

  static PlanStage fromWire(String? v) =>
      PlanStage.values.firstWhere((s) => s.name == v, orElse: () => PlanStage.a);

  /// THE STAGE THE SERVER NAMED, or null when it named none — «нет ступени» is an answer.
  ///
  /// [fromWire] falls back to `a`, which is right for a day's TERM ROW (every card of a scene stands
  /// on a stage) and was wrong for a session TASK: the run-through of the final day and the warm-up's
  /// light touch carry `stage: null`, and reading that as «A» is what put «СТУПЕНЬ A · ПОВТОРЕНИЕ»
  /// over a dictation on the morning of the event (E2E-SIM-2, С-9).
  static PlanStage? tryFromWire(String? v) {
    for (final stage in PlanStage.values) {
      if (stage.name == v) return stage;
    }

    return null;
  }

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
    this.intro = '',
    this.dayState = PlanDayState.inProgress,
    this.minutesLeft = 0,
  });

  final String id;
  final int index;
  final PlanDayKind kind;
  final String title;
  final String? scheduledOn;
  final String? collectionId;
  final PlanDayStatus status;

  /// ОДНО СЛОВО О ДНЕ и его минуты, как их посчитал сервер (наряд DAY-FIX-2, Ч.3). Экран не
  /// считает «осталось N» сам: живой прогон показал три экрана с тремя разными счётами.
  final PlanDayState dayState;
  final int minutesLeft;

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

  /// THE SCENE'S ВВОДКА — two or three sentences in the learner's OWN language: who is in front of
  /// you, what is about to happen, what counts as success (канон §2).
  ///
  /// Empty on a day written before the day became a scene, and empty is a legitimate answer rather
  /// than a hole to fill: the day screen simply draws nothing. The string is the server's — the
  /// model writes it in the support language — so it is one of the few pieces of copy on this
  /// screen that does NOT come out of `AppLocalizations`.
  final String intro;

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
    intro: (j['intro'] as String?)?.trim() ?? '',
    dayState: PlanDayState.fromWire(j['day_state'] as String?),
    minutesLeft: (j['minutes_left'] as num?)?.toInt() ?? 0,
  );
}

/// ЗРЕЛОСТЬ ОДНОЙ СЦЕНЫ И ЕЁ ПОСЛЕДНИЙ ПРОГОН — наряд SCENE-RUN, Ч.3.
///
/// Три слова канона (познакомился → применяю → говорю сам) приходят с сервера кодом, а не
/// вычисляются здесь: «говоришь сам» стоит на переписи ступени C, которой у экрана нет и быть не
/// может. Выводить вердикт из того, чего нет, — это ровно тот процент, который владелец три дня
/// читал нулём.
class PlanSceneCensus {
  const PlanSceneCensus({
    required this.dayIndex,
    required this.maturity,
    required this.ready,
    this.run,
  });

  final int dayIndex;

  /// `met` | `applying` | `speaking` — открытый набор: код, которого эта сборка не знает, честнее
  /// показать первым состоянием, чем угадать.
  final String maturity;

  /// «C + скорость» (канон §4). Числом на экраны плана не выходит — там говорят словами.
  final bool ready;

  /// Итог ПОСЛЕДНЕГО прогона этой сцены, или null — её ещё не прогоняли.
  final ({int total, int said, int saidFast, int skipped, int rescued})? run;

  static PlanSceneCensus fromJson(Map<String, dynamic> j) {
    final run = j['run'] as Map<String, dynamic>?;

    return PlanSceneCensus(
      dayIndex: (j['day_index'] as num?)?.toInt() ?? 0,
      maturity: (j['maturity'] as String?) ?? 'met',
      ready: j['ready'] == true,
      run: run == null
          ? null
          : (
              total: (run['total'] as num?)?.toInt() ?? 0,
              said: (run['said'] as num?)?.toInt() ?? 0,
              saidFast: (run['said_fast'] as num?)?.toInt() ?? 0,
              skipped: (run['skipped'] as num?)?.toInt() ?? 0,
              rescued: (run['rescued'] as num?)?.toInt() ?? 0,
            ),
    );
  }
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
    this.scenes = const [],
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

  /// `Y-m-d`, or NULL — «Без даты» (кадр V4·04б).
  ///
  /// A plan with no date is a different plan, not a distant one: nothing counts down, nothing is
  /// «срок мал», the days carry no date of their own and open one after another, and the rehearsal
  /// is «в конце». Every screen that prints a date has to say the other sentence instead, which is
  /// why this is nullable rather than an empty string — an empty string is a date that renders.
  final String? eventDate;
  final int minutesPerDay;
  final String? startedAt, completedAt;

  /// 0…1. `0.6 × чек-пойнты, сказанные вслух + 0.4 × слова на ступени C` — and the first half is a
  /// literal zero until CONV-1 lands, so the number can only ever grow, never be revised down.
  final double readiness;

  /// ЗРЕЛОСТЬ КАЖДОЙ СЦЕНЫ и её последний прогон (наряд SCENE-RUN, Ч.3). Пусто на пейлоаде
  /// сервера, который поля ещё не знает, — и тогда экран говорит ровно то, что говорил раньше.
  final List<PlanSceneCensus> scenes;

  /// Перепись сцены, введённой днём [dayIndex], или null.
  PlanSceneCensus? sceneAt(int dayIndex) {
    for (final scene in scenes) {
      if (scene.dayIndex == dayIndex) return scene;
    }

    return null;
  }

  /// The day the learner is ON, derived by the server from the review log on every read.
  final int focusDayIndex;
  final int? nextDayIndex;

  /// Whole days to the event. 0 = today, negative = it has passed, NULL = there is no date.
  ///
  /// NOT zero when there is no date: zero means «событие сегодня», and a plan without one would
  /// then be drawn as the most urgent plan the app can show.
  final int? daysToEvent;

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
    eventDate: j['event_date'] as String?,
    minutesPerDay: (j['minutes_per_day'] as num?)?.toInt() ?? 20,
    startedAt: j['started_at'] as String?,
    completedAt: j['completed_at'] as String?,
    readiness: (j['readiness'] as num?)?.toDouble() ?? 0,
    scenes: ((j['scenes'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(PlanSceneCensus.fromJson)
        .toList(growable: false),
    cardsTotal: ((j['stage_census'] as Map<String, dynamic>?)?['total'] as num?)?.toInt() ?? 0,
    stageAClosed:
        ((j['stage_census'] as Map<String, dynamic>?)?['stage_a_closed'] as num?)?.toInt() ?? 0,
    focusDayIndex: (j['focus_day_index'] as num?)?.toInt() ?? 1,
    nextDayIndex: (j['next_day_index'] as num?)?.toInt(),
    daysToEvent: (j['days_to_event'] as num?)?.toInt(),
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
  final String title;

  /// `Y-m-d`, or NULL on a plan built without a date. The archive row says «без даты» instead.
  final String? eventDate;
  final int dayCount;
  final String? completedAt;

  factory PlanSummary.fromJson(Map<String, dynamic> j) => PlanSummary(
    id: (j['id'] as String?) ?? '',
    status: PlanStatus.fromWire(j['status'] as String?),
    title: (j['title'] as String?) ?? '',
    eventDate: j['event_date'] as String?,
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
    this.shelf,
    this.tier,
    this.audioUrl,
    this.nextStep,
    this.mark,
  });

  /// ЧТО С ЭТОЙ СТРОКОЙ БУДЕТ ДЕЛАТЬ ЧЕЛОВЕК — код упражнения с сервера (наряд DAY-FIX-2, Ч.4.2):
  /// `meet` · `recognize` · `hear` · `choose` · `assemble` · `say`, или null — сегодня строка
  /// ничего не должна. Экран дня переводит код в слово под секцией; считать его сам он не вправе.
  final String? nextStep;

  /// ОТМЕТКА У СТРОКИ, если день шёл: `passed` / `said_self` / null (Ч.4.3). Словом, не цифрой.
  final String? mark;

  static const stepMeet = 'meet';
  static const stepRecognize = 'recognize';
  static const stepHear = 'hear';
  static const stepChoose = 'choose';
  static const stepAssemble = 'assemble';
  static const stepSay = 'say';

  static const markPassed = 'passed';
  static const markSaidSelf = 'said_self';

  /// The shelves of a scene, as the server names them (канон §2). `numbers` is stored and not yet
  /// dealt; `rescue` is the plan's five universal phrases, played in the warm-up.
  static const shelfHear = 'hear';
  static const shelfSay = 'say';
  static const shelfAsk = 'ask';
  static const shelfWords = 'words';
  static const shelfChunks = 'chunks';
  static const shelfNumbers = 'numbers';
  static const shelfRescue = 'rescue';

  /// The full ladder A → B → C, up to «сказал сам».
  static const tierSpeak = 'speak';

  /// Two touches and no more — heard it, recognised it. Never something to produce (канон §3).
  static const tierUnderstand = 'understand';

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

  /// WHICH SHELF OF THE SCENE this term stands on, or null on a day written before the shelves.
  ///
  /// One of the `shelf*` constants — but read as an OPEN set: a value this build has never heard of
  /// is a server ahead of the client, and it must fall through to «no shelf» rather than be forced
  /// into one of the six the switch happens to know.
  final String? shelf;

  /// `speak` | `understand` — the ladder this term climbs, or null outside a scene.
  final String? tier;

  /// СЕРВЕРНАЯ ОЗВУЧКА реплики, или null — «файла нет» (наряд TTS-1). Шпаргалка играет ТОТ ЖЕ файл,
  /// что и разговор: два голоса на одну реплику — это две разные реплики для уха.
  final String? audioUrl;

  /// Something to UNDERSTAND, never something to say — see [isRoleLine] for the older half of it.
  ///
  /// The shelf decides the tier structurally (канон §3), so the server answers it and the client
  /// only reads it. An unrecognised [tier] answers `false` and leaves the speaker to decide, which
  /// is exactly what this screen did before the field existed.
  bool get isRecognitionOnly => tier == tierUnderstand || isRoleLine;

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
    shelf: j['shelf'] as String?,
    tier: j['tier'] as String?,
    audioUrl: j['audio_url'] as String?,
    nextStep: j['next_step'] as String?,
    mark: j['mark'] as String?,
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
    this.shelf,
    this.tier,
    this.situation,
    this.speaksAfterChoice = false,
    this.sectionCode,
    this.turnLevel,
  });

  /// This task is the day's own material — it counts towards «день пройден».
  static const sectionDay = 'day';

  /// Top-up from the learner's ordinary queue — worth playing, not part of the day.
  static const sectionReview = 'review';

  /// THE WARM-UP: the plan's five rescue phrases, dealt before every day of it (канон §5).
  ///
  /// A section of its own, and the reason [isDay] asks for [sectionDay] by name instead of «not a
  /// review». The kit is the same five cards on day 1 and on day 9 — counting it into the day would
  /// grow «N из N» by five for a day that did not grow.
  static const sectionWarmup = 'warmup';

  /// The stage this card is being dealt at, or NULL when the sitting has no stages — the final
  /// day's run-through, and the warm-up's one light touch. See [PlanStage.tryFromWire].
  final PlanStage? stage;

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

  /// WHICH SHELF OF THE SCENE this card came off — `hear` | `say` | `ask` | `words` | `chunks` |
  /// `numbers` | `rescue`, or null outside a plan day. See [PlanTermRow.shelf] for the constants.
  ///
  /// The session draws a caption where it changes. [kind] cannot do that job: «Ты ответишь» and «Ты
  /// спросишь» are both `line`, and the two shelves are two different things to practise.
  final String? shelf;

  /// `speak` | `understand` — the ladder this card climbs (канон §3), or null outside a plan.
  final String? tier;

  /// THE POSITION — on a situational card, and null on every other trainer. See [PlanSituation].
  final PlanSituation? situation;

  /// The learner says the option they tapped out loud, right after tapping it — «Ты ответишь» and
  /// «Ты спросишь». Reinforcement: nothing about it is graded or uploaded.
  final bool speaksAfterChoice;

  /// WHAT THE LEARNER IS DOING — the part of the sitting, as a code (наряд DAY-2).
  ///
  /// One of [sectionCodeWarmup] … [sectionCodeReview], read as an OPEN set: a code this build has
  /// never heard of is a server ahead of the client and must fall through to «no caption» rather
  /// than be forced into one of the seven the switch happens to know.
  ///
  /// Null on a payload written before the field existed, and then the shelf decides — which is what
  /// the client did on its own until it could not: meeting a reply and speaking it in the
  /// conversation are two parts of the sitting, and `shelf` says `say` for both.
  final String? sectionCode;

  /// СТРОГОСТЬ ЭТОГО ХОДА — `choose` | `assemble` | `say`, или null (наряд SCENE-RUN).
  ///
  /// Отдельно от режима: тренажёр один и тот же, а рисуется он вариантами, блоками или микрофоном.
  /// Null у всего, что ходом человека не является, и на пейлоаде сервера, который поля не знает.
  final String? turnLevel;

  /// Ход отдан ГОЛОСОМ: ни вариантов, ни блоков — подсказка и микрофон. Ступень C.
  bool get isSpokenTurn => turnLevel == 'say';

  /// The plan's five rescue phrases, before the day, every day (канон §5).
  static const sectionCodeWarmup = 'warmup';

  /// «Слова и связки» — the pieces the scene's lines are built from.
  static const sectionCodeWords = 'words';

  /// «Знакомство с репликами» — stage A of the three line shelves, one part.
  static const sectionCodeDialogueIntro = 'dialogue_intro';

  /// «Диалог сцены» — stage B of those same shelves, played as one conversation.
  static const sectionCodeDialogue = 'dialogue';

  /// «Цифры на слух» (канон §6). No session deals one yet — the code exists so it can.
  static const sectionCodeNumbers = 'numbers';

  /// «Прогон сцены» — ступень C: та же цепочка, подсказка и микрофон (наряд SCENE-RUN, Ч.2).
  static const sectionCodeSceneRun = 'scene_run';

  /// «Прогон перед событием» — the final day's run-through over the whole plan.
  static const sectionCodeRehearsal = 'rehearsal';

  /// «Повторение · из прошлых дней» — this plan's earlier material, after the day.
  static const sectionCodeReview = 'review';

  /// This card is only ever asked for RECOGNITION — the server deals it no production trainer, and
  /// the client must not caption it as one either (Д-8, from the other side).
  ///
  /// A [tier] this build does not know answers `false` and the older [speaker] signal decides, so a
  /// server that grows a third tier degrades to today's behaviour instead of to a wrong label.
  bool get isRecognitionOnly => tier == PlanTermRow.tierUnderstand || isRoleLine;

  /// The warm-up runs BEFORE the day and is not part of it — see [sectionWarmup].
  bool get isWarmup => section == sectionWarmup;

  /// TODAY'S OWN MATERIAL — what «день пройден» counts.
  ///
  /// Asked as «is it the day's» rather than «is it not a review» because there are three sections
  /// now: the warm-up is neither, and the older reading would have counted its five cards into
  /// every day. A payload written before `section` existed still lands on `day`/`review` through
  /// the fallback in [fromJson], so nothing about it changes.
  bool get isDay => section == sectionDay;

  factory PlanSessionTask.fromJson(Map<String, dynamic> j) {
    final fromDay = (j['from_day_index'] as num?)?.toInt() ?? 0;

    return PlanSessionTask(
      stage: PlanStage.tryFromWire(j['stage'] as String?),
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
      shelf: j['shelf'] as String?,
      tier: j['tier'] as String?,
      situation: PlanSituation.fromJson(j['situation'] as Map<String, dynamic>?),
      // The server names it per task; the mode is the same fact for a build that meets one of these
      // cards outside a plan envelope, which is why both exist.
      speaksAfterChoice:
          j['speaks_after_choice'] == true ||
          ExerciseMode.fromWire(
            (j['card'] as Map<String, dynamic>?)?['exercise_mode'] as String?,
          ).speaksAfterChoice,
      sectionCode: (j['section_code'] as String?)?.trim(),
      turnLevel: (j['turn_level'] as String?)?.trim(),
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
    this.sittings = const [],
    this.dialogues = const [],
    this.lineAudio = const [],
    this.sceneRun = const SceneRunKnobs(),
    this.raw = const {},
    this.dayState = PlanDayStateWire.inProgress,
    this.minutesLeft = 0,
  });

  /// СЛОВО О ДНЕ и его минуты — те же, что на пейлоаде плана (наряд DAY-FIX-2, Ч.3).
  @override
  final String dayState;

  @override
  final int minutesLeft;

  /// Every task that belongs to TODAY, in order — `tasks` minus the revision of earlier days.
  List<PlanSessionTask> get dayTasks => tasks.where((t) => t.isDay).toList(growable: false);

  final String sessionId;
  @override
  final String planId;
  @override
  final int dayIndex;

  /// TRUE for every teaching day of a plan — its turn or not (E2E-SIM-2, С-1).
  ///
  /// It used to be false for a day opened ahead of the focus, and that «soft run» is gone: an early
  /// day is dealt its own stage A, strictly, and its answers close it. What still comes back FALSE is
  /// the final day's run-through, which grades nothing on purpose — a plan must not be able to go
  /// backwards on its last morning.
  @override
  final bool strict;

  final List<PlanSessionTask> tasks;

  /// ПРИСЕСТЫ — the task counts of each sitting, in order, adding up to `tasks.length`.
  ///
  /// The learner's 10 / 20 / 40 minutes is the length of ONE sitting, not a limit on the day: the
  /// whole day is dealt and this says where it is honest to stop, always on a section boundary and
  /// never inside «Ты ответишь». Empty on a payload from a server that predates it, and then the day
  /// is played as one long session — which is what it was.
  @override
  final List<int> sittings;

  /// THE CONVERSATIONS this sitting plays — one per scene it reaches (наряд DAY-2).
  ///
  /// Whole scenes, so the dialogue screen plays a conversation from its first line rather than from
  /// whatever the ladder owes today. Empty on the day a scene is introduced, on the run-through, and
  /// on a payload from a server that predates the field — and then the sitting is played card by
  /// card, exactly as it was.
  @override
  final List<PlanDialogue> dialogues;

  /// ВСЯ ОЗВУЧКА ЭТОЙ ПОСАДКИ, парами «текст → файл» (наряд TTS-1, Ч.2.1).
  ///
  /// Списком, а не полем на задаче, потому что качается она ЦЕЛИКОМ на входе в день: реплики
  /// второго присеста готовы к его началу, а не к моменту, когда до них дошла лента; спасателей
  /// сегодня может не быть ни на одной карточке, а панель и разогрев их всё равно произносят.
  ///
  /// Пусто — законный ответ: труба выключена, у языка нет голоса в пакете, или файлы ещё не догнали
  /// день. Во всех трёх случаях реплики звучат системным синтезом, как звучали до наряда.
  @override
  final List<LineAudioRef> lineAudio;

  /// СЕКУНДЫ ПРОГОНА СЦЕНЫ, как их назвал сервер (наряд SCENE-RUN, Ч.2).
  final SceneRunKnobs sceneRun;

  @override
  SceneRunKnobs get sceneRunKnobs => sceneRun;

  /// The conversation of the scene taught on `dayIndex`, or null when this sitting has none.
  PlanDialogue? dialogueForDay(int dayIndex) {
    for (final dialogue in dialogues) {
      if (dialogue.dayIndex == dayIndex) return dialogue;
    }

    return null;
  }

  /// THE PAYLOAD THIS SESSION WAS PARSED FROM, kept verbatim.
  ///
  /// It is what makes a присест durable: leaving between two of them — or being killed — has to
  /// resume the SAME sitting rather than build a new one, and re-parsing the payload the server
  /// already sent is the only way to do that without asking it to deal the day again at whatever
  /// the ladder says by then. Written to the local store beside the position
  /// (`lib/data/plan_sitting_store.dart`) and never sent anywhere.
  final Map<String, dynamic> raw;

  /// The plan session as the ordinary session screen consumes it — cards in order, envelope beside.
  StudySession asStudySession() => StudySession(
    sessionId: sessionId,
    cards: tasks.map((t) => t.card).toList(growable: false),
    plan: this,
  );

  @override
  String? stageLetterAt(int i) => i >= 0 && i < tasks.length ? tasks[i].stage?.letter : null;

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
  String? shelfAt(int i) => i >= 0 && i < tasks.length ? tasks[i].shelf : null;

  @override
  bool isWarmupAt(int i) => i >= 0 && i < tasks.length && tasks[i].isWarmup;

  @override
  bool isRecognitionOnlyAt(int i) =>
      i >= 0 && i < tasks.length && tasks[i].isRecognitionOnly;

  @override
  String? sectionCodeAt(int i) => i >= 0 && i < tasks.length ? tasks[i].sectionCode : null;

  @override
  String? turnLevelAt(int i) => i >= 0 && i < tasks.length ? tasks[i].turnLevel : null;

  @override
  PlanSituation? situationAt(int i) => i >= 0 && i < tasks.length ? tasks[i].situation : null;

  @override
  bool speaksAfterChoiceAt(int i) =>
      i >= 0 && i < tasks.length && tasks[i].speaksAfterChoice;

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
    sittings: ((j['sittings'] as List?) ?? const [])
        .map((e) => (e as num).toInt())
        .toList(growable: false),
    dialogues: ((j['dialogues'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(PlanDialogue.fromJson)
        .whereType<PlanDialogue>()
        .toList(growable: false),
    lineAudio: ((j['line_audio'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map((e) => (text: (e['text'] as String?) ?? '', url: (e['url'] as String?) ?? ''))
        .where((e) => e.text.isNotEmpty && e.url.isNotEmpty)
        .toList(growable: false),
    sceneRun: SceneRunKnobs.fromJson(j['scene_run'] as Map<String, dynamic>?),
    raw: j,
    dayState: (j['day_state'] as String?) ?? PlanDayStateWire.inProgress,
    minutesLeft: (j['minutes_left'] as num?)?.toInt() ?? 0,
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

/// ONE LINE OF THE LISTENING WARM-UP — what the entry plays on кадр V4·03б.
///
/// Three fields and no id: the step is a minute long, the lines are never stored on the device and
/// never read back, and what survives it is [ListenAnswer] — the same line with the learner's own
/// «Понял» / «Не совсем» beside it, which rides to the server with `POST /plans`.
class ListenLine {
  const ListenLine({required this.text, required this.translation, required this.place});

  /// The line itself, in the studied language. This is what the TTS says.
  final String text;

  /// Shown only behind «Показать текст» — the step is about the EAR, and a translation on screen
  /// from the first second turns it into a reading exercise.
  final String translation;

  /// «на стойке», «по телефону» — 2–4 words from the model naming where it sounds. Rendered as the
  /// надзаголовок over the play button and never matched against anything: a scene the model
  /// invents needs a name the model invents. May be empty.
  final String place;

  factory ListenLine.fromJson(Map<String, dynamic> j) => ListenLine(
    text: (j['text'] as String?) ?? '',
    translation: (j['translation'] as String?) ?? '',
    place: (j['place'] as String?) ?? '',
  );
}

/// A heard line with the learner's own verdict on it.
///
/// «Понял» / «Не совсем», and neither is the right answer — the screen says so out loud. What the
/// server does with them is derive ONE decision about the plan (all understood → «упор на
/// говорение», anything else → «упор на понимание»), which is why the verdict itself is never sent:
/// a client that could send it could send one that disagrees with its own rows.
class ListenAnswer {
  const ListenAnswer({required this.line, required this.understood});

  final ListenLine line;
  final bool understood;

  Map<String, dynamic> toJson() => {
    'text': line.text,
    'translation': line.translation,
    'place': line.place,
    'understood': understood,
  };
}

/// The outcome the learner is SHOWN on кадр V4·03в/03г, and the one the server will derive again.
///
/// Computed on the device only to write the sentence on the screen and the ribbon row. The plan is
/// built on the server's own reading of the same rows — one rule, stated in two places on purpose:
/// the screen has to promise exactly what the plan will do, and a promise computed from the same
/// input as the decision cannot drift from it.
enum ListenEmphasis {
  understanding,
  speaking;

  /// «Понимаешь на слух уверенно» only when every line landed. Mixed counts as understanding: the
  /// learner said, about a line they will actually hear, that they did not quite get it.
  static ListenEmphasis of(List<ListenAnswer> answers) =>
      answers.every((a) => a.understood) ? ListenEmphasis.speaking : ListenEmphasis.understanding;
}

/// WHAT ONE WARM-UP CALL ANSWERS — and the entry asks it at two different moments.
///
/// On the GOAL step the language is not chosen yet, so [continuations] arrive alone and [lines] is
/// empty; after the language step both come back. Either list being empty always means the same
/// thing — «этого блока нет» — and never «блок сломался»: both things it feeds are optional, and
/// neither has an error state on screen.
class ListenWarmup {
  const ListenWarmup({this.lines = const [], this.continuations = const []});

  final List<ListenLine> lines;

  /// «Дописать за тебя» — the learner's own goal carried a little further, in their own language.
  /// Tapping one appends it to what they typed.
  final List<String> continuations;

  bool get isEmpty => lines.isEmpty && continuations.isEmpty;

  factory ListenWarmup.fromJson(Map<String, dynamic> j) => ListenWarmup(
    lines: ((j['lines'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(ListenLine.fromJson)
        .toList(growable: false),
    continuations: ((j['continuations'] as List?) ?? const [])
        .whereType<String>()
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .toList(growable: false),
  );
}
