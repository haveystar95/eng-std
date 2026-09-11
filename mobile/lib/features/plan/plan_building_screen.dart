import 'dart:async';

import 'package:dio/dio.dart' show DioException;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/plan/plan_providers.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'day/open_day.dart';
import 'plan_ui.dart';

/// «Собираю день N» — кадр Б-06.
///
/// The dark plate of the preview STAYS on screen and becomes the status: the same surface, the same
/// place, new words in it. That is what makes «Начать» feel like one continuous act rather than a
/// jump to a loading screen, and it is the reason this is a plate and not a spinner.
///
/// Three steps and no percentage. A generation that takes forty seconds has nothing honest to say
/// about how far along it is — the model is either still writing or it is not — so the screen names
/// WHAT IS HAPPENING instead. The steps are derived from the day's own status, which is the only
/// thing the server actually reports:
///
///   pending / (no day yet)   → «Цель разобрана» alone: the outline is done, the day is queued
///   generating               → and «Реплики приёма подобраны»
///   ready / done             → all three, and the screen leaves
///
/// Оно ОПРАШИВАЕТ `GET /plans/current` и читает `lesson_status` дня: урок собирает сервер сам,
/// клиент ничего не запускает (контракт PLAN-GEN, стык DAY-UI).
class PlanBuildingScreen extends ConsumerStatefulWidget {
  const PlanBuildingScreen({super.key, required this.plan, this.dayIndex});

  final LearningPlan plan;

  /// Which day is being written. Defaults to the plan's own focus — day 1 right after «Начать»,
  /// and the day the learner asked for when they opened one ahead of the focus.
  final int? dayIndex;

  @override
  ConsumerState<PlanBuildingScreen> createState() => _PlanBuildingScreenState();
}

class _PlanBuildingScreenState extends ConsumerState<PlanBuildingScreen> {
  /// How long between polls. The model takes tens of seconds; a second-by-second poll would be a
  /// request per second for a fact that changes once.
  static const _interval = Duration(seconds: 3);

  /// A ceiling, because a job that never finishes must not leave the learner on a screen that says
  /// «собираю» forever. Generous — a day is two model calls with retries behind them.
  static const _giveUpAfter = Duration(minutes: 4);

  Timer? _poll;
  DateTime? _startedAt;
  PlanDayStatus _status = PlanDayStatus.pending;
  bool _failed = false;

  /// The server REFUSED rather than took too long — a failed day or an answered error. The two need
  /// different sentences: «дольше обычного» invites waiting, and here there is nothing to wait for.
  bool _refused = false;

  bool _left = false;

  int get _dayIndex => widget.dayIndex ?? widget.plan.focusDayIndex;

  @override
  void initState() {
    super.initState();
    _startedAt = DateTime.now();
    unawaited(_tick());
    _poll = Timer.periodic(_interval, (_) => unawaited(_tick()));
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _tick() async {
    if (!mounted || _left) return;
    try {
      // Урок дня собирает сервер сам (PLAN-GEN); здесь только читаем его состояние из плана.
      final current = await ref.read(apiClientProvider).currentPlan();
      final day = current?.days.where((d) => d.number == _dayIndex).firstOrNull ?? current?.currentDay;
      final status = switch (day?.lessonStatus) {
        'ready' => PlanDayStatus.ready,
        'failed' => PlanDayStatus.failed,
        'building' || 'generating' => PlanDayStatus.generating,
        _ => PlanDayStatus.pending,
      };
      if (!mounted) return;
      setState(() => _status = status);

      if (status.hasMaterial) {
        _leaveFor(const Duration(milliseconds: 450));
      } else if (status == PlanDayStatus.failed) {
        // The day came back FAILED, which is an answer and not a delay. Waiting longer cannot
        // change it — and after two claims the server will not try again at all.
        setState(() {
          _failed = true;
          _refused = true;
        });
        _poll?.cancel();
      } else if (DateTime.now().difference(_startedAt!) > _giveUpAfter) {
        setState(() => _failed = true);
        _poll?.cancel();
      }
    } catch (e) {
      if (!mounted) return;
      // WHICH FAILURES ARE WORTH WAITING THROUGH, and it is a short list.
      //
      // A dropped request is: the tunnel blinks, the phone changes network, the server restarts —
      // the timer tries again in three seconds and the learner sees nothing, which is right.
      //
      // Everything the SERVER answers is not. A 409 is the plan's own spending ceiling saying «not
      // this day, not yet»; a 404 is a day that cannot be built at all; a 422 is a refusal. Polling
      // through any of them leaves the plate saying «собираю день 1» for four minutes over a
      // question that was already answered — which is exactly how this screen hangs.
      final status = e is DioException ? e.response?.statusCode : null;
      if (status != null && status != 429 && status < 500) {
        debugPrint('[plan-building] day $_dayIndex: server answered $status');
        setState(() {
          _failed = true;
          _refused = true;
        });
        _poll?.cancel();
      }
    }
  }

  /// Leave after a beat, so the third step is actually SEEN ticking rather than flashing on the way
  /// out — and leave for WHERE THE LEARNER CAME FROM.
  ///
  /// Two entrances, two destinations, and conflating them is a screen that throws you out of the
  /// day you were reading:
  ///
  ///  * «Начать» on the preview ([widget.dayIndex] is null — the server owns the focus) lands on the
  ///    plan, exactly as кадр Б-06 says: the learner has just bought the whole thing and the plan is
  ///    what they bought.
  ///  * «Собрать день N» from a DAY names its day, and that day is what the learner asked to see.
  ///    Sending them to the plan instead is the app answering a different question — reported from
  ///    the owner's phone as «оно выкидывает меня со 2 дня».
  void _leaveFor(Duration delay) {
    if (_left) return;
    _left = true;
    _poll?.cancel();
    Future.delayed(delay, () {
      if (!mounted) return;
      ref.invalidate(activePlanProvider);
      ref.invalidate(planProvider(widget.plan.id));
      ref.invalidate(currentPlanProvider);
      // Кабинет дня (DAY-UI): «Начать» без номера — текущий день плана, «Собрать день N» — тот день.
      final navigator = Navigator.of(context);
      navigator.pop();
      unawaited(openDayRoom(context, ref, number: widget.dayIndex));
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Which of the three steps are lit. The first is done the moment this screen exists — the
    // outline the learner just read IS «цель разобрана».
    final done = switch (_status) {
      PlanDayStatus.pending => 1,
      PlanDayStatus.generating => 2,
      PlanDayStatus.ready || PlanDayStatus.done => 3,
      PlanDayStatus.failed => 1,
    };

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, AppSpacing.s22, 14, AppSpacing.s26),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // БУМАЖНАЯ ГАММА (наряд DAY-GATE-1, Ч.2.6). Тёмная плита осталась от превью плана,
                // где она была продолжением одного жеста «Начать»; экран СБОРКИ ДНЯ открывается сам
                // — из строки дня, из «Продолжить», из уведомления, — и тёмная плита посреди
                // светлого плана читается как чужой экран. В серии тёмных плит нет.
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    PlanLabel(l.planBuildingTitle(_dayIndex)),
                    const SizedBox(height: AppSpacing.s12),
                    // ЦЕЛЬ ОБЫЧНЫМ ТЕКСТОМ, не более двух строк: это напоминание, ради чего человек
                    // ждёт, а не заголовок экрана. Длинная цель, набранная крупно, съедала экран и
                    // выталкивала шаги за его край.
                    Text(
                      widget.plan.goalText.isEmpty ? widget.plan.title : widget.plan.goalText,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.collectionNameScreen.copyWith(fontSize: 22, height: 1.25),
                    ),
                    const SizedBox(height: AppSpacing.s12),
                    Text(
                      l.planBuildingBody,
                      style: AppText.translation.copyWith(
                        fontSize: 14,
                        height: 1.6,
                        color: AppColors.secondary,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.s22),
                    // Exactly ONE row is «current» — the first one not yet done — and only while
                    // the work is still running. A pulse on a screen that has given up would be
                    // the screen saying it is still trying.
                    _Step(label: l.planBuildingStep1, done: done >= 1, current: !_failed && done < 1),
                    const SizedBox(height: 10),
                    _Step(label: l.planBuildingStep2, done: done >= 2, current: !_failed && done == 1),
                    const SizedBox(height: 10),
                    _Step(label: l.planBuildingStep3, done: done >= 3, current: !_failed && done == 2),
                  ],
                ),
                const Spacer(),
                if (_failed) ...[
                  Text(
                    _refused ? l.planBuildingRefused : l.planBuildingFailed,
                    textAlign: TextAlign.center,
                    style: AppText.translation.copyWith(
                      fontSize: 14,
                      height: 1.5,
                      color: AppColors.secondary,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.s16),
                  // The plan EXISTS — it was started, and its later days are queued. So the way out
                  // is the plan screen, which can say what state each day is really in, and not a
                  // dead end with a retry that re-posts the same idempotent call.
                  PrimaryButton(
                    label: l.planBuildingOpenAnyway,
                    minHeight: 52,
                    onPressed: () => _leaveFor(Duration.zero),
                  ),
                ],
                const SizedBox(height: AppSpacing.s22),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({required this.label, required this.done, this.current = false});
  final String label;
  final bool done;

  /// This is the step being worked on right now. It pulses; the ones behind it are solid brass and
  /// the ones ahead are quiet outlines.
  final bool current;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      SizedBox(
        width: 15,
        height: 15,
        child: current
            ? const PlanPulsingDot(color: AppColors.brassInk)
            : AnimatedContainer(
                duration: AppMotion.segmentFill,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: done ? AppColors.brassInk : null,
                  border: done ? null : Border.all(color: AppColors.dividerFaint, width: 1.5),
                ),
              ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Text(
          label,
          style: AppText.translation.copyWith(
            fontSize: 14,
            // The current row reads as brightly as a finished one: it is where the learner should
            // be looking, and a dimmed «happening now» is a contradiction.
            color: done || current ? AppColors.ink : AppColors.tertiary,
          ),
        ),
      ),
    ],
  );
}
