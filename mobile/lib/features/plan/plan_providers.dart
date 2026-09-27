import 'dart:async';

import 'package:flutter/foundation.dart' show debugPrint;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/api_client.dart';
import '../../data/plan/plan_models.dart';
import '../../data/plan/plan_store.dart';
import '../../data/providers.dart';

/// THE TAB'S WHOLE ANSWER — the live plan (or none), the room of the day the plate is about, the
/// finished plans under the route, and whether this is the cache or the network talking.
class PlanTabState {
  const PlanTabState({
    required this.plan,
    required this.finished,
    this.room,
    this.offline = false,
  });

  /// The live plan, or null — «плана нет» (кадр 21-1).
  final Plan? plan;

  /// The room of [focusDay] — the plate's stage rows and the closed day's returns. Null while the
  /// day is still being written, or when the room could not be read.
  final PlanDayRoom? room;

  /// «Завершённые планы» (кадры 21-1, 21-2b), newest first.
  final List<PlanRow> finished;

  /// Drawn from the cache because the server did not answer — the tab says «нет сети».
  final bool offline;

  /// THE DAY THE PLATE IS ABOUT.
  ///
  /// The current day while it is today's (21-2, 21-3, 22-5); the day that was closed TODAY when
  /// the next one opens tomorrow (21-4, «День 2 закрыт» over a route whose ring already moved on);
  /// null when every day is closed (21-7) or there is no plan.
  PlanDayRoute? get focusDay => focusDayOf(plan);

  static PlanDayRoute? focusDayOf(Plan? plan) {
    if (plan == null) return null;
    final current = plan.currentDay;
    if (current != null && current.slot.code == PlanSlotCode.today) return current;
    PlanDayRoute? lastClosed;
    for (final d in plan.days) {
      if (d.isClosed) lastClosed = d;
    }
    if (lastClosed != null && current != null) return lastClosed;

    return current;
  }

  /// The plate shows the day that was just closed rather than the next one (кадр 21-4).
  bool get showsClosedDay => focusDay != null && focusDay!.isClosed;

  PlanTabState copyWith({
    Plan? plan,
    bool clearPlan = false,
    PlanDayRoom? room,
    bool clearRoom = false,
    List<PlanRow>? finished,
    bool? offline,
  }) => PlanTabState(
    plan: clearPlan ? null : (plan ?? this.plan),
    room: clearRoom ? null : (room ?? this.room),
    finished: finished ?? this.finished,
    offline: offline ?? this.offline,
  );
}

final planStoreProvider = Provider<PlanStore>((ref) => PlanStore(ref.watch(appDatabaseProvider)));

/// THE TAB'S CONTROLLER. Reads the network on every entry to the tab, falls back to the cache and
/// says so, and — while the current day's lesson is being written — polls the plan until it is.
///
/// Writes (start, reschedule, finish, delete, retry) answer with the plan they changed, and the
/// controller adopts that answer instead of asking again: one round trip, and the screen redraws
/// from the same truth the server just stated.
class PlanTabController extends AsyncNotifier<PlanTabState> {
  Timer? _poll;

  /// How often the plan is re-read while day one's lesson is still being written (кадр 22-5a).
  static const pollEvery = Duration(seconds: 3);

  @override
  Future<PlanTabState> build() async {
    ref.onDispose(() => _poll?.cancel());
    final loaded = await _load();
    _schedulePoll(loaded);

    return loaded;
  }

  ApiClient get _api => ref.read(apiClientProvider);
  PlanStore get _store => ref.read(planStoreProvider);

  Future<PlanTabState> _load() async {
    ({Plan plan, Map<String, dynamic> raw})? current;
    try {
      current = await _api.currentPlan();
    } on PlanContractError {
      // The server answered, and the answer names a state this build cannot draw: that is not
      // «нет сети», and yesterday's cache would show a route that is no longer true.
      rethrow;
    } catch (e) {
      debugPrint('[plan-tab] current plan: $e');
      final cached = await _store.readCurrent();
      if (!cached.known) rethrow;
      final plan = cached.raw == null ? null : Plan.fromJson(cached.raw!);

      return PlanTabState(plan: plan, finished: const [], offline: true);
    }
    await _store.writeCurrent(current?.raw);
    final plan = current?.plan;

    return PlanTabState(
      plan: plan,
      room: await _roomFor(plan),
      finished: await _finished(),
    );
  }

  /// The finished plans — a second call, and one whose failure must not cost the tab its plan.
  Future<List<PlanRow>> _finished() async {
    try {
      final rows = await _api.plans();

      return [for (final r in rows) if (r.status == PlanStatus.finished) r];
    } catch (e) {
      debugPrint('[plan-tab] finished plans: $e');

      return const [];
    }
  }

  Future<PlanDayRoom?> _roomFor(Plan? plan) async {
    final day = PlanTabState.focusDayOf(plan);
    if (plan == null || day == null || day.lessonBuilding || day.lessonFailed) return null;
    try {
      return await _api.planDayRoom(plan.id, day.number);
    } catch (e) {
      debugPrint('[plan-tab] day room: $e');

      return null;
    }
  }

  /// Poll while the plate is in its shimmer (22-5a): the server writes day one on its own, the
  /// client only asks whether it has.
  void _schedulePoll(PlanTabState s) {
    _poll?.cancel();
    final day = s.focusDay;
    if (s.plan == null || day == null || !day.lessonBuilding) return;
    _poll = Timer(pollEvery, () => unawaited(refresh(silent: true)));
  }

  /// Re-read the plan. [silent] keeps the current state on screen while the answer is on its way
  /// (a poll tick, a return to the tab) instead of flashing a spinner.
  Future<void> refresh({bool silent = true}) async {
    // The first build is already on its way — a silent re-read on top of it would be the same
    // request twice for one answer.
    if (silent && state.isLoading) return;
    if (!silent) state = const AsyncLoading();
    final next = await AsyncValue.guard(_load);
    if (!silent || next.hasValue) state = next;
    final value = state.value;
    if (value != null) _schedulePoll(value);
  }

  /// Adopt the plan a write answered with, and re-read the room it is about.
  Future<void> adopt(Plan plan) async {
    await _store.writeCurrent(plan.raw);
    final previous = state.value;
    state = AsyncData(
      PlanTabState(plan: plan, room: await _roomFor(plan), finished: previous?.finished ?? const []),
    );
    _schedulePoll(state.value!);
  }

  /// The plan is gone — deleted, or finished and no longer live.
  Future<void> forget() async {
    await _store.writeCurrent(null);
    state = AsyncData(PlanTabState(plan: null, finished: await _finished()));
    _poll?.cancel();
  }

  Future<Plan> start(String planId) async {
    final plan = await _api.startPlan(planId);
    await adopt(plan);

    return plan;
  }

  Future<void> reschedule(String planId, {required bool changeDate, String? eventDate}) async =>
      adopt(await _api.reschedulePlan(planId, changeDate: changeDate, eventDate: eventDate));

  Future<void> finish(String planId) async {
    await _api.finishPlan(planId);
    await forget();
  }

  Future<void> delete(String planId) async {
    await _api.deletePlan(planId);
    await forget();
  }

  /// «Повторить» on a day whose lesson failed (кадр 22-5c).
  Future<void> retryLesson(String planId, String sceneId) async =>
      adopt(await _api.retryPlanLesson(planId, sceneId));
}

final planTabProvider = AsyncNotifierProvider<PlanTabController, PlanTabState>(
  PlanTabController.new,
);

/// THE PLAN THE APP ALREADY HOLDS — the tab's last answer, without waking the tab: the profile's reminder time (42-1) and
/// the delete sheet's plan name (42-3) read it; a screen that is not under the tabs (a test, a pushed profile before the
/// tab was ever built) gets null rather than a network read of its own.
Plan? heldPlan(WidgetRef ref) => ref.exists(planTabProvider) ? ref.watch(planTabProvider).value?.plan : null;

/// A finished plan opened from the list — read live, whole (кадр 21-7 in its reading mode).
final finishedPlanProvider = FutureProvider.family<Plan, String>(
  (ref, id) => ref.read(apiClientProvider).plan(id),
);

/// The one-time hints of the tab (кадр 21-2c), the first closing's (21-4c), the sheet (21-8).
final planHintsProvider = AsyncNotifierProvider<PlanHintsController, PlanHints>(
  PlanHintsController.new,
);

class PlanHints {
  const PlanHints({required this.tabShown, required this.closeShown, required this.howShown});

  final bool tabShown;
  final bool closeShown;
  final bool howShown;

  PlanHints copyWith({bool? tabShown, bool? closeShown, bool? howShown}) => PlanHints(
    tabShown: tabShown ?? this.tabShown,
    closeShown: closeShown ?? this.closeShown,
    howShown: howShown ?? this.howShown,
  );
}

class PlanHintsController extends AsyncNotifier<PlanHints> {
  @override
  Future<PlanHints> build() async {
    final store = ref.read(planStoreProvider);

    return PlanHints(
      tabShown: await store.tabHintsShown(),
      closeShown: await store.closeHintShown(),
      howShown: await store.howSheetShown(),
    );
  }

  PlanHints get _now =>
      state.value ?? const PlanHints(tabShown: false, closeShown: false, howShown: false);

  Future<void> markTabShown() async {
    await ref.read(planStoreProvider).markTabHintsShown();
    state = AsyncData(_now.copyWith(tabShown: true));
  }

  Future<void> markCloseShown() async {
    await ref.read(planStoreProvider).markCloseHintShown();
    state = AsyncData(_now.copyWith(closeShown: true));
  }

  Future<void> markHowShown() async {
    await ref.read(planStoreProvider).markHowSheetShown();
    state = AsyncData(_now.copyWith(howShown: true));
  }
}

/// ЗАПЕРТЫЙ ДЕНЬ, ЧЬЮ ПРИЧИНУ ТАБ ДОЛЖЕН НАЗВАТЬ (наряд PLAN-UI-3 §1).
///
/// Запертый день не открывается ни с какой двери: тап по нему на маршруте, ссылка
/// `engstd://plan/day/{id}`, уведомление и 409 `plan_day_locked` от сервера приводят сюда, и
/// маршрут дописывает в мету этого дня «откроется после дня N» — строкой, без экрана.
class PlanExplainDay extends Notifier<int?> {
  @override
  int? build() => null;

  void explain(int number) => state = number;
}

final planExplainDayProvider = NotifierProvider<PlanExplainDay, int?>(PlanExplainDay.new);

/// ДЕНЬ, К КОТОРОМУ ТАБ ДОЛЖЕН ПРОКРУТИТЬ МАРШРУТ — тап по уведомлению открывает вкладку «План» на
/// нужном дне (наряд PLAN-UI-3 §4). Номер и порядковый счётчик: второй тап по тому же дню тоже
/// прокручивает.
class PlanFocusDay extends Notifier<({int day, int seq})?> {
  @override
  ({int day, int seq})? build() => null;

  void focus(int day) => state = (day: day, seq: (state?.seq ?? 0) + 1);
}

final planFocusDayProvider = NotifierProvider<PlanFocusDay, ({int day, int seq})?>(PlanFocusDay.new);
