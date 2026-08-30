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
import '../training/session_screen.dart';
import 'plan_building_screen.dart';
import 'plan_ui.dart';

/// ONE DAY OF A PLAN — кадр 1c · 02.
///
/// Two registers, set differently because they are different things:
///
///  * ФРАЗЫ — what the learner will SAY, in serif with a terracotta rule down the left. They are the
///    point of the day; the words exist because these sentences need them.
///  * СЛОВА — a compact list with a brass stage letter at the right edge. The letter is the whole
///    reason this screen reads the server rather than the local mirror: a stage is computed from the
///    review log on every read and is stored nowhere.
///
/// A day AHEAD of the focus is openable — the frames say «можно открыть раньше» rather than drawing
/// a lock. What it cannot do is count: its session comes back `strict: false`, schedules nothing and
/// closes no stage, and the screen says so above the button rather than letting the learner find out
/// afterwards.
class PlanDayScreen extends ConsumerWidget {
  const PlanDayScreen({super.key, required this.plan, required this.dayIndex});

  final LearningPlan plan;
  final int dayIndex;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final args = (planId: plan.id, dayIndex: dayIndex);
    final day = ref.watch(planDayProvider(args));

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _DayBar(label: l.planDayOfPlan(dayIndex, plan.days.length)),
              Expanded(
                child: day.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator(color: AppColors.ink)),
                  error: (e, _) => PlanNotice(
                    text: isOffline(e) ? l.planErrorOffline : l.planErrorLoadFailed,
                    actionLabel: l.generationRetry,
                    onAction: () => ref.invalidate(planDayProvider(args)),
                  ),
                  data: (detail) => _DayBody(plan: plan, detail: detail),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DayBody extends ConsumerWidget {
  const _DayBody({required this.plan, required this.detail});

  final LearningPlan plan;
  final PlanDayDetail detail;

  bool get _isFocus => detail.day.index == plan.focusDayIndex;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final day = detail.day;

    if (!day.status.hasMaterial) {
      return _NotWrittenYet(plan: plan, day: day);
    }

    final carried = detail.carried;

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        18,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      children: [
        Text(day.title, style: AppText.collectionNameScreen.copyWith(fontSize: 29, height: 1.18)),
        if (day.outcomes.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s16),
          PlanLabel(l.planDayCanDo, color: AppColors.tertiary, fontSize: 11.5),
          const SizedBox(height: AppSpacing.s8),
          Text(
            day.outcomes.join(' · '),
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              height: 1.7,
              color: AppColors.inkBody,
            ),
          ),
        ],
        if (detail.phrases.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s22),
          PlanLabel(l.planDayPhrases, color: AppColors.tertiary, fontSize: 11.5),
          const SizedBox(height: 10),
          for (final phrase in detail.phrases) _PhraseLine(term: phrase),
        ],
        if (detail.words.isNotEmpty || carried.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s22),
          PlanLabel(l.planDayWords, color: AppColors.tertiary, fontSize: 11.5),
          const SizedBox(height: 6),
          for (final word in detail.words) _WordRow(term: word),
          if (carried.isNotEmpty) _CarriedRow(terms: carried),
        ],
        const SizedBox(height: AppSpacing.s22),
        if (!_isFocus) ...[
          // Said BEFORE the button, not after the session. A soft run is a legitimate thing to want
          // — «посмотреть, что будет завтра» — and the only dishonest version of it is one the
          // learner finds out about when their progress has not moved.
          Text(
            l.planDaySoftNote,
            style: AppText.translation.copyWith(
              fontSize: 13,
              height: 1.5,
              color: AppColors.tertiary,
            ),
          ),
          const SizedBox(height: 10),
        ],
        PrimaryButton(
          label: l.planDayTrain,
          minHeight: 52,
          onPressed: () => _train(context, ref),
        ),
        const SizedBox(height: AppSpacing.s16),
        _ConversationBlock(role: day.roleTitle),
      ],
    );
  }

  Future<void> _train(BuildContext context, WidgetRef ref) async {
    AppHaptics.light();
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SessionScreen(
          title: detail.day.title,
          planId: plan.id,
          planDayIndex: detail.day.index,
          targetLang: detail.targetLang,
        ),
      ),
    );
    // The session moved stages and possibly the focus. Both live on the server, so the two screens
    // behind this one are re-read rather than patched.
    ref.invalidate(planDayProvider((planId: plan.id, dayIndex: detail.day.index)));
    ref.invalidate(planProvider(plan.id));
    ref.invalidate(activePlanProvider);
  }
}

/// A day the server has not written yet — «Собрать день N».
///
/// The plan generates ONE day at a time and only a couple ahead of the focus (its own spending
/// ceiling), so a day further out legitimately has a title and nothing else. The button asks for it
/// by hand, which is exactly what `POST /plans/{id}/days/{n}/generate` is for.
class _NotWrittenYet extends ConsumerWidget {
  const _NotWrittenYet({required this.plan, required this.day});

  final LearningPlan plan;
  final PlanDay day;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        18,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      children: [
        Text(day.title, style: AppText.collectionNameScreen.copyWith(fontSize: 29, height: 1.18)),
        const SizedBox(height: AppSpacing.s12),
        Text(
          day.status == PlanDayStatus.failed ? l.planDayFailed : l.planDayNotWritten,
          style: AppText.translation.copyWith(
            fontSize: 14.5,
            height: 1.6,
            color: AppColors.secondary,
          ),
        ),
        const SizedBox(height: AppSpacing.s22),
        PrimaryButton(
          label: l.planDayBuildNow,
          minHeight: 52,
          onPressed: () {
            AppHaptics.light();
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(
                builder: (_) => PlanBuildingScreen(plan: plan, dayIndex: day.index),
              ),
            );
          },
        ),
      ],
    );
  }
}

/// «It's a sharp pain.» — the sentence in serif with its translation under it, and a terracotta rule
/// down the left. The rule is the frame's one accent on this screen: it marks «то, что ты скажешь».
class _PhraseLine extends StatelessWidget {
  const _PhraseLine({required this.term});
  final PlanTermRow term;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Container(
      padding: const EdgeInsets.fromLTRB(14, 2, 0, 2),
      decoration: const BoxDecoration(
        border: Border(left: BorderSide(color: AppColors.verdictUnknown, width: 2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            term.text,
            style: AppText.collectionNameCard.copyWith(fontSize: 19, height: 1.3),
          ),
          if (term.translation != null && term.translation!.isNotEmpty) ...[
            const SizedBox(height: 3),
            Text(
              term.translation!,
              style: AppText.translation.copyWith(fontSize: 13.5, color: AppColors.secondary),
            ),
          ],
        ],
      ),
    ),
  );
}

/// «sharp · острый … A» — one row of the day's register.
class _WordRow extends StatelessWidget {
  const _WordRow({required this.term});
  final PlanTermRow term;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Flexible(child: Text(term.text, style: AppText.termInList)),
              if (term.translation != null && term.translation!.isNotEmpty) ...[
                const SizedBox(width: 10),
                Flexible(
                  child: Text(
                    term.translation!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppText.translation.copyWith(fontSize: 14, color: AppColors.secondary),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.s8),
        PlanStageMark(term.stage.letter),
      ],
    ),
  );
}

/// «back · pain · week — B · со дня 1» — everything still in flight from earlier days, in ONE row.
///
/// One row and not one per word, because what it says is a fact about the PLAN and not about each
/// word: the days are connected, and yesterday's words are still in play. A list of them would
/// compete with today's register for the same attention.
class _CarriedRow extends StatelessWidget {
  const _CarriedRow({required this.terms});
  final List<PlanTermRow> terms;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Grouped by the day they came from, oldest first: «со дня 1» and «со дня 2» are two different
    // sentences and merging them would name a day that owns only some of the words.
    final byDay = <int, List<PlanTermRow>>{};
    for (final term in terms) {
      byDay.putIfAbsent(term.fromDayIndex, () => []).add(term);
    }
    final indexes = byDay.keys.toList()..sort();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final index in indexes)
          Container(
            constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    byDay[index]!.map((t) => t.text).join(' · '),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppText.termInList.copyWith(color: AppColors.inkBody),
                  ),
                ),
                const SizedBox(width: AppSpacing.s8),
                PlanStageMark(
                  // The stage of the group, taken from its first word: they were introduced on the
                  // same day and walk the ladder together, so a per-word letter here would be three
                  // identical letters in a row.
                  byDay[index]!.first.stage.letter,
                  suffix: l.planFromDay(index),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// «Разговор» — present, named, and locked until CONV-1.
///
/// Drawn rather than hidden on purpose: the conversation is what the whole plan is FOR, and a day
/// that simply had no such block would read as a plan that teaches words. The caption says when it
/// opens instead of promising it silently.
class _ConversationBlock extends StatelessWidget {
  const _ConversationBlock({required this.role});
  final String? role;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return PaperCard(
      radius: 16,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    PlanLabel(l.planConversationLabel),
                    const SizedBox(height: 5),
                    Text(
                      role ?? l.planConversationDefaultRole,
                      style: AppText.collectionNameCard.copyWith(fontSize: 20),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.s12),
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.photoPlate,
                ),
                child: const Icon(LucideIcons.lock, size: 17, color: AppColors.plateLabel),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            l.planConversationLocked,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              height: 1.55,
              color: AppColors.secondary,
            ),
          ),
        ],
      ),
    );
  }
}

class _DayBar extends StatelessWidget {
  const _DayBar({required this.label});
  final String label;

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
        Expanded(child: Center(child: PlanLabel(label))),
        const SizedBox(width: AppSpacing.minTap),
      ],
    ),
  );
}
