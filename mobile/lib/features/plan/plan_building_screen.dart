import 'dart:async';

import 'package:dio/dio.dart' show DioException;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_screen.dart';
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
/// It POLLS `POST /plans/{id}/days/{n}/generate`, which is idempotent and answers with the status —
/// so the same call both starts the work and reports on it, and a retry after a dropped connection
/// cannot start a second generation.
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
      final status = await ref
          .read(apiClientProvider)
          .generatePlanDay(widget.plan.id, _dayIndex);
      if (!mounted) return;
      setState(() => _status = status);

      if (status.hasMaterial) {
        _leaveFor(const Duration(milliseconds: 450));
      } else if (status == PlanDayStatus.failed) {
        setState(() => _failed = true);
        _poll?.cancel();
      } else if (DateTime.now().difference(_startedAt!) > _giveUpAfter) {
        setState(() => _failed = true);
        _poll?.cancel();
      }
    } catch (e) {
      if (!mounted) return;
      // A 409 means the plan's own spending ceiling says «not this day, not yet» — which is a real
      // answer and not a failure to retry at. Everything else offline-ish is retried by the timer.
      if (e is DioException && e.response?.statusCode == 409) {
        setState(() => _failed = true);
        _poll?.cancel();
      }
    }
  }

  /// Leave for the plan screen after a beat, so the third step is actually SEEN ticking rather than
  /// flashing on the way out.
  void _leaveFor(Duration delay) {
    if (_left) return;
    _left = true;
    _poll?.cancel();
    Future.delayed(delay, () {
      if (!mounted) return;
      ref.invalidate(activePlanProvider);
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => PlanScreen(planId: widget.plan.id)),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final paper = AppColors.paper;
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
                PlanPlate(
                  padding: const EdgeInsets.fromLTRB(22, 26, 22, 26),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      PlanLabel(widget.plan.title, color: AppColors.brass),
                      const SizedBox(height: AppSpacing.s12),
                      Text(
                        l.planBuildingTitle(_dayIndex),
                        style: AppText.displayTerm.copyWith(
                          color: paper,
                          fontSize: 28,
                          height: 1.2,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.s12),
                      Text(
                        l.planBuildingBody,
                        style: AppText.translation.copyWith(
                          fontSize: 14,
                          height: 1.6,
                          color: paper.withValues(alpha: 0.78),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.s22),
                      _Step(label: l.planBuildingStep1, done: done >= 1),
                      const SizedBox(height: 10),
                      _Step(label: l.planBuildingStep2, done: done >= 2),
                      const SizedBox(height: 10),
                      _Step(label: l.planBuildingStep3, done: done >= 3),
                    ],
                  ),
                ),
                const Spacer(),
                if (_failed) ...[
                  Text(
                    l.planBuildingFailed,
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
  const _Step({required this.label, required this.done});
  final String label;
  final bool done;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      AnimatedContainer(
        duration: AppMotion.segmentFill,
        width: 15,
        height: 15,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: done ? AppColors.brass : null,
          border: done
              ? null
              : Border.all(color: AppColors.paper.withValues(alpha: 0.4), width: 1.5),
        ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Text(
          label,
          style: AppText.translation.copyWith(
            fontSize: 14,
            color: AppColors.paper.withValues(alpha: done ? 1 : 0.55),
          ),
        ),
      ),
    ],
  );
}
