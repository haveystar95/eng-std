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

/// ONE DAY OF A PLAN — ПРОГРАММА ДНЯ ЦЕЛИКОМ (наряд DAY-FIX-2, Ч.4; кадр D·08 расходится по решению
/// владельца 05.09, см. design-map).
///
/// Человек открывает день и видит ВСЁ, что будет: спасатели, слова, связки, что ему скажут, что он
/// ответит, что спросит — каждая строка с переводом, каждая секция под своим заголовком и со словом
/// о том, что с ней сегодня делать («познакомишься», «выберешь ответ», «соберёшь из блоков»…).
/// «Шпаргалки» больше нет: этот экран и есть она.
///
/// В шапке — ОДНО слово состояния и минуты, оба серверные (`day_state`, `minutes_left`, Ч.3). Одна
/// кнопка, и она говорит то же, что слово: «Начать день» / «Продолжить» / «Пройти ещё раз». Цифры
/// на экране — только минуты; ни «N из M», ни «карточек · секций» здесь нет.
///
/// A day AHEAD of the focus is openable — the frames say «можно открыть раньше» rather than drawing
/// a lock — and since E2E-SIM-2 (С-1) it counts like any other day.
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
                    // may have been rebuilt since, and one being written turns into material.
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

    // THE FINAL DAY IS NOT AN UNBUILT DAY (Д-27): it is a run-through of everything the plan taught.
    if (day.kind == PlanDayKind.finalRun) {
      return _FinalDay(plan: plan, day: day, targetLang: detail.targetLang);
    }

    if (!day.status.hasMaterial) {
      return _NotWrittenYet(plan: plan, day: day);
    }

    final program = _DayProgram.of(detail);

    return ListView(
      // Always scrollable, so the pull works on a day whose content does not fill the screen.
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        18,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      children: [
        // ОДНО СЛОВО О ДНЕ, серверное, и минуты рядом (Ч.3). Та же функция, что на вкладке «План»
        // и в шапке присеста — три экрана не могут разойтись, потому что читают одно поле.
        PlanLabel(
          planDayStateWord(
            l,
            day.dayState,
            day.minutesLeft,
            conversationMinutes: day.conversationMinutes,
          ),
          color: day.dayState == PlanDayState.done ? AppColors.brassInk : AppColors.tertiary,
        ),
        const SizedBox(height: 10),
        Text(day.title, style: AppText.collectionNameScreen.copyWith(fontSize: 27, height: 1.2)),
        // МИНУТЫ ОБОИХ ПРИСЕСТОВ (наряд DAY-FIX-3, Ч.5.1): «материал около 12 минут · разговор
        // около 6 минут» — человек видит, что день — два захода, и сколько каждый стоит.
        if (planDaySittingMinutes(
              l,
              material: day.materialMinutes,
              conversation: day.conversationMinutes,
            )
            case final sittings?) ...[
          const SizedBox(height: 6),
          Text(
            sittings,
            style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
          ),
        ],
        // THE ВВОДКА IS THE MAIN TEXT OF THE SCREEN, in the ink colour and not in grey (записка
        // «Вводка»): a person reads the situation before the lines.
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
        // THE PROGRAM, section by section, in the order the sitting deals them (канон §11): every
        // line with its translation, and under each caption the word for what happens to it today.
        for (final section in program.sections) ...[
          const SizedBox(height: AppSpacing.s22),
          _SectionBlock(section: section),
        ],
        const SizedBox(height: AppSpacing.s22),
        if (!_isFocus) ...[
          // Said BEFORE the button, because looking ahead is a legitimate thing to want and the
          // learner should know which day they are about to walk.
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
        // ОДНА КНОПКА, и она говорит то же, что слово состояния сверху.
        PrimaryButton(
          label: planDayAction(l, day.dayState),
          minHeight: 52,
          onPressed: () => _train(context, ref),
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
        // «ШПАРГАЛКИ» ЗДЕСЬ БОЛЬШЕ НЕТ (DAY-FIX-2, Ч.4.4): каждая сцена открывается со вкладки «План»
        // как программа дня с переводами, и это она и есть.
        //
        // «ЗАВЕРШИТЬ ПЛАН» — the ending the canon promises, reachable from the screen the learner is
        // actually standing on (E2E-SIM-2, С-10). Quiet rather than primary: the button above is
        // what to do NOW, and finishing is what to do when there is nothing left to do.
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
        // [PlanRehearsalDone] is a BODY, not a screen — pushed bare it lands under no Material at
        // all, and Flutter draws every line of it in the debug face (caught on the simulator).
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
    // The run-through closes the plan, so every plan surface is re-read.
    ref.invalidate(planProvider(plan.id));
    ref.invalidate(activePlanProvider);
    ref.invalidate(planArchiveProvider);
    if (context.mounted) Navigator.of(context).maybePop();
  }
}

/// A day the server has not written yet — and THREE states, not one.
///
/// The plan generates one day at a time and only a couple ahead of the focus, so a day further out
/// legitimately has a title and nothing else: «Собрать день» asks for it. A day that FAILED is not
/// the same thing, and a day that failed TWICE is a third thing again: the server claims a day at
/// most twice ({@link PlanDay::MAX_ATTEMPTS}), and after that the only thing that CAN help is one
/// more attempt for the day, or building the plan again.
class _NotWrittenYet extends ConsumerWidget {
  const _NotWrittenYet({required this.plan, required this.day});

  final LearningPlan plan;
  final PlanDay day;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final exhausted = day.outOfAttempts;

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
          exhausted
              ? l.planDayExhaustedLead
              : (day.status == PlanDayStatus.failed ? l.planDayFailed : l.planDayNotWritten),
          style: AppText.translation.copyWith(
            fontSize: 14.5,
            height: 1.6,
            color: AppColors.secondary,
          ),
        ),
        // THE ACTUAL CAUSE, on its own line and read off the server's `fail_code` (Д-19).
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
          // ONE MORE ATTEMPT FOR THIS DAY, before the sentence about the whole plan. It spends
          // money, so it is pressed by a person and never polled.
          PrimaryButton(
            label: l.planDayRebuildDay,
            minHeight: 52,
            onPressed: () => _rebuildDay(context, ref),
          ),
          const SizedBox(height: AppSpacing.s12),
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

  /// Give THIS day one more attempt and watch it being written — the building screen from there on.
  Future<void> _rebuildDay(BuildContext context, WidgetRef ref) async {
    AppHaptics.light();
    try {
      await ref.read(apiClientProvider).rebuildPlanDay(plan.id, day.index);
    } catch (_) {
      // The building screen answers for the failure in its own words — it polls the same day.
    }
    if (!context.mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => PlanBuildingScreen(plan: plan, dayIndex: day.index)),
    );
  }

  /// The same act as the plan screen's own «Отказаться от плана», through the same function.
  Future<void> _rebuild(BuildContext context, WidgetRef ref) async {
    if (!await abandonPlan(context, ref, plan.id) || !context.mounted) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
  }
}

/// ONE SCENE IN THE RUN-THROUGH — its number, its name, and what is honestly known about it.
class _RehearsalSceneRow extends StatelessWidget {
  const _RehearsalSceneRow({required this.scene, required this.passed});

  final PlanDay scene;

  /// The day is behind the focus — the SAME rule the day list uses for «пройден».
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

/// THE PROGRAM OF THE DAY — six sections in the order the sitting deals them (канон §11).
///
/// One pass over the day's terms. Rescue phrases belong to the PLAN (канон §5) and open the day;
/// then the scene's own shelves. A shelf with nothing on it is absent: «0 фраз» is not a sentence
/// this product says.
class _DayProgram {
  const _DayProgram(this.sections);

  final List<_Section> sections;

  static _DayProgram of(PlanDayDetail detail) {
    final byShelf = <String, List<PlanTermRow>>{};
    for (final term in detail.terms) {
      // The scene's OWN cards and the plan's rescue phrases. A term carried in from an earlier day
      // is that day's scene and is dealt in the seam, not on this screen: the day screen is about
      // one scene.
      if (term.shelf != PlanTermRow.shelfRescue && term.fromDayIndex != detail.day.index) continue;
      byShelf.putIfAbsent(term.shelf ?? _shelfUnknown, () => []).add(term);
    }

    final sections = <_Section>[];
    for (final shelf in _order) {
      final terms = byShelf.remove(shelf);
      if (terms != null && terms.isNotEmpty) sections.add(_Section(shelf, terms));
    }
    // A shelf this build has never heard of, and every day written before the shelves existed: one
    // undivided block at the end rather than silence.
    for (final entry in byShelf.entries) {
      sections.add(_Section(entry.key, entry.value));
    }

    return _DayProgram(sections);
  }

  static const _shelfUnknown = '';

  /// КАНОН §11 — the same order the session's own parts are in: warm-up, words, chunks, then the
  /// scene as it is spoken.
  static const _order = [
    PlanTermRow.shelfRescue,
    PlanTermRow.shelfWords,
    PlanTermRow.shelfChunks,
    PlanTermRow.shelfHear,
    PlanTermRow.shelfSay,
    PlanTermRow.shelfAsk,
    PlanTermRow.shelfNumbers,
  ];
}

/// One section of the program: its shelf, its rows, and the words for what happens to them today.
class _Section {
  const _Section(this.shelf, this.terms);

  final String shelf;
  final List<PlanTermRow> terms;

  String label(AppLocalizations l) => switch (shelf) {
    PlanTermRow.shelfRescue => l.planDialogueRescue,
    PlanTermRow.shelfWords => l.planShelfWordsOnly,
    PlanTermRow.shelfChunks => l.planShelfChunks,
    PlanTermRow.shelfHear => l.planShelfHear,
    PlanTermRow.shelfSay => l.planShelfSay,
    PlanTermRow.shelfAsk => l.planShelfAsk,
    PlanTermRow.shelfNumbers => l.planSectionNumbers,
    // A day with no shelves at all — its material under the caption it has always had.
    _ => l.planDayPhrases,
  };

  /// ЧТО СЕГОДНЯ БУДЕТ С ЭТОЙ СЕКЦИЕЙ, словами — «познакомишься · переведёшь», «соберёшь из
  /// плиток», «познакомишься · соберёшь из блоков · выберешь ответ»… The codes are the server's
  /// (`next_step` + `then_steps`, Ч.4.2; DAY-FIX-3, Ч.5.1), one word per distinct code, in the
  /// order the sitting deals them. Null when nothing on the shelf is asked today.
  String? stepLine(AppLocalizations l) {
    final words = <String>[];
    for (final term in terms) {
      // Every touch of a row met today, in the order of the sitting: the server names the steps,
      // the screen only strings them.
      for (final step in [term.nextStep, ...term.thenSteps]) {
        final word = _stepWord(l, step);
        if (word != null && !words.contains(word)) words.add(word);
      }
    }

    return words.isEmpty ? null : l.planDayStepLead(words.join(' · '));
  }

  static String? _stepWord(AppLocalizations l, String? step) => switch (step) {
    PlanTermRow.stepMeet => l.planStepMeet,
    PlanTermRow.stepTranslate => l.planStepTranslate,
    PlanTermRow.stepTiles => l.planStepTiles,
    PlanTermRow.stepRecognize => l.planStepRecognize,
    PlanTermRow.stepHear => l.planStepHear,
    PlanTermRow.stepChoose => l.planStepChoose,
    PlanTermRow.stepAssemble => l.planStepAssemble,
    PlanTermRow.stepSay => l.planStepSay,
    _ => null,
  };
}

/// ONE SECTION OF THE PROGRAM — its caption, the word for today under it, and every row of it.
///
/// Every row, never «и ещё 3»: the screen is the sheet the learner comes back to before the door,
/// and a list that hides its own tail is not a sheet.
class _SectionBlock extends StatelessWidget {
  const _SectionBlock({required this.section});

  final _Section section;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final step = section.stepLine(l);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        PlanLabel(section.label(l), color: AppColors.tertiary, fontSize: 11.5),
        if (step != null) ...[
          const SizedBox(height: 4),
          Text(
            step,
            style: AppText.translation.copyWith(fontSize: 13, color: AppColors.brassInk),
          ),
        ],
        const SizedBox(height: 8),
        for (final term in section.terms) _ProgramRow(term: term),
      ],
    );
  }
}

/// One row of the program — text, translation, and the mark if the day has been walked.
///
/// The interlocutor's rows keep the one typographic rule of the series: курсив — только чужая речь,
/// and «он» in the margin. A register where every line looks identical is exactly what put the
/// doctor's question among the learner's own (Д-8).
class _ProgramRow extends StatelessWidget {
  const _ProgramRow({required this.term});

  final PlanTermRow term;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final role = term.isRecognitionOnly;
    // ОТМЕТКА СЛОВОМ — «познакомился» / «применяешь» / «говоришь сам» (Ч.4.3; DAY-FIX-3, Ч.5.2),
    // the server's, or nothing. Never a digit.
    final mark = switch (term.mark) {
      PlanTermRow.markMet => l.planMarkMet,
      PlanTermRow.markApplying => l.planMarkApplying,
      PlanTermRow.markSaidSelf => l.planMarkSaidSelf,
      _ => null,
    };

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
                // «ПО ТЕМЕ» (DAY-FIX-3, Ч.5.4): a word of the situation that stands in no line —
                // said so, or the learner wonders why it never sounds in the conversation.
                if (term.topical) ...[
                  const SizedBox(height: 4),
                  Text(
                    l.planTermTopical,
                    style: AppText.blockLabel.copyWith(color: AppColors.tertiary),
                  ),
                ],
              ],
            ),
          ),
          if (mark != null) ...[
            const SizedBox(width: 10),
            Text(mark, style: AppText.blockLabel.copyWith(color: AppColors.brassInk)),
          ],
        ],
      ),
    );
  }
}

/// The header: back, and the day's place in the plan. The «Шпаргалка» it used to carry is gone
/// (DAY-FIX-2, Ч.4.4) — the screen under it is the sheet.
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
