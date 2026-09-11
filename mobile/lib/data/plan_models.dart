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

/// ОДНО СЛОВО О ДНЕ — «не начат» · «идёт» · «материал пройден» · «пройден» (наряд DAY-FIX-2,
/// Ч.3; DAY-FIX-3, Ч.4.3).
///
/// Считает СЕРВЕР, и только он: вкладка «План», экран дня и шапка присеста читают это поле, и ни
/// один из них не держит счётчика «осталось N». Отдельно от [PlanDayStatus], который про СБОРКУ
/// дня: `ready` день может быть нетронутым или пройденным наполовину.
enum PlanDayState {
  notStarted,
  inProgress,

  /// Присест «Материал» пройден, впереди «Разговор».
  materialDone,
  done;

  /// Открытый набор: код, которого эта сборка не знает, читается как «идёт»; поля нет вовсе
  /// (сервер до DAY-FIX-2) — день ещё не начат, потому что о нём ничего не известно.
  static PlanDayState fromWire(String? v) => switch (v) {
    null || 'not_started' => PlanDayState.notStarted,
    'material_done' => PlanDayState.materialDone,
    'done' => PlanDayState.done,
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

/// ЭТАП ДНЯ — из чего день состоит (наряд DAY-GATE-1, Ч.1.1 на сервере, Ч.2.1 здесь).
///
/// Сервер везёт КОДЫ, подписи клиентские и в двух языках, как у всех кодов плана (Д-19). Набор
/// открытый: этап, которого эта сборка не знает, честнее показать как есть, чем уронить экран или
/// молча выбросить — выброшенный этап превратил бы «день не пройден» в «день пройден».
enum PlanStageCode {
  material,
  conversation,
  rehearsal,
  retrain,
  unknown;

  static PlanStageCode fromWire(String? wire) => switch (wire) {
    'material' => material,
    'conversation' => conversation,
    'rehearsal' => rehearsal,
    'retrain' => retrain,
    _ => unknown,
  };

  String get wire => this == unknown ? '' : name;

  /// «Повторить ошибки» дня не держит и «пройден» от него не зависит — так решает СЕРВЕР, и это
  /// единственное место на клиенте, где про этот этап знают что-то кроме подписи.
  bool get isOptional => this == retrain;
}

/// `done` | `current` | `locked`. Ровно один `current` среди трёх обязательных.
enum PlanStageState {
  done,
  current,
  locked;

  static PlanStageState fromWire(String? wire) => switch (wire) {
    'done' => done,
    'current' => current,
    // Неизвестное состояние — самое закрытое: экран, который угадал «сейчас», отправил бы человека
    // в 409.
    _ => locked,
  };
}

/// Один этап дня на пейлоаде: код, состояние и «после какого откроется».
class PlanDayStage {
  const PlanDayStage({required this.stage, required this.state, this.opensAfter});

  final PlanStageCode stage;
  final PlanStageState state;

  /// Этап, после которого этот откроется, или null. Едет ВСЕГДА, а не только у запертого: строка
  /// «после чего» — свойство этапа, а не его состояния.
  final PlanStageCode? opensAfter;

  factory PlanDayStage.fromJson(Map<String, dynamic> j) => PlanDayStage(
    stage: PlanStageCode.fromWire(j['stage'] as String?),
    state: PlanStageState.fromWire(j['state'] as String?),
    opensAfter: j['opens_after'] == null
        ? null
        : PlanStageCode.fromWire(j['opens_after'] as String?),
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
    this.materialMinutes = 0,
    this.conversationMinutes = 0,
    this.stages = const [],
    this.lockedByDayIndex,
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

  /// МИНУТЫ ДВУХ ПРИСЕСТОВ врозь — «материал около 12 минут · разговор около 6» (наряд DAY-FIX-3,
  /// Ч.5.1). Ноль у присеста, который пройден, и на сервере, который поля не знает.
  final int materialMinutes, conversationMinutes;

  /// ИЗ ЧЕГО СОСТОИТ ДЕНЬ и что с каждой частью сегодня (наряд DAY-GATE-1). Пустой список — день
  /// со старого сервера: экран тогда блока этапов не рисует вовсе, а не выдумывает его из
  /// `day_state`. ЧИСЕЛ ЗДЕСЬ НЕТ НАМЕРЕННО — «N из M» на экранах плана не бывает.
  final List<PlanDayStage> stages;

  /// Номер дня, который держит этот закрытым, или null (Ч.1.2). ВЫВОДИТЬ ЗАМОК САМОСТОЯТЕЛЬНО
  /// НЕЛЬЗЯ: поле уже на пейлоаде, а второе правило про ту же дверь однажды разошлось бы с первым —
  /// и разошлось бы в сторону 409 у человека на экране.
  final int? lockedByDayIndex;

  bool get isLocked => lockedByDayIndex != null;

  /// Обязательные этапы дня — те, от которых зависит «пройден». `retrain` в это число не входит:
  /// так решает сервер, и здесь это только прочитано.
  List<PlanDayStage> get requiredStages =>
      stages.where((s) => !s.stage.isOptional).toList(growable: false);

  /// Этап, который открыт прямо сейчас, или null. Ровно один среди обязательных — если сервер
  /// прислал иначе, берётся первый: угадывать второй экран не станет.
  PlanDayStage? get currentStage {
    for (final stage in requiredStages) {
      if (stage.state == PlanStageState.current) return stage;
    }

    return null;
  }

  /// «Повторить ошибки» — приходит в списке, только когда есть что повторять.
  PlanDayStage? get retrainStage {
    for (final stage in stages) {
      if (stage.stage == PlanStageCode.retrain) return stage;
    }

    return null;
  }

  /// Обязательные этапы, которые ещё не пройдены, — в порядке пейлоада. Это и есть «что осталось
  /// до следующего дня»: следующий день встаёт в очередь по факту «день N пройден» (решение 294).
  List<PlanDayStage> get stagesLeft =>
      requiredStages.where((s) => s.state != PlanStageState.done).toList(growable: false);

  /// День пройден насквозь: все обязательные этапы закрыты (решение 291). Читается по этапам, а не
  /// по `day_state`, потому что вопрос «что осталось» задаётся именно про этапы; когда этапов на
  /// пейлоаде нет, ответа нет — и экран строку не печатает.
  bool get allStagesDone => requiredStages.isNotEmpty && stagesLeft.isEmpty;

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
    materialMinutes: (j['material_minutes'] as num?)?.toInt() ?? 0,
    conversationMinutes: (j['conversation_minutes'] as num?)?.toInt() ?? 0,
    stages: ((j['stages'] as List?) ?? const [])
        .map((e) => PlanDayStage.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
    lockedByDayIndex: (j['locked_by_day_index'] as num?)?.toInt(),
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

List<String> _strings(Object? v) =>
    [for (final e in (v as List?) ?? const []) if (e is String && e.trim().isNotEmpty) e];
