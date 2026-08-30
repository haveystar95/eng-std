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
                    // opens the DAY that teaches it, which is the only place the learner can act on
                    // this line at all.
                    trailing: checkpoint.hit
                        ? null
                        : _RowLink(
                            label: l.planTrainThis,
                            onTap: () => _openDay(context, plan, checkpoint.dayIndex),
                          ),
                  ),
                const SizedBox(height: AppSpacing.s22),
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
                if (focus != null)
                  PrimaryButton(
                    label: l.planContinueDay(focus.index),
                    minHeight: 52,
                    onPressed: () => _openDay(context, plan, focus.index),
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
                planDateLabel(context, plan.eventDate, short: true),
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
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              // 52pt of Literata beside 19 — the number IS the headline, and the unit is not. Two
              // strings rather than one localised phrase, because one phrase cannot be set in two
              // sizes (the same reason the home screen's session count is two).
              Text(
                '${plan.readinessPercent}',
                style: AppText.displayNumber.copyWith(color: paper, fontSize: 52, height: 1),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  '%',
                  style: AppText.displayNumber.copyWith(color: paper, fontSize: 26, height: 1),
                ),
              ),
              const SizedBox(width: AppSpacing.s12),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 2),
                  child: Text(
                    l.planReadinessCaption,
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
          const SizedBox(height: 14),
          PlanReadinessBar(value: plan.readiness, onDark: true),
          const SizedBox(height: AppSpacing.s12),
          Row(
            children: [
              Expanded(
                child: Text(
                  plan.daysToEvent > 0
                      ? l.planEventInDays(plan.daysToEvent)
                      : (plan.daysToEvent == 0 ? l.planEventToday : l.planEventPassed),
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

    final subtitle = passed
        ? l.planDayPassed(day.index)
        : day.kind == PlanDayKind.finalRun
            ? l.planDayFinalHint
            : day.status.isBuilding
                ? l.planDayBuilding
                : [
                    if ((computed?.wordCount ?? 0) > 0) l.planWordsCount(computed!.wordCount),
                    if ((computed?.phraseCount ?? 0) > 0) l.planPhrasesCount(computed!.phraseCount),
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
