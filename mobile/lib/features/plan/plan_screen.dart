import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/api_client.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_day_screen.dart';
import 'plan_feedback_screen.dart';
import 'plan_rehearsal_screen.dart';
import 'plan_tab_screen.dart' show abandonPlan;
import 'plan_ui.dart';

/// THE ACTIVE PLAN — кадр 1c · 01, plus the readiness block the наряд asks for.
///
/// The headline number is READINESS TO THE EVENT and not «слов пройдено», and the difference is the
/// whole product: a learner walking into an appointment does not care how many cards they answered,
/// they care whether they can say the six things they came to say. The word count («14 из 18») is
/// demoted to a service line under the bar for exactly that reason.
///
/// Readiness is the server's, verbatim: `0.6 × чек-пойнты, сказанные вслух + 0.4 × слова на ступени
/// C`. Its first half is a literal zero until CONV-1 writes the first conversation, so a fresh plan
/// reads a small number and «Ты уже можешь» reads 0 из 6. That is honest — nothing has been said out
/// loud to anybody yet — and the number can only ever grow, never be revised down.
class PlanScreen extends ConsumerWidget {
  const PlanScreen({super.key, required this.planId, this.embedded = false});

  final String planId;

  /// Drawn INSIDE the План tab rather than pushed on top of it: no back chevron, and the bottom
  /// padding leaves room for the floating tab pill.
  final bool embedded;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final plan = ref.watch(planProvider(planId));

    final body = plan.when(
      loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
      error: (e, _) => PlanNotice(
        text: isOffline(e) ? l.planErrorOffline : l.planErrorLoadFailed,
        actionLabel: l.generationRetry,
        onAction: () => ref.invalidate(planProvider(planId)),
      ),
      data: (p) => _PlanBody(plan: p, embedded: embedded),
    );

    if (embedded) return body;

    return Scaffold(
      backgroundColor: AppColors.paper,
      body: SafeArea(bottom: false, child: body),
    );
  }
}

class _PlanBody extends ConsumerWidget {
  const _PlanBody({required this.plan, required this.embedded});

  final LearningPlan plan;
  final bool embedded;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final bottomInset = embedded
        ? AppTabBarMetrics.height +
              AppTabBarMetrics.bottomInset +
              MediaQuery.viewPaddingOf(context).bottom +
              AppSpacing.s8
        : AppSpacing.s26;
    final focus = plan.focusDay;

    return RefreshIndicator(
      color: AppColors.ink,
      backgroundColor: AppColors.surfaceRaised,
      onRefresh: () async {
        ref.invalidate(planProvider(plan.id));
        ref.invalidate(activePlanProvider);
        await ref.read(planProvider(plan.id).future);
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(14, AppSpacing.s8, 14, bottomInset),
        children: [
          _ReadinessPlate(plan: plan),
          const SizedBox(height: AppSpacing.s22),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // «ТЫ УЖЕ МОЖЕШЬ · 0 из 6» IS NOT DRAWN UNTIL IT CAN BE ANYTHING BUT ZERO.
                //
                // A checkpoint is confirmed by being SAID in the conversation without a prompt, and
                // there is no conversation until CONV-1 — so every `hit` is false by construction
                // and the header counts to zero for the whole life of every plan. A block that can
                // only ever say «0 из 6» reads as failure over work that was done.
                //
                // The rows are not lost with it: each «Потренировать» opened the DAY that teaches
                // the checkpoint, and the day list two lines below is the same set of days. The data
                // stays on the wire (`can_already`) — the screen is built against its shape and does
                // not have to be rewritten when CONV-1 lands; only this `if` goes away.
                if (plan.canAlready.any((c) => c.hit)) ...[
                  PlanLabel(
                    l.planCanAlready(plan.checkpointsHit, plan.canAlready.length),
                    color: AppColors.tertiary,
                    fontSize: 11.5,
                  ),
                  const SizedBox(height: AppSpacing.s8),
                  for (final checkpoint in plan.canAlready)
                    PlanAbilityRow(
                      text: checkpoint.text,
                      hit: checkpoint.hit,
                      // An unmet ability is not a complaint — it is a door. Tapping «Потренировать»
                      // opens the DAY that teaches it.
                      trailing: checkpoint.hit
                          ? null
                          : _RowLink(
                              label: l.planTrainThis,
                              onTap: () => _openDay(context, plan, checkpoint.dayIndex),
                            ),
                    ),
                  const SizedBox(height: AppSpacing.s22),
                ],
                PlanLabel(
                  l.planDaysHeader(plan.focusDayIndex, plan.days.length),
                  color: AppColors.tertiary,
                  fontSize: 11.5,
                ),
                const SizedBox(height: 10),
                for (final day in plan.days)
                  _DayRow(
                    plan: plan,
                    day: day,
                    onTap: () => _openDay(context, plan, day.index),
                  ),
                const SizedBox(height: AppSpacing.s22),
                // THE EVENT HAS HAPPENED and the plan is still running: the one thing left to do is
                // say how it went. It is the same screen the evening notification opens, offered
                // here for the learner who never tapped it — otherwise a plan whose appointment is
                // over goes on holding its words out of the ordinary day, indefinitely.
                // An undated plan has no event to have happened, so it is never offered «как прошло».
                if ((plan.daysToEvent ?? 1) <= 0) ...[
                  PrimaryButton(
                    label: l.planFeedbackTitle,
                    minHeight: 52,
                    onPressed: () => _openFeedback(context, ref, plan),
                  ),
                  const SizedBox(height: AppSpacing.s12),
                ],
                if (plan.daysToEvent == 0) ...[
                  QuietButton(
                    label: l.planRehearsalOpen,
                    onPressed: () => _openRehearsal(context, plan),
                  ),
                  const SizedBox(height: AppSpacing.s12),
                ],
                // «Продолжить день N» is a promise that there is a day to continue. A BURNED focus
                // day has no material and never will, and the live run's plan offered the button
                // over one — the learner only found out by pressing it (Д-20). The day screen owns
                // what to do about a failure, so the row leads there and says so, in its own words.
                if (focus != null)
                  focus.status == PlanDayStatus.failed
                      ? PrimaryButton(
                          label: l.planDayOpenFailed(focus.index),
                          minHeight: 52,
                          onPressed: () => _openDay(context, plan, focus.index),
                        )
                      : PrimaryButton(
                          label: l.planContinueDay(focus.index),
                          minHeight: 52,
                          onPressed: () => _openDay(context, plan, focus.index),
                        ),
                // THE WAY OUT, and the only one there is. The server allows one running plan per
                // learner, so without this the only exit is the plan's own event. Quiet terracotta
                // text under the action, the app's established shape for a destructive act (rule
                // 20: no fill) — «Фаза 4» draws no such control, and a product that can be entered
                // and not left is worse than a frame with one more link on it.
                const SizedBox(height: AppSpacing.s22),
                Center(
                  child: MinTapHeight(
                    onTap: () => abandonPlan(context, ref, plan.id),
                    child: Text(
                      l.planAbandonLink,
                      style: AppText.translation.copyWith(
                        fontSize: 14,
                        color: AppColors.destructiveText,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _openDay(BuildContext context, LearningPlan plan, int dayIndex) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => PlanDayScreen(plan: plan, dayIndex: dayIndex)),
    );
  }

  void _openRehearsal(BuildContext context, LearningPlan plan) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PlanRehearsalScreen(planId: plan.id, targetLang: plan.targetLang),
      ),
    );
  }

  Future<void> _openFeedback(BuildContext context, WidgetRef ref, LearningPlan plan) async {
    AppHaptics.light();
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => PlanFeedbackScreen(plan: plan)),
    );
    // The plan may be finished now, in which case this screen is about to be replaced by the
    // finished-plan tab — so both reads it hangs off are dropped rather than one.
    ref.invalidate(activePlanProvider);
    ref.invalidate(planProvider(plan.id));
  }
}

/// The dark plate: the plan's name, its readiness, and when the event is.
class _ReadinessPlate extends StatelessWidget {
  const _ReadinessPlate({required this.plan});
  final LearningPlan plan;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final paper = AppColors.paper;

    return PlanPlate(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: PlanLabel(l.planActiveBadge, color: AppColors.brass)),
              PlanLabel(
                planDateOrNone(context, plan.eventDate, short: true),
                color: paper.withValues(alpha: 0.7),
                fontSize: 11.5,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            plan.title,
            style: AppText.displayTerm.copyWith(color: paper, fontSize: 28, height: 1.18),
          ),
          const SizedBox(height: AppSpacing.s16),
          Divider(height: 1, thickness: 1, color: paper.withValues(alpha: 0.18)),
          const SizedBox(height: AppSpacing.s16),
          // WHAT THE WORK LOOKS LIKE TODAY, not «0% готовность к событию».
          //
          // The percentage is honest and, for the first days of a plan, always zero: it counts the
          // cards that reached their LAST stage (a line after B, a word after C), and a day that
          // closes stage A moves its cards ONTO stage B. The owner walked fifty-six cards on 02.09
          // and read 0%. So the plate shows the count the sitting actually moves until the canonical
          // formula arrives (P2-v0.4/SIT-1). The formula itself is untouched — `readiness` is still
          // computed, still on the wire, and simply not the headline here.
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              // 52pt of Literata beside 18 — the number IS the headline. Two strings rather than one
              // localised phrase, because one phrase cannot be set in two sizes.
              Text(
                '${plan.stageAClosed}',
                style: AppText.displayNumber.copyWith(color: paper, fontSize: 52, height: 1),
              ),
              const SizedBox(width: AppSpacing.s12),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 2),
                  child: Text(
                    [
                      l.planStageCensusCards(plan.cardsTotal),
                      l.planStageCensusClosed(plan.stageAClosed),
                      if (plan.stageALeft > 0) l.planStageCensusLeft(plan.stageALeft),
                    ].join(' · '),
                    style: AppText.displayTerm.copyWith(
                      color: paper.withValues(alpha: 0.8),
                      fontSize: 18,
                      height: 1.25,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            l.planStageCensusCaption,
            style: AppText.translation.copyWith(
              fontSize: 12.5,
              color: paper.withValues(alpha: 0.66),
            ),
          ),
          const SizedBox(height: 14),
          PlanReadinessBar(
            // The bar follows the same count as the number above it — a bar tracking a percentage
            // the headline no longer shows would be a second, contradicting answer.
            value: plan.cardsTotal == 0 ? 0 : plan.stageAClosed / plan.cardsTotal,
            onDark: true,
          ),
          const SizedBox(height: AppSpacing.s12),
          Row(
            children: [
              Expanded(
                child: Text(
                  switch (plan.daysToEvent) {
                    null => l.planEntryHintNoDate,
                    final int d when d > 0 => l.planEventInDays(d),
                    0 => l.planEventToday,
                    _ => l.planEventPassed,
                  },
                  style: AppText.translation.copyWith(
                    fontSize: 12.5,
                    color: paper.withValues(alpha: 0.66),
                  ),
                ),
              ),
              Text(
                l.planDayOfTotal(plan.focusDayIndex, plan.days.length),
                style: AppText.translation.copyWith(
                  fontSize: 12.5,
                  color: paper.withValues(alpha: 0.66),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// One day in the plan's list. Three faces, and only one of them is bold.
///
/// A passed day is dimmed (its work is behind the learner), the FOCUS day is the single heavy row,
/// and a day ahead is open but written quietly — «можно открыть раньше» rather than a lock. The
/// design's own words: «пройденный день приглушён, текущий — единственный жирный, будущий открыт,
/// но подписан деликатно».
class _DayRow extends StatelessWidget {
  const _DayRow({required this.plan, required this.day, required this.onTap});

  final LearningPlan plan;
  final PlanDay day;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final passed = day.index < plan.focusDayIndex;
    final current = day.index == plan.focusDayIndex;
    final computed = plan.computedDayAt(day.index);

    // THE ROW SAYS WHAT THE DAY ACTUALLY IS (Д-20).
    //
    // «Собирается» used to cover `pending` and `generating` together, so days 3 and 4 — untouched,
    // zero attempts, nothing queued — were announced as being built, and a `failed` day fell
    // through to the word counts and read like an ordinary day ahead. Three states, three
    // sentences: queued, being written, burned.
    final subtitle = passed
        ? l.planDayPassed(day.index)
        : day.status == PlanDayStatus.failed
            ? l.planDayNotBuilt
            : day.kind == PlanDayKind.finalRun
                ? l.planDayFinalHint
                : day.status.isGenerating
                    ? l.planDayBuilding
                    : day.status.isQueued
                        ? l.planDayQueued
                        : [
                            if ((computed?.wordCount ?? 0) > 0) l.planWordsCount(computed!.wordCount),
                            if ((computed?.phraseCount ?? 0) > 0)
                              l.planPhrasesCount(computed!.phraseCount),
                            if (!current) l.planDayOpenEarly,
                          ].join(' · ');

    return Opacity(
      opacity: passed ? 0.62 : 1,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: EdgeInsets.symmetric(vertical: current ? 16 : 14),
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _DayMark(index: day.index, passed: passed, current: current),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      day.title,
                      style: AppText.collectionNameCard.copyWith(
                        fontSize: current ? 20 : 18,
                        fontWeight: current ? FontWeight.w600 : FontWeight.w500,
                        color: current ? AppColors.ink : AppColors.inkBody,
                      ),
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        style: AppText.translation.copyWith(
                          fontSize: current ? 13.5 : 13,
                          height: 1.5,
                          color: current ? AppColors.inkBody : AppColors.tertiary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DayMark extends StatelessWidget {
  const _DayMark({required this.index, required this.passed, required this.current});
  final int index;
  final bool passed, current;

  @override
  Widget build(BuildContext context) => Container(
    width: 26,
    height: 26,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: passed ? AppColors.ink : null,
      border: passed
          ? null
          : Border.all(
              color: current ? AppColors.destructiveText : AppColors.track,
              width: current ? 2 : 1,
            ),
    ),
    child: passed
        ? const Icon(LucideIcons.check, size: 13, color: AppColors.paper)
        : Text(
            '$index',
            style: AppText.translation.copyWith(
              fontSize: 11,
              fontWeight: FontWeight.w500,
              color: current ? AppColors.destructiveText : AppColors.tertiary,
            ),
          ),
  );
}

class _RowLink extends StatelessWidget {
  const _RowLink({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => MinTapHeight(
    minHeight: 24,
    onTap: onTap,
    child: Text(
      label,
      style: AppText.translation.copyWith(fontSize: 13.5, color: AppColors.destructiveText),
    ),
  );
}
