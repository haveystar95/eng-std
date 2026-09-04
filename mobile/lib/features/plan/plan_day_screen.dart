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
import 'plan_fail_reason.dart';
import 'plan_rehearsal_done.dart';
import 'plan_tab_screen.dart' show abandonPlan;
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
/// a lock — and since E2E-SIM-2 (С-1) it counts like any other day: the session comes back
/// `strict: true`, deals that day's own stage A in канон §11's order, and closes it when it closes.
/// The line above the button says which day the learner is looking at, not what the session will
/// fail to do; the «мягкий прогон» it used to warn about no longer exists on either side.
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
                  data: (detail) => RefreshIndicator(
                    color: AppColors.ink,
                    backgroundColor: AppColors.surfaceRaised,
                    // A day is read live and can change under the learner: one that failed to build
                    // may have been rebuilt since, and one being written turns into material. The
                    // plan screen has had a pull-to-refresh from the start; the day needed it more.
                    onRefresh: () async {
                      ref.invalidate(planDayProvider(args));
                      await ref.read(planDayProvider(args).future);
                    },
                    child: _DayBody(plan: plan, detail: detail),
                  ),
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

    // THE FINAL DAY IS NOT AN UNBUILT DAY (Д-27). It introduces nothing and owns no collection, so
    // there is nothing to generate and the server says so with a 404 — while this screen drew
    // «Собрать день», took the refusal and printed «материал не прошёл проверку». What it actually
    // is is a RUN-THROUGH of everything the plan has taught, and walking it finishes the plan.
    if (day.kind == PlanDayKind.finalRun) {
      return _FinalDay(plan: plan, day: day, targetLang: detail.targetLang);
    }

    if (!day.status.hasMaterial) {
      return _NotWrittenYet(plan: plan, day: day);
    }

    final carried = detail.carried;

    return ListView(
      // Always scrollable, so the pull works on a day whose content does not fill the screen —
      // which is exactly the day that failed to build and has three lines on it.
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        18,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      children: [
        Text(day.title, style: AppText.collectionNameScreen.copyWith(fontSize: 29, height: 1.18)),
        // THE SCENE'S ВВОДКА — «кто перед тобой, что сейчас произойдёт, что считается успехом»
        // (канон §2), in the learner's own language, above everything the day is made of.
        //
        // Plain body text and nothing else: the day screen is DAY-2's to design, and a paragraph
        // that is merely present is worth more than a card invented here and thrown away there. A
        // day with no вводка — every day written before the scene existed — draws nothing at all.
        if (day.intro.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s12),
          Text(
            day.intro,
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              height: 1.6,
              color: AppColors.secondary,
            ),
          ),
        ],
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
          for (final phrase in detail.phrases)
            _PhraseLine(term: phrase, roleName: day.roleTitle),
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
          // Said BEFORE the button, because looking ahead is a legitimate thing to want and the
          // learner should know which day they are about to walk. It used to warn that the session
          // would «run softly» — true then, and the mechanism it described is what dealt dictations
          // of sentences nobody had been shown (С-1). Now it says the plain fact: this is tomorrow's
          // lesson, dealt as tomorrow's lesson.
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

/// THE FINAL DAY — the run-through before the event.
///
/// Deliberately the smallest screen that makes the plan finishable: the finished one is DAY-2. What
/// it must not do is what it used to — offer to BUILD a day that has nothing to build, and then
/// explain the refusal with a reason taken from a different failure (Д-27, and the same class as
/// Д-19).
class _FinalDay extends ConsumerWidget {
  const _FinalDay({required this.plan, required this.day, this.targetLang});

  final LearningPlan plan;
  final PlanDay day;
  final String? targetLang;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
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
          l.planRehearsalLead,
          style: AppText.translation.copyWith(
            fontSize: 14.5,
            height: 1.6,
            color: AppColors.secondary,
          ),
        ),
        const SizedBox(height: AppSpacing.s22),
        PrimaryButton(
          label: l.planRehearsalStart,
          minHeight: 52,
          onPressed: () => _run(context, ref),
        ),
        // «ЗАВЕРШИТЬ ПЛАН» — the ending the canon promises, reachable from the screen the learner is
        // actually standing on (E2E-SIM-2, С-10).
        //
        // `POST /plans/{id}/complete` has existed and worked all along; what the app offered on this
        // screen was «Отказаться от плана» and nothing else, so «план кончается результатом, а не
        // датой» was true of the server and false of the product. The run-through's own summary
        // ([PlanRehearsalDone]) is still the ordinary way here — a learner who plays the run-through
        // to the end is offered it there — and this is for the one who has already done it, or who
        // is closing the plan the morning after.
        //
        // Quiet rather than primary: the button above is what to do NOW, and finishing is what to do
        // when there is nothing left to do.
        const SizedBox(height: AppSpacing.s12),
        QuietButton(
          label: l.planCompleteAction,
          onPressed: () => _complete(context, ref),
        ),
      ],
    );
  }

  /// End the plan with a RESULT — the same command the run-through's summary sends, and the same
  /// screen after it, so the two endings cannot come to differ.
  Future<void> _complete(BuildContext context, WidgetRef ref) async {
    AppHaptics.light();
    final ok = await showCenterAlert(
      context: context,
      title: AppLocalizations.of(context).planCompleteTitle,
      message: AppLocalizations.of(context).planCompleteBody,
      confirmLabel: AppLocalizations.of(context).planCompleteConfirm,
      cancelLabel: AppLocalizations.of(context).commonCancel,
    );
    if (ok != true || !context.mounted) return;

    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PlanRehearsalDone(
          planId: plan.id,
          onDone: () => Navigator.of(context).pop(),
        ),
      ),
    );
    ref.invalidate(planProvider(plan.id));
    ref.invalidate(activePlanProvider);
    ref.invalidate(planArchiveProvider);
    if (context.mounted) Navigator.of(context).maybePop();
  }

  Future<void> _run(BuildContext context, WidgetRef ref) async {
    AppHaptics.light();
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SessionScreen(
          title: day.title,
          planId: plan.id,
          planDayIndex: day.index,
          planIsFinalDay: true,
          targetLang: targetLang,
        ),
      ),
    );
    // The run-through closes the plan, so every plan surface is re-read — the archive is a
    // different screen from the one the learner left.
    ref.invalidate(planProvider(plan.id));
    ref.invalidate(activePlanProvider);
    ref.invalidate(planArchiveProvider);
    if (context.mounted) Navigator.of(context).maybePop();
  }
}

/// A day the server has not written yet — and THREE states, not one.
///
/// The plan generates one day at a time and only a couple ahead of the focus (its own spending
/// ceiling), so a day further out legitimately has a title and nothing else: «Собрать день» asks for
/// it, which is what `POST /plans/{id}/days/{n}/generate` is for.
///
/// A day that FAILED is not the same thing, and a day that failed TWICE is a third thing again. The
/// server claims a day at most twice ({@link PlanDay::MAX_ATTEMPTS}) — «день, не прошедший
/// валидатор дважды, это то, на что смотрит человек» — and after that `claim()` returns false
/// forever. Offering «Собрать день» there is a button that cannot work, however many times it is
/// pressed. So the exhausted day says what actually happened and offers the only thing that CAN
/// help: building the plan again.
class _NotWrittenYet extends ConsumerWidget {
  const _NotWrittenYet({required this.plan, required this.day});

  final LearningPlan plan;
  final PlanDay day;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final exhausted = day.outOfAttempts;

    return ListView(
      // Always scrollable, so the pull works on a day whose content does not fill the screen —
      // which is exactly the day that failed to build and has three lines on it.
      physics: const AlwaysScrollableScrollPhysics(),
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
          exhausted
              ? l.planDayExhaustedLead
              : (day.status == PlanDayStatus.failed ? l.planDayFailed : l.planDayNotWritten),
          style: AppText.translation.copyWith(
            fontSize: 14.5,
            height: 1.6,
            color: AppColors.secondary,
          ),
        ),
        // THE ACTUAL CAUSE, on its own line and read off the server's `fail_code` (Д-19). It used to
        // be part of the sentence above, hard-coded and therefore wrong for every failure but one:
        // the live day had died on an example that repeated another card, and the screen said the
        // model had answered in the wrong language.
        if (day.status == PlanDayStatus.failed) ...[
          const SizedBox(height: AppSpacing.s12),
          Text(
            l.planFailWhy(planFailReason(l, day.failCode)),
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              height: 1.6,
              color: AppColors.tertiary,
            ),
          ),
        ],
        const SizedBox(height: AppSpacing.s22),
        if (exhausted) ...[
          // ONE MORE ATTEMPT FOR THIS DAY, before the sentence about the whole plan.
          //
          // The day gets two paid calls and is then `failed`, and until this button existed the
          // only move on this screen was «Собрать план заново» — which throws away every day already
          // walked. The owner's live plan of 02.09 stopped exactly there: day 1 passed, day 2
          // burned, and continuing meant discarding day 1. It spends money, so it is pressed by a
          // person and never polled.
          PrimaryButton(
            label: l.planDayRebuildDay,
            minHeight: 52,
            onPressed: () => _rebuildDay(context, ref),
          ),
          const SizedBox(height: AppSpacing.s12),
          // The plan is not salvageable a day at a time from here. Abandoning is a decision, so it
          // is confirmed — and it is the learner's, which is why nothing happens automatically.
          QuietButton(
            label: l.planDayRebuildPlan,
            onPressed: () => _rebuild(context, ref),
          ),
        ]
        else
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

  /// Give THIS day one more attempt and watch it being written — the building screen from there on,
  /// which is the same screen «Собрать день» opens and polls the same idempotent endpoint.
  Future<void> _rebuildDay(BuildContext context, WidgetRef ref) async {
    AppHaptics.light();
    try {
      await ref.read(apiClientProvider).rebuildPlanDay(plan.id, day.index);
    } catch (_) {
      // The building screen answers for the failure in its own words — it polls the same day and
      // will say «не собрался» if the attempt never started. A second error surface here would be
      // two sentences about one thing.
    }
    if (!context.mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => PlanBuildingScreen(plan: plan, dayIndex: day.index)),
    );
  }

  /// The same act as the plan screen's own «Отказаться от плана», through the same function — two
  /// entrances to one decision must not come to differ in what they confirm or what they leave.
  Future<void> _rebuild(BuildContext context, WidgetRef ref) async {
    if (!await abandonPlan(context, ref, plan.id) || !context.mounted) return;
    // Back to the tab, which is now the empty state with «Составить план» on it.
    Navigator.of(context).popUntil((route) => route.isFirst);
  }
}

/// «It's a sharp pain.» — the sentence in serif with its translation under it, and a terracotta rule
/// down the left. The rule is the frame's one accent on this screen: it marks «то, что ты скажешь».
class _PhraseLine extends StatelessWidget {
  const _PhraseLine({required this.term, this.roleName});
  final PlanTermRow term;

  /// The interlocutor's name from the day's skeleton («Врач-терапевт»), when it has one.
  final String? roleName;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Container(
      padding: const EdgeInsets.fromLTRB(14, 2, 0, 2),
      decoration: BoxDecoration(
        border: Border(
          left: BorderSide(
            // The interlocutor's line is not one of yours: brass is the plan's own service mark,
            // terracotta is the learner's line. The rule alone is not the whole answer — the
            // caption below says it in words — but a register where every line looks identical is
            // exactly what put the doctor's question among the learner's phrases (Д-8).
            //
            // Asked as «is this only ever RECOGNISED» rather than «is the speaker the role»: the
            // «Тебе скажут» shelf answers both, and a term the server marks `understand` without a
            // speaker must not be set as one of the learner's own lines either.
            color: term.isRecognitionOnly ? AppColors.brassInk : AppColors.verdictUnknown,
            width: 2,
          ),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (term.isRecognitionOnly) ...[
            // The role's own name when the skeleton gave one, «Собеседник:» when it did not.
            PlanLabel(roleName ?? l.planSpeakerRole, fontSize: 10.5),
            const SizedBox(height: 3),
          ],
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
