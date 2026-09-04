import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_cheatsheet.dart';
import 'plan_ui.dart';

/// «ПОДГОТОВКА ЗАВЕРШЕНА» — кадр D·12, and the act that closes the plan.
///
/// The final day introduces nothing and owns no collection, so the server refuses to build it (404)
/// — and the client used to draw «Собрать день» anyway, take the 404, print «материал не прошёл
/// проверку» and stop. The plan could not be finished from the app at all; the live run closed it
/// from tinker (Д-27). Now the run-through ends here, `POST /plans/{id}/complete` runs the ordinary
/// `EndPlan(Complete)` — the same archive `abandon` performs.
///
/// ## Числа — только те, что сервер действительно знает
///
/// The frame lists four facts and the server computes two and a half of them. «Реплик в плане» is
/// not a number this product has — the census counts CARDS, of which lines are a part — and «сказали
/// сами, без ключа» and «прогон вслух 17 мин» are not measured anywhere. So the screen states the
/// three it can stand behind and leaves the rest out, rather than printing a plausible number
/// nobody computed (записка серии, «Числа»).
///
/// ## «Как прошло?» IS NOT A STUB HERE
///
/// The frame draws it as one — «спросим после приёма, механика придёт позже». It has since been
/// built ({@see PlanFeedbackScreen}, reachable from the plan screen once the event is behind), so
/// drawing a dotted placeholder over a working screen would be the app lying in the other
/// direction. The main action is the cheat sheet, exactly as the frame has it: on the day of the
/// event that is what is needed, not a new plan.
class PlanRehearsalDone extends ConsumerStatefulWidget {
  const PlanRehearsalDone({super.key, required this.planId, required this.onDone});

  final String planId;

  final VoidCallback onDone;

  @override
  ConsumerState<PlanRehearsalDone> createState() => _PlanRehearsalDoneState();
}

class _PlanRehearsalDoneState extends ConsumerState<PlanRehearsalDone> {
  /// Null while the request is in flight, true once the plan is closed, false when it could not be.
  bool? _closed;

  @override
  void initState() {
    super.initState();
    unawaited(_close());
  }

  Future<void> _close() async {
    try {
      await ref.read(apiClientProvider).completePlan(widget.planId);
      if (!mounted) return;
      // Every plan surface is read live (see providers.dart) — none of them is mirrored — so the
      // finished state reaches the tab, the home card and this plan's own screen by re-reading.
      ref.invalidate(planProvider(widget.planId));
      ref.invalidate(activePlanProvider);
      ref.invalidate(planArchiveProvider);
      setState(() => _closed = true);
    } catch (_) {
      if (mounted) setState(() => _closed = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Read live, like every other plan surface — and after `_close()` has invalidated it, so the
    // numbers below are the plan as it ENDED rather than as it was mid-run-through.
    final plan = ref.watch(planProvider(widget.planId)).value;

    return SafeArea(
      bottom: false,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenH,
          AppSpacing.s26,
          AppSpacing.screenH,
          AppSpacing.s26,
        ),
        children: [
          const SizedBox(height: 28),
          Center(child: PlanLabel(l.planFinishedBadge)),
          const SizedBox(height: 14),
          Text(
            l.planRehearsalDoneTitle,
            textAlign: TextAlign.center,
            style: AppText.displayTerm.copyWith(fontSize: 34, height: 1.15),
          ),
          const SizedBox(height: 12),
          Text(
            // The honest sentence, and only once the server has agreed. A screen that announced the
            // archive over a request that failed would be the same lie the old «слова ушли в общее
            // повторение» was (Д-30) — one screen down, in the other direction.
            _closed == false ? l.planErrorOffline : l.planRehearsalDoneBody,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(
              fontSize: 15,
              height: 1.6,
              color: AppColors.secondary,
            ),
          ),
          // THE THREE FACTS THE SERVER KNOWS — see the class docblock for the ones it does not.
          // Mono figures, no percentage: the plan's own readiness is on its card, and a second
          // number for the same thing on the screen that ends it would be a second opinion.
          if (plan != null) ...[
            const SizedBox(height: 30),
            _Fact(
              label: l.planDoneScenes,
              value: l.planDialogueCountOf(_scenesPassed(plan), _scenes(plan)),
            ),
            _Fact(label: l.planDoneCards, value: '${plan.cardsTotal}'),
            _Fact(
              label: l.planDoneStageA,
              value: l.planDialogueCountOf(plan.stageAClosed, plan.cardsTotal),
            ),
            const SizedBox(height: AppSpacing.s16),
            Text(
              l.planDoneArchiveNote,
              style: AppText.translation.copyWith(
                fontSize: 13.5,
                height: 1.55,
                color: AppColors.tertiary,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.s26),
          if (_closed == null)
            const Center(child: CircularProgressIndicator(color: AppColors.ink))
          else if (_closed == false)
            PrimaryButton(label: l.generationRetry, minHeight: 52, onPressed: () {
              setState(() => _closed = null);
              unawaited(_close());
            })
          else ...[
            // THE CHEAT SHEET IS THE MAIN ACTION (кадр D·12): «в день события нужна она, а не новый
            // план». It opens on the LAST scene — the one closest to the conversation ahead.
            if (plan != null && _scenes(plan) > 0)
              PrimaryButton(
                label: l.planDoneOpenCheatSheet,
                minHeight: 52,
                onPressed: () => showPlanCheatSheet(
                  context,
                  planId: widget.planId,
                  dayIndex: plan.introDays.last.index,
                  targetLang: plan.targetLang,
                ),
              ),
            const SizedBox(height: AppSpacing.s12),
            QuietButton(label: l.planRehearsalDoneAction, onPressed: widget.onDone),
          ],
        ],
      ),
    );
  }

  /// The plan's teaching days — its scenes. The run-through is not one of them.
  static int _scenes(LearningPlan plan) => plan.introDays.length;

  /// «Сцены пройдены 4 из 4» — counted off the day rows the server wrote, and `done` is the server's
  /// own word for a day whose stage A closed.
  static int _scenesPassed(LearningPlan plan) =>
      plan.introDays.where((d) => d.status == PlanDayStatus.done).length;
}

/// One fact of the ending — the label, and the number in the mono face.
class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});

  final String label, value;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: AppText.translation.copyWith(fontSize: 15, color: AppColors.inkBody),
          ),
        ),
        const SizedBox(width: AppSpacing.s12),
        Text(value, style: AppText.blockLabel.copyWith(fontSize: 14, color: AppColors.brassInk)),
      ],
    ),
  );
}
