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
import 'plan_cheatsheet.dart';
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
              _DayBar(
                label: l.planDayOfPlan(dayIndex, plan.days.length),
                // ШПАРГАЛКА в шапке (кадр D·01): «подглядеть перед дверью» — доступна с любого
                // экрана плана и возвращает точно туда же.
                onCheatSheet: () => showPlanCheatSheet(
                  context,
                  planId: plan.id,
                  dayIndex: dayIndex,
                  targetLang: plan.targetLang,
                ),
              ),
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

    final shelves = _Shelves.of(detail);

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
        if (shelves.started) ...[
          Row(
            children: [
              Expanded(child: PlanLabel(l.planDayStarted, color: AppColors.brassInk)),
              Text(
                l.planDaySectionPart(shelves.closed, shelves.cards),
                style: AppText.blockLabel.copyWith(color: AppColors.brassInk, fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 10),
        ],
        Text(day.title, style: AppText.collectionNameScreen.copyWith(fontSize: 27, height: 1.2)),
        // THE ВВОДКА IS THE MAIN TEXT OF THE SCREEN, in the ink colour and not in grey (записка
        // «Вводка», серия «День v1»): a person reads the situation before the lines, and a вводка set
        // as a caption reads as a footnote to a list of sentences. «Кто перед тобой, что сейчас
        // произойдёт, что считается успехом» (канон §2). A day with none — every day written before
        // the scene existed — draws nothing at all.
        if (day.intro.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s12),
          Text(
            day.intro,
            style: AppText.translation.copyWith(
              fontSize: 15,
              height: 1.6,
              color: AppColors.ink,
            ),
          ),
        ],
        // СПАСАТЕЛИ — the accent of this screen (записка «Акцент один на экран»). They are what the
        // day opens on, and the line under them says why, which is the difference between five
        // phrases and a chore.
        if (shelves.rescue.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s22),
          _RescueBlock(phrases: shelves.rescue),
        ],
        // THE SHELVES, IN THE ORDER THE SITTING DEALS THEM (канон §11), each under the caption the
        // session uses for it. It used to be «Фразы» and «Слова» — two buckets that told «Тебе
        // скажут» from «Ты ответишь» by nothing at all, which is exactly how the interlocutor's
        // question came to sit among the learner's own lines (Д-8).
        for (final shelf in shelves.blocks) ...[
          const SizedBox(height: AppSpacing.s22),
          _ShelfBlock(
            label: shelf.label(l),
            note: shelf.shelf == PlanTermRow.shelfHear ? l.planDayRoleOnlyUnderstand : null,
            terms: shelf.terms,
            collapsed: shelves.started,
          ),
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
          label: shelves.started ? l.planDayContinueLeft(shelves.left) : l.planDayTrain,
          minHeight: 52,
          onPressed: () => _train(context, ref),
        ),
        const SizedBox(height: 10),
        // THE COMPOSITION, from the server's own material: how many cards and how many parts. A
        // count of CARDS and not of minutes — «прогресс считается в карточках внутри секций, а не в
        // процентах времени», so a pause in the middle breaks nothing.
        Center(
          child: Text(
            l.planDayComposition(shelves.cards, shelves.sections),
            style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
          ),
        ),
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

/// ПРОГОН ПЕРЕД СОБЫТИЕМ — кадр D·11.
///
/// «Прогон не притворяется днём»: у него нет разогрева и секций, только список сцен и то, что о
/// каждой известно. Every teaching day of the plan is a scene, they run one after another, out loud,
/// and a scene nobody trained goes in as it is rather than blocking the run.
///
/// ## Про сцены нет процентов, и это правило, а не пропуск
///
/// «Готовность к сцене приходит от сервера одним числом. Дизайн не считает её из ступеней и не
/// показывает, пока сервер не вернул значение» (записка серии «День v1»). The server computes
/// readiness for the PLAN and not per scene, so the frame's «готов 60%» has nothing behind it yet.
/// What the client honestly knows is each day's STATUS, and that is what the rows say.
class _FinalDay extends ConsumerWidget {
  const _FinalDay({required this.plan, required this.day, this.targetLang});

  final LearningPlan plan;
  final PlanDay day;
  final String? targetLang;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final scenes = plan.days.where((d) => d.kind != PlanDayKind.finalRun).toList(growable: false);

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
        if (scenes.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s22),
          PlanLabel(l.planRehearsalScenes, color: AppColors.tertiary, fontSize: 11.5),
          const SizedBox(height: 8),
          for (final scene in scenes)
            _RehearsalSceneRow(scene: scene, passed: scene.index < plan.focusDayIndex),
          const SizedBox(height: 10),
          Text(
            l.planRehearsalNoPercent,
            style: AppText.translation.copyWith(
              fontSize: 13,
              height: 1.5,
              color: AppColors.tertiary,
            ),
          ),
        ],
        const SizedBox(height: AppSpacing.s22),
        Center(
          child: Text(
            l.planRehearsalAloudNote,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(fontSize: 13.5, color: AppColors.secondary),
          ),
        ),
        const SizedBox(height: 10),
        PrimaryButton(
          label: l.planRehearsalStart,
          minHeight: 52,
          onPressed: () => _run(context, ref),
        ),
        // «ШПАРГАЛКА ПОД РУКОЙ» is a promise the screen has to keep: the run-through has no options
        // and no prompts, so the sheet is the only thing to reach for. It opens on the FOCUS scene
        // — the one the learner is least sure of — and returns here.
        const SizedBox(height: AppSpacing.s12),
        QuietButton(
          label: l.planCheatSheet,
          onPressed: () => showPlanCheatSheet(
            context,
            planId: plan.id,
            dayIndex: scenes.isEmpty ? plan.focusDayIndex : scenes.last.index,
            targetLang: plan.targetLang,
          ),
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
        // [PlanRehearsalDone] is a BODY, not a screen — the run-through returns it inside the
        // session's own Scaffold. Pushed bare it lands under no Material at all, and Flutter draws
        // every line of it in the debug face: yellow double underline on black (caught on the
        // simulator, кадр D·12). The Scaffold is what the other call site already gives it.
        builder: (_) => Scaffold(
          body: PlanRehearsalDone(
            planId: plan.id,
            onDone: () => Navigator.of(context).pop(),
          ),
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

/// ONE SCENE IN THE RUN-THROUGH — its number, its name, and what is honestly known about it.
///
/// «Нетренированная сцена признаётся вслух и не блокирует прогон» (кадр D·11): a day that was never
/// walked says so, under its own name, and the run-through takes it as it is.
class _RehearsalSceneRow extends StatelessWidget {
  const _RehearsalSceneRow({required this.scene, required this.passed});

  final PlanDay scene;

  /// The day is behind the focus — the SAME rule the day list uses for «День N пройден».
  ///
  /// Both facts are the server's and they can disagree: the focus is computed live off the
  /// standings, and `learning_plan_days.status` is written when a sitting ends. On this plan's
  /// day 2 they did — focus 3, status `ready` — and two screens about one day gave two answers,
  /// which is С-11 in a different costume. One rule, and it is the list's, because that is the
  /// screen the learner came from.
  final bool passed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final untrained = !passed && !scene.status.hasMaterial;
    final status = passed
        ? l.planRehearsalScenePassed
        : switch (scene.status) {
            PlanDayStatus.done => l.planRehearsalScenePassed,
            PlanDayStatus.ready => l.planRehearsalSceneReady,
            _ => l.planRehearsalSceneUntrained,
          };

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: PlanLabel(
                  l.planDialogueScene(scene.index),
                  color: AppColors.tertiary,
                  fontSize: 11,
                ),
              ),
              Text(
                status,
                style: AppText.blockLabel.copyWith(
                  color: passed ? AppColors.brassInk : AppColors.tertiary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(scene.title, style: AppText.termInList.copyWith(fontSize: 16, height: 1.35)),
          if (untrained) ...[
            const SizedBox(height: 3),
            Text(
              l.planRehearsalUntrainedNote,
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
            ),
          ],
        ],
      ),
    );
  }
}

/// THE DAY'S MATERIAL, sorted into the parts the sitting deals it in — канон §11.
///
/// One pass over the day's terms, because every number on this screen is counted off the same list:
/// what is on each shelf, how many cards there are, how many parts, how many have closed stage A,
/// and therefore whether the day has been STARTED at all.
class _Shelves {
  const _Shelves({
    required this.blocks,
    required this.rescue,
    required this.cards,
    required this.closed,
  });

  /// The scene's own shelves, in the order the sitting reaches them. Empty ones are absent — «блок
  /// без данных не рисуется» is this product's rule, and «0 фраз» is not a sentence it says.
  final List<_ShelfGroup> blocks;

  /// The plan's five phrases. They belong to the PLAN and not to this scene (канон §5), so they are
  /// their own block above the shelves rather than a sixth one among them.
  final List<PlanTermRow> rescue;

  /// The scene's own cards, and how many of them have closed stage A.
  final int cards, closed;

  int get left => (cards - closed).clamp(0, cards);

  /// The parts the sitting has — the shelves plus the warm-up, which always runs first.
  int get sections => blocks.length + (rescue.isEmpty ? 0 : 1);

  /// The learner has been here before: something of this scene has already closed its first rung.
  bool get started => closed > 0 && closed < cards;

  static _Shelves of(PlanDayDetail detail) {
    final rescue = <PlanTermRow>[];
    final byShelf = <String, List<PlanTermRow>>{};
    var cards = 0;
    var closed = 0;

    for (final term in detail.terms) {
      if (term.shelf == PlanTermRow.shelfRescue) {
        rescue.add(term);

        continue;
      }
      // The scene's OWN cards. A term carried in from an earlier day is that day's scene and is
      // dealt in this sitting's seam, not on this screen: the day screen is about one scene.
      if (term.fromDayIndex != detail.day.index) continue;

      cards++;
      // The same rule the server's census uses: past stage A, or standing on A with every trainer
      // of it ticked. Two places, one definition, or the screen and the plan card disagree about a
      // card the learner has just finished.
      if (term.stage != PlanStage.a || term.stageComplete) closed++;
      byShelf.putIfAbsent(term.shelf ?? _shelfUnknown, () => []).add(term);
    }

    final blocks = <_ShelfGroup>[];
    for (final shelf in _order) {
      final terms = byShelf.remove(shelf);
      if (terms != null && terms.isNotEmpty) blocks.add(_ShelfGroup(shelf, terms));
    }
    // A shelf this build has never heard of, and every day written before the shelves existed: one
    // undivided block at the end rather than silence.
    for (final entry in byShelf.entries) {
      blocks.add(_ShelfGroup(entry.key, entry.value));
    }

    return _Shelves(blocks: blocks, rescue: rescue, cards: cards, closed: closed);
  }

  static const _shelfUnknown = '';

  /// КАНОН §11, and it is the same list the session's own parts are in.
  static const _order = [
    PlanTermRow.shelfWords,
    PlanTermRow.shelfChunks,
    PlanTermRow.shelfHear,
    PlanTermRow.shelfSay,
    PlanTermRow.shelfAsk,
    PlanTermRow.shelfNumbers,
  ];
}

class _ShelfGroup {
  const _ShelfGroup(this.shelf, this.terms);

  final String shelf;
  final List<PlanTermRow> terms;

  String label(AppLocalizations l) => switch (shelf) {
    PlanTermRow.shelfWords || PlanTermRow.shelfChunks => l.planShelfWords,
    PlanTermRow.shelfHear => l.planShelfHear,
    PlanTermRow.shelfSay => l.planShelfSay,
    PlanTermRow.shelfAsk => l.planShelfAsk,
    PlanTermRow.shelfNumbers => l.planSectionNumbers,
    // A day with no shelves at all — its material under the caption it has always had.
    _ => l.planDayPhrases,
  };
}

/// СПАСАТЕЛИ on the day screen — the accent, and the one block that says why it is here.
class _RescueBlock extends StatelessWidget {
  const _RescueBlock({required this.phrases});

  final List<PlanTermRow> phrases;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return PaperCard(
      radius: 16,
      padding: const EdgeInsets.fromLTRB(18, 15, 18, 15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: PlanLabel(l.planDialogueRescue)),
              Text(
                l.planDialogueRescuePhrases(phrases.length),
                style: AppText.blockLabel.copyWith(color: AppColors.brassInk),
              ),
            ],
          ),
          const SizedBox(height: 8),
          // ONE of them, quoted. Five would be a list to read; one is an example of what they are.
          Text(
            '«${phrases.first.text}»',
            style: AppText.collectionNameCard.copyWith(fontSize: 16, height: 1.35),
          ),
          const SizedBox(height: 6),
          Text(
            l.planDayRescueLead,
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

/// ONE SHELF OF THE SCENE — its caption, what it is for, and its lines.
///
/// The interlocutor's shelf marks every line «он» and sets it in italic: курсив — только чужая речь,
/// and a register where every line looks identical is exactly what put the doctor's question among
/// the learner's own (Д-8).
///
/// A STARTED day shows two lines and a count («и ещё 3», кадр D·01в): the learner has read this
/// already, and what they came back for is the button.
class _ShelfBlock extends StatelessWidget {
  const _ShelfBlock({
    required this.label,
    required this.terms,
    this.note,
    this.collapsed = false,
  });

  final String label;
  final String? note;
  final List<PlanTermRow> terms;
  final bool collapsed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final shown = collapsed && terms.length > 2 ? terms.take(2).toList() : terms;
    final hidden = terms.length - shown.length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: PlanLabel(label, color: AppColors.tertiary, fontSize: 11.5)),
            if (note != null)
              Text(
                note!,
                style: AppText.translation.copyWith(fontSize: 11.5, color: AppColors.brassInk),
              ),
          ],
        ),
        const SizedBox(height: 8),
        for (final term in shown) _ShelfLine(term: term),
        if (hidden > 0)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              l.planDaySectionPart(shown.length, terms.length),
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
            ),
          ),
      ],
    );
  }
}

/// One line of a shelf — «он» in the margin when it is the interlocutor's.
class _ShelfLine extends StatelessWidget {
  const _ShelfLine({required this.term});

  final PlanTermRow term;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final role = term.isRecognitionOnly;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: AppColors.dividerFaint),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (role) ...[
            // «он» — a mark in the left margin, in the mono face. The type says whose line it is
            // before the caption does.
            Text(
              l.planSpeakerRoleShort,
              style: AppText.blockLabel.copyWith(color: AppColors.brassInk),
            ),
            const SizedBox(width: 10),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  term.text,
                  style: role
                      ? AppText.collectionNameCard.copyWith(
                          fontSize: 15,
                          height: 1.45,
                          fontStyle: FontStyle.italic,
                        )
                      : AppText.termInList.copyWith(fontSize: 15, height: 1.45),
                ),
                if ((term.translation ?? '').isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    term.translation!,
                    style: AppText.translation.copyWith(
                      fontSize: 13,
                      color: AppColors.secondary,
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
}

/// «РАЗГОВОР» AS A LOCKED PLATE IS GONE from this screen, and so are «Фразы» and «Слова».
///
/// The plate promised the conversation the plan is for; the conversation exists now and is played
/// inside the sitting (наряд DAY-2, Ч.3), so a lock over it would be the app hiding something the
/// learner has already done. The two buckets went with the shelves: «Тебе скажут» and «Ты ответишь»
/// are two different things and were drawn identically, which is Д-8 seen on the day screen.
class _DayBar extends StatelessWidget {
  const _DayBar({required this.label, this.onCheatSheet});

  final String label;

  /// «Шпаргалка» — the sheet, opened from the header (кадр D·01). Null on a day that has no
  /// material to show one from.
  final VoidCallback? onCheatSheet;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return SizedBox(
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
          if (onCheatSheet == null)
            const SizedBox(width: AppSpacing.minTap)
          else
            Padding(
              padding: const EdgeInsets.only(right: 6),
              child: InkWell(
                onTap: onCheatSheet,
                borderRadius: BorderRadius.circular(8),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                  child: Text(
                    l.planCheatSheet,
                    style: AppText.blockLabel.copyWith(
                      color: AppColors.brassInk,
                      letterSpacing: .4,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
