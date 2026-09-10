import '../../../data/plan/plan_models.dart';

/// THE ENTRY'S ANSWERS AND WHERE THE BUILD STANDS — one value the four steps read and write.
///
/// A plain immutable state held by the entry screen; the steps are pure functions of it. What the
/// server owns (the plan, its build status) sits beside what the learner typed, so «Изм.» keeps the
/// answers and a re-build asks for a NEW plan from the same answers.
enum EntryStep { goal, language, days, preview }

enum EntryBuildPhase {
  /// No plan asked for yet — or the answers changed since the last one.
  idle,

  /// `POST /plans` sent, `GET /plans/{id}/build` polled (кадр 22-4a).
  building,

  /// `ready` — the route is on screen (22-4b).
  ready,

  /// `unclear` — the goal names no situation (22-4d).
  unclear,

  /// `failed`, or the server did not answer at all (22-4c).
  failed,

  /// «Начать» sent and awaited.
  starting,
}

class EntryState {
  const EntryState({
    this.step = EntryStep.goal,
    this.goal = '',
    this.chip,
    this.targetLang = 'en',
    this.level = PlanLevel.beginner,
    this.days = 5,
    this.requestedDays = 5,
    this.dateEnabled = false,
    this.eventDate,
    this.phase = EntryBuildPhase.idle,
    this.plan,
    this.planId,
    this.offline = false,
  });

  final EntryStep step;
  final String goal;

  /// The chip whose template filled the field, if any (кадр 22-1b).
  final EntryGoalChip? chip;
  final String targetLang;
  final PlanLevel level;

  /// The EFFECTIVE days — 1 · 3 · 5 · 7 · 10 (кадр 22-3a), or what an early date leaves of them.
  final int days;

  /// What the learner CHOSE. The date may shorten [days] below it (22-3b: «чип переезжает с 5 на
  /// 3»), and the brass line says so as long as the two differ.
  final int requestedDays;
  final bool dateEnabled;
  final DateTime? eventDate;
  final EntryBuildPhase phase;

  /// The plan the server answered with, once the build is `ready`.
  final Plan? plan;

  /// The plan being built or retried.
  final String? planId;

  /// The last build attempt never reached the server (§6: без сети сборку не начать).
  final bool offline;

  static const dayChoices = [1, 3, 5, 7, 10];

  /// The date the plan is asked with — only when the toggle is on.
  DateTime? get effectiveDate => dateEnabled ? eventDate : null;

  bool get goalFilled => goal.trim().isNotEmpty;

  EntryState copyWith({
    EntryStep? step,
    String? goal,
    EntryGoalChip? chip,
    bool clearChip = false,
    String? targetLang,
    PlanLevel? level,
    int? days,
    int? requestedDays,
    bool? dateEnabled,
    DateTime? eventDate,
    bool clearDate = false,
    EntryBuildPhase? phase,
    Plan? plan,
    bool clearPlan = false,
    String? planId,
    bool? offline,
  }) => EntryState(
    step: step ?? this.step,
    goal: goal ?? this.goal,
    chip: clearChip ? null : (chip ?? this.chip),
    targetLang: targetLang ?? this.targetLang,
    level: level ?? this.level,
    days: days ?? this.days,
    requestedDays: requestedDays ?? this.requestedDays,
    dateEnabled: dateEnabled ?? this.dateEnabled,
    eventDate: clearDate ? null : (eventDate ?? this.eventDate),
    phase: phase ?? this.phase,
    plan: clearPlan ? null : (plan ?? this.plan),
    planId: clearPlan ? null : (planId ?? this.planId),
    offline: offline ?? this.offline,
  );
}

/// The five chips under the goal field (кадр 22-1).
enum EntryGoalChip { doctor, rent, interview, trip, other }
