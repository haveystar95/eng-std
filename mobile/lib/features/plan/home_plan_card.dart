import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_day_screen.dart';
import 'plan_tab_screen.dart';
import 'plan_ui.dart';

/// THE PLAN ON THE HOME SCREEN — кадры 08 and 09, one slot and two things that can be in it.
///
/// It sits ABOVE the «сегодня» plate and it is made of a different material: brass-outlined paper
/// against the dark tile. That is not decoration — it is how the eye reads them as two different
/// piles of work rather than as one list with a heading, and the server backs the same reading by
/// keeping the plan's words out of the day's counts entirely ({@see PlanHeldTerms} on the server).
///
/// With no plan the SAME slot carries the invitation (кадр 09), quieter and on plain paper. The
/// position does not move: a promise that migrates around the screen is a promise the learner has to
/// re-find every morning.
class HomePlanSlot extends ConsumerWidget {
  const HomePlanSlot({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final plan = ref.watch(activePlanProvider);

    return plan.when(
      // The home screen must not wait on this. While the plan is unknown the slot is EMPTY rather
      // than a placeholder: the day below it is the point of the screen, and a shimmering box above
      // it would be the first thing the learner looks at every morning.
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
      data: (p) => p == null ? const _PlanInvite() : _PlanCard(plan: p),
    );
  }
}

class _PlanCard extends ConsumerWidget {
  const _PlanCard({required this.plan});
  final LearningPlan plan;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final focus = plan.focusDay;
    // The abilities, compressed to two lines: what is closed and what is not. The plan screen lists
    // them one per row; the home screen has one card's worth of room and the learner is here to
    // decide whether to open it, not to read the syllabus.
    final done = plan.canAlready.where((c) => c.hit).map((c) => c.text).toList();
    final left = plan.canAlready.where((c) => !c.hit).map((c) => c.text).toList();

    return PlanBrassCard(
      onTap: () => _open(context, ref),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: PlanLabel(
                  l.homePlanCardBadge(plan.focusDayIndex, plan.days.length),
                  fontSize: 11,
                ),
              ),
              const SizedBox(width: AppSpacing.s8),
              // No date, no countdown: the card says «без даты» rather than «событие сегодня»,
              // which is what a zero here used to make it say.
              PlanLabel(
                switch (plan.daysToEvent) {
                  null => l.planNoDate,
                  final int d when d > 0 => l.homePlanCardEventIn(d),
                  _ => l.homePlanCardEventToday,
                },
                fontSize: 11,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.s8),
          Text(plan.title, style: AppText.displayTerm.copyWith(fontSize: 23, height: 1.22)),
          const SizedBox(height: AppSpacing.s12),
          // ВЕРДИКТ СЛОВАМИ ЗРЕЛОСТИ, а не переписью ступеней (наряд DAY-2-FIX, Ч.2.1).
          //
          // Здесь стояло «51 карточка · 21 закрыла ступень A · 30 осталось» с подписью «ступень A ·
          // знакомство с материалом». Числа были правдой; «ступень A» — служебное слово лестницы, о
          // котором человек не просил и которого в продукте нет больше нигде. Процент по-прежнему
          // не показывается: он считает карточки, дошедшие до ПОСЛЕДНЕЙ ступени, и первые дни плана
          // честно даёт ноль (владелец прошёл 56 карточек и прочитал 0%).
          Text(
            '${planMaturityVerdict(l, total: plan.cardsTotal, closed: plan.stageAClosed)}'
            ' · ${l.planMaturityCards(plan.stageAClosed, plan.cardsTotal)}',
            style: AppText.translation.copyWith(fontSize: 14, color: AppColors.inkBody),
          ),
          const SizedBox(height: 10),
          PlanReadinessBar(value: plan.cardsTotal == 0 ? 0 : plan.stageAClosed / plan.cardsTotal),
          // The abilities are drawn only once one of them can be true — until CONV-1 confirms a
          // checkpoint, `left` is the whole list and the card would carry a permanent complaint.
          if (done.isNotEmpty) ...[
            const SizedBox(height: 10),
            _AbilityLine(text: done.join(' · '), hit: true),
            if (left.isNotEmpty) _AbilityLine(text: left.join(' · '), hit: false),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: Text(
                  focus?.title ?? plan.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppText.translation.copyWith(fontSize: 14, color: AppColors.inkBody),
                ),
              ),
              const SizedBox(width: AppSpacing.s12),
              _BrassAction(label: l.homePlanCardContinue, onTap: () => _open(context, ref)),
            ],
          ),
        ],
      ),
    );
  }

  /// Straight into the DAY, not into the tab. The card's own words are «Продолжить день 2», and a
  /// button that landed on a screen with another button on it would be one tap of nothing.
  Future<void> _open(BuildContext context, WidgetRef ref) async {
    AppHaptics.light();
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PlanDayScreen(plan: plan, dayIndex: plan.focusDayIndex),
      ),
    );
    ref.invalidate(activePlanProvider);
  }
}

/// One compressed line of abilities: «✓ Зачем пришёл · где болит · какая боль».
class _AbilityLine extends StatelessWidget {
  const _AbilityLine({required this.text, required this.hit});
  final String text;
  final bool hit;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 16,
          child: Text(
            hit ? '✓' : '—',
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              color: hit ? AppColors.brassInk : AppColors.tertiary,
            ),
          ),
        ),
        Expanded(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              color: hit ? AppColors.ink : AppColors.secondary,
            ),
          ),
        ),
      ],
    ),
  );
}

/// «Есть дата и цель?» — кадр 09. Plain paper and a brass outline button: quieter than an active
/// plan, in exactly the same place.
class _PlanInvite extends ConsumerWidget {
  const _PlanInvite();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);

    return PaperCard(
      radius: 18,
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
      onTap: () => openPlanBuilder(context, ref),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l.homePlanInviteTitle,
                  style: AppText.collectionNameCard.copyWith(fontSize: 20, height: 1.25),
                ),
                const SizedBox(height: 5),
                Text(
                  l.homePlanInviteBody,
                  style: AppText.translation.copyWith(
                    fontSize: 13.5,
                    height: 1.5,
                    color: AppColors.secondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 14),
          _BrassAction(
            label: l.homePlanInviteCta,
            outlined: true,
            onTap: () => openPlanBuilder(context, ref),
          ),
        ],
      ),
    );
  }
}

/// The plan's own action on the home screen — brass, because it belongs to the plan and because the
/// terracotta accent on this screen is spoken for by «Начать занятие» on the dark tile below.
class _BrassAction extends StatelessWidget {
  const _BrassAction({required this.label, required this.onTap, this.outlined = false});

  final String label;
  final VoidCallback onTap;
  final bool outlined;

  @override
  Widget build(BuildContext context) => Material(
    color: outlined ? Colors.transparent : AppColors.brassInk,
    clipBehavior: Clip.antiAlias,
    // `shape` and not `borderRadius`: Material asserts if both are given, and the outlined variant
    // needs a side, which only `shape` can carry.
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadii.small),
      side: outlined ? const BorderSide(color: AppColors.brassInk) : BorderSide.none,
    ),
    child: InkWell(
      onTap: onTap,
      child: Container(
        constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 17),
        child: Text(
          label,
          style: AppText.translation.copyWith(
            fontSize: 14.5,
            fontWeight: FontWeight.w600,
            color: outlined ? AppColors.brassInk : AppColors.paper,
          ),
        ),
      ),
    ),
  );
}
