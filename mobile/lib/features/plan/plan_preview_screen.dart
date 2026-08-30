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
import 'plan_building_screen.dart';
import 'plan_ui.dart';

/// «Превью плана» — кадр Б-04, and Б-05 when the deadline is tight.
///
/// The dark plate is the whole reason this is a screen of its own rather than the last reply in a
/// ribbon: the plan reads as a bought thing, the same material the day's session wears on the home
/// screen. Everything under it is the STRUCTURE — days with what each one teaches — and the two
/// quiet links that change it.
///
/// Nothing here has been generated yet. The outline is one model call the learner has already paid
/// for; «Начать» is the commitment, and until it is pressed the plan is a draft that costs nothing
/// to abandon by walking back.
class PlanPreviewScreen extends ConsumerStatefulWidget {
  const PlanPreviewScreen({super.key, required this.plan});

  final LearningPlan plan;

  @override
  ConsumerState<PlanPreviewScreen> createState() => _PlanPreviewScreenState();
}

class _PlanPreviewScreenState extends ConsumerState<PlanPreviewScreen> {
  late LearningPlan _plan = widget.plan;
  bool _busy = false;
  String? _error;

  /// «Оставить» on the «срок мал» card — the learner has read the recommendation and declined it.
  /// Kept on the screen and not on the server: the server's `deadline_tight` is a FACT about the
  /// calendar, and «I know» is not a change to the plan.
  bool _tightAcknowledged = false;

  Future<void> _mutate(Future<LearningPlan> Function(ApiClient api) call) async {
    if (_busy) return;
    AppHaptics.light();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final updated = await call(ref.read(apiClientProvider));
      if (mounted) {
        setState(() {
          _plan = updated;
          _busy = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      final l = AppLocalizations.of(context);
      setState(() {
        _busy = false;
        _error = isOffline(e) ? l.planErrorOffline : l.planErrorBuildFailed;
      });
    }
  }

  /// «Убрать день» — the learner picks WHICH one. The frames draw one link; a link that silently
  /// dropped the last day would be an edit the learner cannot see before it happens.
  Future<void> _dropDay() async {
    final l = AppLocalizations.of(context);
    final days = (_plan.computed?.days ?? const <PlanComputedDay>[])
        .where((d) => d.kind == PlanDayKind.intro)
        .toList();
    if (days.length <= 1) {
      setState(() => _error = l.planDropLastDay);
      return;
    }

    final chosen = await showAppBottomSheet<int>(
      context: context,
      builder: (sheetContext) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.planDropDaySheet, style: AppText.sheetButton.copyWith(fontSize: 17)),
          const SizedBox(height: AppSpacing.s8),
          for (final day in days)
            AppSheetRow(
              title: Text(
                '${l.planDayNumber(day.index)} · ${day.title}',
                style: AppText.translation.copyWith(fontSize: 15),
              ),
              onTap: () => Navigator.of(sheetContext).pop(day.index),
            ),
        ],
      ),
    );

    if (chosen != null) {
      await _mutate((api) => api.reschedulePlan(_plan.id, dropDayIndex: chosen));
    }
  }

  /// «Начать» — the commitment. Days start generating and words start being held; from here the
  /// plan is the learner's, and the way out is «отказаться», not «назад».
  Future<void> _start() async {
    if (_busy) return;
    AppHaptics.light();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final started = await ref.read(apiClientProvider).startPlan(_plan.id);
      if (!mounted) return;
      // The tab now has a plan to show. Invalidated before navigating so the screen behind the
      // «собираю» animation is already the right one.
      ref.invalidate(activePlanProvider);
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => PlanBuildingScreen(plan: started)),
      );
    } catch (e) {
      if (!mounted) return;
      final l = AppLocalizations.of(context);
      setState(() {
        _busy = false;
        _error = isOffline(e) ? l.planErrorOffline : l.planErrorStartFailed;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final computed = _plan.computed;
    final days = computed?.days ?? const <PlanComputedDay>[];

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _PreviewBar(title: l.planPreviewBadge),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(14, AppSpacing.s12, 14, AppSpacing.s26),
                  children: [
                    PlanPlate(child: _plateBody(context, l, computed)),
                    if (_plan.deadlineTight && !_tightAcknowledged) ...[
                      const SizedBox(height: 18),
                      _TightCard(
                        plan: _plan,
                        busy: _busy,
                        onAddMinutes: () => _mutate(
                          (api) => api.reschedulePlan(
                            _plan.id,
                            minutesPerDay: _plan.minutesPerDay + 20,
                          ),
                        ),
                        onKeep: () => setState(() => _tightAcknowledged = true),
                      ),
                    ],
                    const SizedBox(height: AppSpacing.s22),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          for (final day in days) _DayRow(day: day),
                          const SizedBox(height: AppSpacing.s16),
                          Row(
                            children: [
                              _QuietLink(label: l.planPreviewDropDay, onTap: _busy ? null : _dropDay),
                              const SizedBox(width: 18),
                              _QuietLink(
                                label: l.planPreviewRebuild,
                                onTap: _busy
                                    ? null
                                    : () => _mutate((api) => api.buildPlanOutline(_plan.id)),
                              ),
                            ],
                          ),
                          if (_error != null) ...[
                            const SizedBox(height: AppSpacing.s16),
                            Text(
                              _error!,
                              style: AppText.translation.copyWith(
                                fontSize: 13.5,
                                height: 1.45,
                                color: AppColors.destructiveText,
                              ),
                            ),
                          ],
                          const SizedBox(height: 18),
                          PrimaryButton(
                            label: _busy ? l.planBuilderWorking : l.planPreviewStart,
                            minHeight: 52,
                            enabled: !_busy,
                            onPressed: _start,
                          ),
                          const SizedBox(height: 10),
                          // The paywall is not built (PLAN-1c leaves it alone on purpose). The line
                          // is a PLACEHOLDER and says so — a price invented here would be a price
                          // somebody eventually ships.
                          Center(
                            child: Text(
                              l.planPricePlaceholder,
                              style: AppText.translation.copyWith(
                                fontSize: 12.5,
                                color: AppColors.tertiary,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _plateBody(BuildContext context, AppLocalizations l, PlanComputed? computed) {
    final paper = AppColors.paper;
    final intro = computed?.introDayCount ?? 0;
    final terms = computed?.totalTerms ?? 0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          _plan.title,
          style: AppText.displayTerm.copyWith(color: paper, fontSize: 28, height: 1.18),
        ),
        const SizedBox(height: 14),
        Divider(height: 1, thickness: 1, color: paper.withValues(alpha: 0.18)),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(
              child: PlanLabel(
                l.planPrepDays(intro),
                color: paper.withValues(alpha: 0.72),
                fontSize: 11.5,
              ),
            ),
            PlanLabel(
              l.planMinutesPerDay(_plan.minutesPerDay),
              color: paper.withValues(alpha: 0.72),
              fontSize: 11.5,
            ),
          ],
        ),
        const SizedBox(height: 14),
        Text(
          [
            l.planEventOn(planDateLabel(context, _plan.eventDate)),
            if (terms > 0) l.planApproxTerms(terms),
          ].join(' '),
          style: AppText.translation.copyWith(
            fontSize: 14,
            height: 1.55,
            color: paper.withValues(alpha: 0.82),
          ),
        ),
      ],
    );
  }
}

/// One day of the preview: «ДЕНЬ 1 · 5 слов · 4 фразы», the title, and what it teaches.
class _DayRow extends StatelessWidget {
  const _DayRow({required this.day});
  final PlanComputedDay day;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final counts = day.kind == PlanDayKind.finalRun
        ? l.planDayNoNewWords
        : [
            if (day.wordCount > 0) l.planWordsCount(day.wordCount),
            if (day.phraseCount > 0) l.planPhrasesCount(day.phraseCount),
          ].join(' · ');

    return Container(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.s16),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: PlanLabel(l.planDayNumber(day.index), fontSize: 11.5)),
              if (counts.isNotEmpty)
                Text(
                  counts,
                  style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
                ),
            ],
          ),
          const SizedBox(height: 5),
          Text(day.title, style: AppText.collectionNameCard.copyWith(fontSize: 19, height: 1.3)),
          if (day.outcomes.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s8),
            Text(
              // «Сказать, зачем пришёл · назвать, где болит · объяснить, как давно» — the abilities
              // in one line rather than a bulleted list: the preview is a promise, not a syllabus.
              day.outcomes.join(' · '),
              style: AppText.translation.copyWith(
                fontSize: 13.5,
                height: 1.6,
                color: AppColors.inkBody,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// «Срок мал под цель» (кадр Б-05) — a brass recommendation between the plate and the days.
///
/// It sits exactly where the structure it talks about begins, and it is a RECOMMENDATION: the
/// «Оставить» button is real, and the plan it leaves alone is a plan the learner may still run.
/// The reason is stated as MECHANICS («слова второго дня останутся на ступени B») rather than as
/// «будет сложно» — the learner can act on the first and not on the second.
class _TightCard extends StatelessWidget {
  const _TightCard({
    required this.plan,
    required this.busy,
    required this.onAddMinutes,
    required this.onKeep,
  });

  final LearningPlan plan;
  final bool busy;
  final VoidCallback onAddMinutes, onKeep;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final computed = plan.computed;
    final dropped = computed?.dropped ?? const <PlanDroppedSkill>[];
    // The abilities that DO fit, so the card can show both halves — a list of only the losses reads
    // as a failure, and this plan teaches most of what was asked.
    final kept = [
      for (final day in computed?.days ?? const <PlanComputedDay>[])
        ...day.outcomes,
    ];

    return PlanBrassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l.planTightTitle(computed?.introDayCount ?? 0, plan.minutesPerDay),
            style: AppText.collectionNameCard.copyWith(fontSize: 18, height: 1.4),
          ),
          const SizedBox(height: AppSpacing.s12),
          for (final text in kept.take(3))
            PlanAbilityRow(text: text, hit: true, divider: false),
          for (final skill in dropped.take(3))
            PlanAbilityRow(text: skill.outcome, hit: false, divider: false),
          const SizedBox(height: AppSpacing.s16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _BrassButton(label: l.planTightAddMinutes(20), onTap: busy ? null : onAddMinutes),
              _OutlineButton(label: l.planTightKeep, onTap: busy ? null : onKeep),
            ],
          ),
        ],
      ),
    );
  }
}

/// The one brass FILL in the app, and only here: the «срок мал» card's recommended action. It is
/// not the screen's primary button — «Начать» below still is, in terracotta — so the two cannot be
/// confused for one another.
class _BrassButton extends StatelessWidget {
  const _BrassButton({required this.label, required this.onTap});
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: onTap == null ? AppColors.track : AppColors.brassInk,
    borderRadius: BorderRadius.circular(AppRadii.small),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Container(
        constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 18),
        child: Text(
          label,
          style: AppText.translation.copyWith(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: AppColors.paper,
          ),
        ),
      ),
    ),
  );
}

class _OutlineButton extends StatelessWidget {
  const _OutlineButton({required this.label, required this.onTap});
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadii.small),
      side: const BorderSide(color: AppColors.track),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Container(
        constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Text(label, style: AppText.translation.copyWith(fontSize: 15)),
      ),
    ),
  );
}

class _QuietLink extends StatelessWidget {
  const _QuietLink({required this.label, required this.onTap});
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => MinTapHeight(
    onTap: onTap,
    child: Text(
      label,
      style: AppText.translation.copyWith(
        fontSize: 14,
        color: onTap == null ? AppColors.tertiary : AppColors.destructiveText,
      ),
    ),
  );
}

class _PreviewBar extends StatelessWidget {
  const _PreviewBar({required this.title});
  final String title;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: AppSpacing.minTap,
    child: Row(
      children: [
        InkResponse(
          onTap: () => Navigator.of(context).maybePop(),
          radius: 22,
          child: const SizedBox(
            width: AppSpacing.minTap,
            height: AppSpacing.minTap,
            child: Icon(LucideIcons.chevronLeft, size: 20, color: AppColors.secondary),
          ),
        ),
        Expanded(child: Center(child: PlanLabel(title))),
        const SizedBox(width: AppSpacing.minTap),
      ],
    ),
  );
}
