import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/api_client.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_builder_screen.dart';
import 'plan_screen.dart';
import 'plan_ui.dart';

/// THE «ПЛАН» TAB — three faces, and which one is shown is a fact about the account, not a mode.
///
///  * NO PLAN (кадр 1c · 10) — the difference between a collection and a plan, in three lines. No
///    illustration and no «выучи язык за неделю»: the tab has to explain a concept, and the honest
///    way to do that is to say what it is for.
///  * A PLAN RUNNING — {@link PlanScreen}, embedded rather than pushed, so the tab bar stays.
///  * A PLAN FINISHED (кадр 1c · 11) — «подготовка завершена» does not disappear: the outcome, what
///    happened to the words, and the archive of everything before it.
class PlanTabScreen extends ConsumerWidget {
  const PlanTabScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final active = ref.watch(activePlanProvider);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: SafeArea(
        bottom: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, AppSpacing.s16, AppSpacing.screenH, 0),
              child: SizedBox(
                height: AppSpacing.minTap,
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(l.tabPlan, style: AppText.collectionNameScreen.copyWith(fontSize: 22)),
                ),
              ),
            ),
            Expanded(
              child: active.when(
                loading: () =>
                    const Center(child: CircularProgressIndicator(color: AppColors.ink)),
                error: (e, _) => PlanNotice(
                  text: isOffline(e) ? l.planErrorOffline : l.planErrorLoadFailed,
                  actionLabel: l.generationRetry,
                  onAction: () => ref.invalidate(activePlanProvider),
                ),
                data: (plan) => plan == null
                    ? const _NoActivePlan()
                    : PlanScreen(planId: plan.id, embedded: true),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// No plan is RUNNING — which is two different screens, and the archive tells them apart.
///
/// A learner who has never made one gets the explanation (кадр 10); one who has just finished one
/// gets their result and their archive (кадр 11). Deciding it here rather than in two callers is
/// what keeps «Составить план» in one place at the bottom of both.
class _NoActivePlan extends ConsumerWidget {
  const _NoActivePlan();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final archive = ref.watch(planArchiveProvider);

    return archive.when(
      // The archive is a nicety, not a gate: while it is loading or if it fails, the tab still says
      // what a plan is and still offers to make one.
      loading: () => const _EmptyPlanTab(),
      error: (_, _) => const _EmptyPlanTab(),
      data: (plans) => plans.isEmpty ? const _EmptyPlanTab() : _FinishedPlanTab(plans: plans),
    );
  }
}

/// «Подготовиться к чему-то конкретному» — кадр 1c · 10.
class _EmptyPlanTab extends ConsumerWidget {
  const _EmptyPlanTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);

    return ListView(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.screenHWide,
        AppSpacing.s26,
        AppSpacing.screenHWide,
        _bottomInset(context),
      ),
      children: [
        Text(l.planEmptyTitle, style: AppText.collectionNameScreen.copyWith(height: 1.2)),
        const SizedBox(height: AppSpacing.s12),
        Text(
          l.planEmptyBody,
          style: AppText.translation.copyWith(
            fontSize: 14.5,
            height: 1.65,
            color: AppColors.inkBody,
          ),
        ),
        const SizedBox(height: AppSpacing.s26),
        for (final (i, line) in [l.planEmptyStep1, l.planEmptyStep2, l.planEmptyStep3].indexed)
          _NumberedLine(index: i + 1, text: line, last: i == 2),
        const SizedBox(height: AppSpacing.s26),
        PrimaryButton(
          label: l.planEmptyCta,
          minHeight: 52,
          onPressed: () => openPlanBuilder(context, ref),
        ),
      ],
    );
  }
}

/// «Подготовка завершена» + «18 слов ушли в общее повторение» + «Архив» — кадр 1c · 11.
class _FinishedPlanTab extends ConsumerWidget {
  const _FinishedPlanTab({required this.plans});
  final List<PlanSummary> plans;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final latest = plans.first;
    final archive = plans.skip(1).toList();
    // The whole plan, for the abilities it taught — one read, for the one plan the learner is
    // actually looking at. The archive rows stay summaries.
    final full = ref.watch(planProvider(latest.id)).value;

    return ListView(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        AppSpacing.s12,
        AppSpacing.screenH,
        _bottomInset(context),
      ),
      children: [
        Container(
          padding: const EdgeInsets.only(top: AppSpacing.s16),
          decoration: const BoxDecoration(
            border: Border(top: BorderSide(color: AppColors.brassFrame)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              PlanLabel(l.planFinishedBadge),
              const SizedBox(height: AppSpacing.s8),
              Text(
                latest.title,
                style: AppText.displayTerm.copyWith(fontSize: 28, height: 1.18),
              ),
              const SizedBox(height: AppSpacing.s8),
              Text(
                l.planFinishedSummary(
                  latest.dayCount,
                  planDateLabel(context, latest.eventDate),
                ),
                style: AppText.translation.copyWith(
                  fontSize: 14,
                  height: 1.55,
                  color: AppColors.inkBody,
                ),
              ),
              if (full != null && full.canAlready.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.s12),
                for (final checkpoint in full.canAlready)
                  PlanAbilityRow(text: checkpoint.text, hit: checkpoint.hit, divider: false),
              ],
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.s22),
        // What happened to the WORDS — the sentence the whole exclusion rule exists for: while the
        // plan ran, its words were the plan's; now they are the learner's ordinary queue.
        PaperCard(
          radius: 16,
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                l.planWordsReleasedTitle,
                style: AppText.collectionNameCard.copyWith(fontSize: 19, height: 1.3),
              ),
              const SizedBox(height: 6),
              Text(
                l.planWordsReleasedBody,
                style: AppText.translation.copyWith(
                  fontSize: 13.5,
                  height: 1.55,
                  color: AppColors.secondary,
                ),
              ),
            ],
          ),
        ),
        if (archive.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s26),
          PlanLabel(l.planArchive, color: AppColors.tertiary, fontSize: 11.5),
          const SizedBox(height: AppSpacing.s8),
          for (final plan in archive) _ArchiveRow(plan: plan),
        ],
        const SizedBox(height: AppSpacing.s26),
        PrimaryButton(
          label: l.planFinishedNewPlan,
          minHeight: 52,
          onPressed: () => openPlanBuilder(context, ref),
        ),
      ],
    );
  }
}

class _ArchiveRow extends StatelessWidget {
  const _ArchiveRow({required this.plan});
  final PlanSummary plan;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return InkWell(
      onTap: () {
        AppHaptics.light();
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => PlanScreen(planId: plan.id)),
        );
      },
      child: Container(
        constraints: const BoxConstraints(minHeight: 60),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(plan.title, style: AppText.collectionNameCard.copyWith(fontSize: 18)),
                  const SizedBox(height: 2),
                  Text(
                    '${planDateLabel(context, plan.eventDate)} · ${l.planDaysCount(plan.dayCount)}',
                    style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
                  ),
                ],
              ),
            ),
            const Icon(LucideIcons.chevronRight, size: 16, color: AppColors.tertiary),
          ],
        ),
      ),
    );
  }
}

class _NumberedLine extends StatelessWidget {
  const _NumberedLine({required this.index, required this.text, required this.last});
  final int index;
  final String text;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 14),
    decoration: BoxDecoration(
      border: Border(
        top: const BorderSide(color: AppColors.dividerFaint),
        bottom: last ? const BorderSide(color: AppColors.dividerFaint) : BorderSide.none,
      ),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 3),
          child: Text(
            '0$index',
            style: AppText.translation.copyWith(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.6,
              color: AppColors.brassInk,
            ),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Text(
            text,
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              height: 1.5,
              color: AppColors.inkBody,
            ),
          ),
        ),
      ],
    ),
  );
}

double _bottomInset(BuildContext context) =>
    AppTabBarMetrics.height +
    AppTabBarMetrics.bottomInset +
    MediaQuery.viewPaddingOf(context).bottom +
    AppSpacing.s8;

/// «Составить план» — THE ONE DOOR, opened from three places (the empty tab, the finished tab, and
/// the home invitation), so the three cannot come to differ in what they open or in what they
/// invalidate when the learner comes back.
Future<void> openPlanBuilder(BuildContext context, WidgetRef ref, {String? goal}) async {
  AppHaptics.light();
  await Navigator.of(context).push(
    MaterialPageRoute(builder: (_) => PlanBuilderScreen(initialGoal: goal)),
  );
  // They may have started one. Both the tab and the home card read the same provider.
  ref.invalidate(activePlanProvider);
  ref.invalidate(planArchiveProvider);
}
