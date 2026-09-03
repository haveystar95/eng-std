import 'dart:async';

import 'package:flutter/cupertino.dart' show CupertinoDatePicker, CupertinoDatePickerMode;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/api_client.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'entry/entry_ui.dart';
import 'plan_building_screen.dart';
import 'plan_ui.dart';

/// ПРЕВЬЮ ПЛАНА СЦЕНАМИ — кадры V4·06 (с датой) и 06б (без даты).
///
/// The main screen of the entry: the first time a person sees their own route. It is a LADDER OF
/// SCENES and not a table of counts — each card is a day with a name and two or three sentences of
/// вводка in the learner's own language, and the numbers that appear (days, minutes, the date) are
/// the server's own, printed once, in one line.
///
/// ## What the composition promises
///
/// The title is the goal. The subtitle leads with «по твоим словам», because everything below it
/// was written from the sentence the learner typed and that is the claim the screen is making. The
/// rescue kit shows ONE of its five phrases живьём — «чтобы обещание было осязаемым» — and the
/// rehearsal is dashed, because it is the one card that is not a day of teaching.
///
/// Day 1 is the only accented card. «Первый день на полтона темнее и на строку подробнее: это
/// единственный акцент лестницы, дни 2–4 держат ровный ритм.»
///
/// ## Undated is a different plan, not a plan missing a field
///
/// «Дни» become «сцены», the countdown disappears, the rehearsal moves from «накануне» to «в
/// конце», and the second button offers the one thing the plan lacks. Nothing invents a date to
/// fill the gap.
///
/// Nothing has been generated yet: the skeleton is one call already paid for, and «Начать» is the
/// commitment. Walking back costs nothing.
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
  /// Kept on the screen and not on the server: `deadline_tight` is a FACT about the calendar, and
  /// «я понял» is not a change to the plan.
  bool _tightAcknowledged = false;

  bool get _dated => (_plan.eventDate ?? '').isNotEmpty;

  List<PlanDay> get _scenes =>
      _plan.days.where((d) => d.kind == PlanDayKind.intro).toList(growable: false);

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

  /// «Поставить дату» — the one thing an undated plan is missing (кадр V4·06б).
  ///
  /// The same free re-schedule «Добавить 20 минут» uses: the outline is already written, and laying
  /// its scenes onto a calendar is arithmetic, not a model call.
  Future<void> _setDate() async {
    AppHaptics.light();
    final today = DateTime.now();
    var chosen = DateTime(today.year, today.month, today.day).add(const Duration(days: 2));

    final result = await showAppBottomSheet<DateTime>(
      context: context,
      builder: (sheetContext) {
        final l = AppLocalizations.of(sheetContext);

        return Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.planWhenSheetTitle, style: AppText.sheetButton.copyWith(fontSize: 17)),
            SizedBox(
              height: 216,
              child: CupertinoDatePicker(
                mode: CupertinoDatePickerMode.date,
                initialDateTime: chosen,
                minimumDate: DateTime(today.year, today.month, today.day),
                maximumDate: today.add(const Duration(days: 365)),
                onDateTimeChanged: (d) => chosen = DateTime(d.year, d.month, d.day),
              ),
            ),
            const SizedBox(height: AppSpacing.s12),
            PrimaryButton(
              label: l.commonSave,
              onPressed: () => Navigator.of(sheetContext).pop(chosen),
            ),
          ],
        );
      },
    );

    if (result == null || !mounted) return;
    final iso =
        '${result.year.toString().padLeft(4, '0')}-${result.month.toString().padLeft(2, '0')}-${result.day.toString().padLeft(2, '0')}';
    await _mutate((api) => api.reschedulePlan(_plan.id, eventDate: iso));
  }

  /// «Убрать сцену» — offered only inside the «срок мал» card, because that is the only reason to.
  Future<void> _dropScene() async {
    final l = AppLocalizations.of(context);
    final scenes = _scenes;
    if (scenes.length <= 1) {
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
          for (final scene in scenes)
            AppSheetRow(
              title: Text(
                '${l.planDayNumber(scene.index)} · ${scene.title}',
                style: AppText.translation.copyWith(fontSize: 15),
              ),
              onTap: () => Navigator.of(sheetContext).pop(scene.index),
            ),
        ],
      ),
    );

    if (chosen != null) {
      await _mutate((api) => api.reschedulePlan(_plan.id, dropDayIndex: chosen));
    }
  }

  /// «Начать первый день» — the commitment. Days start generating and words start being held.
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
      ref.invalidate(activePlanProvider);
      // …and THIS is the moment to ask about notifications: the learner has just told the app there
      // is something they care about. Fire-and-forget — a refusal costs the plan nothing.
      unawaited(ref.read(planNotificationsProvider).requestPermission());
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
    final scenes = _scenes;
    final summary = (_plan.goalRestated ?? '').trim().isNotEmpty
        ? _plan.goalRestated!.trim()
        : _plan.goalText.trim();

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                EntryHeader(
                  kicker: l.planPreviewKicker,
                  step: 4,
                  steps: 4,
                  onBack: () => Navigator.of(context).maybePop(),
                  trailing: const SizedBox.shrink(),
                ),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.only(top: 26, bottom: 34),
                    children: [
                      // ─ the header block, first ────────────────────────────────────────────
                      _Appear(
                        delay: Duration.zero,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              _plan.title,
                              style: AppText.collectionNameScreen.copyWith(
                                fontSize: 32,
                                height: 1.18,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Text(
                              _dated
                                  ? l.planPreviewSubtitle(summary, scenes.length)
                                  : l.planPreviewSubtitleNoDate(summary, scenes.length),
                              style: AppText.translation.copyWith(
                                fontSize: 15,
                                height: 1.6,
                                color: AppColors.inkBody,
                              ),
                            ),
                          ],
                        ),
                      ),
                      // ─ …then the orientation line, 80 ms later ────────────────────────────
                      _Appear(
                        delay: const Duration(milliseconds: 80),
                        child: Container(
                          margin: const EdgeInsets.only(top: 18),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          decoration: BoxDecoration(
                            border: Border(
                              top: BorderSide(
                                color: AppColors.brassInk.withValues(alpha: 0.32),
                              ),
                              bottom: BorderSide(
                                color: AppColors.brassInk.withValues(alpha: 0.32),
                              ),
                            ),
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: PlanLabel(
                                  _dated
                                      ? l.planPreviewOrientation(
                                          scenes.length,
                                          _plan.minutesPerDay,
                                        )
                                      : l.planPreviewOrientationNoDate(
                                          scenes.length,
                                          _plan.minutesPerDay,
                                        ),
                                  fontSize: 11,
                                ),
                              ),
                              if (_dated) ...[
                                const SizedBox(width: 12),
                                PlanLabel(
                                  planDateOrNone(context, _plan.eventDate, short: true),
                                  fontSize: 11,
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                      if (!(_plan.computed?.fits ?? true) && !_tightAcknowledged) ...[
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
                          onDropScene: _busy ? null : _dropScene,
                          onKeep: () => setState(() => _tightAcknowledged = true),
                        ),
                      ],
                      // ─ …then the ladder, 60 ms apart, never longer than 400 ms in total ───
                      const SizedBox(height: 28),
                      _Appear(
                        delay: const Duration(milliseconds: 140),
                        child: EntryOverline(
                          _dated ? l.planPreviewDaysTitle : l.planPreviewScenesTitle,
                        ),
                      ),
                      const SizedBox(height: 16),
                      for (var i = 0; i < scenes.length; i++) ...[
                        if (i > 0) const SizedBox(height: 11),
                        _Appear(
                          delay: Duration(milliseconds: 180 + 60 * i.clamp(0, 4)),
                          child: _SceneCard(
                            label: _dated
                                ? l.planPreviewDayLabel(scenes[i].index)
                                : l.planPreviewSceneLabel(scenes[i].index),
                            title: scenes[i].title,
                            intro: scenes[i].intro,
                            accent: i == 0,
                          ),
                        ),
                      ],
                      // ─ …and the rescue kit and the rehearsal last ─────────────────────────
                      const SizedBox(height: 22),
                      _Appear(
                        delay: const Duration(milliseconds: 420),
                        child: _RescueCard(
                          body: l.planPreviewRescueBody,
                          quote: l.planPreviewRescueQuote,
                        ),
                      ),
                      const SizedBox(height: 14),
                      _Appear(
                        delay: const Duration(milliseconds: 460),
                        child: _RehearsalCard(
                          label: _dated
                              ? l.planPreviewRehearsalEveLabel
                              : l.planPreviewRehearsalEndLabel,
                          title: l.planPreviewRehearsalTitle,
                          body: _dated
                              ? l.planPreviewRehearsalEveBody(scenes.length)
                              : l.planPreviewRehearsalEndBody,
                        ),
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 18),
                        Text(
                          _error!,
                          style: AppText.translation.copyWith(
                            fontSize: 13.5,
                            height: 1.45,
                            color: AppColors.destructiveText,
                          ),
                        ),
                      ],
                      // ─ the CTA appears with no delay: it must not wait for an animation ───
                      const SizedBox(height: 28),
                      EntryCta(
                        label: _dated ? l.planPreviewStartDay : l.planPreviewStartScene,
                        minHeight: 56,
                        enabled: !_busy,
                        onPressed: _start,
                      ),
                      const SizedBox(height: 16),
                      // The subscription slot. A PLACEHOLDER that says it is one — the paywall is
                      // not this наряд's, and a price invented here is a price somebody ships.
                      Center(
                        child: _busy
                            ? PlanBusyLine(text: l.planBuilderBusyLine)
                            : Text(
                                l.planPricePlaceholder,
                                textAlign: TextAlign.center,
                                style: AppText.translation.copyWith(
                                  fontSize: 12.5,
                                  height: 1.5,
                                  color: AppColors.tertiary,
                                ),
                              ),
                      ),
                      const SizedBox(height: 10),
                      Center(
                        child: Semantics(
                          button: true,
                          child: InkWell(
                            onTap: _busy
                                ? null
                                : (_dated
                                      ? () {
                                          AppHaptics.light();
                                          Navigator.of(context).maybePop();
                                        }
                                      : _setDate),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                              child: Text(
                                _dated ? l.planPreviewEditAnswers : l.planPreviewSetDate,
                                style: AppText.translation.copyWith(
                                  fontSize: 14,
                                  color: AppColors.secondary,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 34),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// One scene of the ladder — the number in brass, the name in the serif, the вводка under it.
class _SceneCard extends StatelessWidget {
  const _SceneCard({
    required this.label,
    required this.title,
    required this.intro,
    required this.accent,
  });

  final String label, title, intro;

  /// Day 1 (or scene 1) — the ONE accent of the ladder.
  final bool accent;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(18, 17, 18, 17),
    decoration: BoxDecoration(
      color: accent ? AppColors.planSelected : AppColors.surfaceRaised,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(
        color: accent
            ? AppColors.brassInk.withValues(alpha: 0.34)
            : AppColors.dividerFaint,
      ),
      boxShadow: [
        BoxShadow(
          color: AppColors.ink.withValues(alpha: accent ? 0.08 : 0.045),
          blurRadius: accent ? 14 : 12,
          offset: Offset(0, accent ? 4 : 3),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            PlanLabel(label, fontSize: 10.5),
            const SizedBox(width: 11),
            Expanded(
              child: Container(
                height: 1,
                color: AppColors.brassInk.withValues(alpha: accent ? 0.32 : 0.25),
              ),
            ),
          ],
        ),
        const SizedBox(height: 9),
        Text(title, style: AppText.collectionNameCard.copyWith(fontSize: 21, height: 1.25)),
        if (intro.trim().isNotEmpty) ...[
          const SizedBox(height: 7),
          Text(
            intro,
            style: AppText.translation.copyWith(
              fontSize: 14,
              height: 1.55,
              color: AppColors.inkBody,
            ),
          ),
        ],
      ],
    ),
  );
}

/// The rescue kit, with one of its five phrases quoted — the promise made tangible.
class _RescueCard extends StatelessWidget {
  const _RescueCard({required this.body, required this.quote});

  final String body, quote;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
    decoration: BoxDecoration(
      color: AppColors.planSelected,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AppColors.brassInk.withValues(alpha: 0.34)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(top: 2),
          child: Icon(Icons.favorite_border, size: 18, color: AppColors.brassInk),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                body,
                style: AppText.translation.copyWith(
                  fontSize: 14,
                  height: 1.55,
                  color: AppColors.inkBody,
                ),
              ),
              const SizedBox(height: 7),
              // ITALIC, and the one italic of the series: «курсив маркирует чужую речь».
              Text(
                quote,
                style: AppText.collectionNameCard.copyWith(
                  fontSize: 14.5,
                  height: 1.5,
                  fontStyle: FontStyle.italic,
                  fontWeight: FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// The rehearsal — dashed, because it is the one card that teaches nothing new.
class _RehearsalCard extends StatelessWidget {
  const _RehearsalCard({required this.label, required this.title, required this.body});

  final String label, title, body;

  @override
  Widget build(BuildContext context) => DottedBorderBox(
    radius: 18,
    color: AppColors.brassInk.withValues(alpha: 0.45),
    padding: const EdgeInsets.fromLTRB(18, 17, 18, 17),
    child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              PlanLabel(label, fontSize: 10.5),
              const SizedBox(width: 11),
              Expanded(
                child: Container(
                  height: 1,
                  color: AppColors.brassInk.withValues(alpha: 0.25),
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          Text(title, style: AppText.collectionNameCard.copyWith(fontSize: 21, height: 1.25)),
          const SizedBox(height: 7),
          Text(
            body,
            style: AppText.translation.copyWith(
              fontSize: 14,
              height: 1.55,
              color: AppColors.inkBody,
            ),
          ),
      ],
    ),
  );
}

/// «Срок мал» — kept from направление Б, and the ONLY thing on this screen the frames do not draw.
///
/// It appears only when the server says the plan does not fit, and it is the only place the learner
/// is told that scenes were dropped. Without it that fact is silent: the ladder simply comes back
/// shorter than the goal deserved, and nothing on the screen says why.
class _TightCard extends StatelessWidget {
  const _TightCard({
    required this.plan,
    required this.busy,
    required this.onAddMinutes,
    required this.onDropScene,
    required this.onKeep,
  });

  final LearningPlan plan;
  final bool busy;
  final VoidCallback onAddMinutes;
  final VoidCallback? onDropScene;
  final VoidCallback onKeep;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final computed = plan.computed;
    final dropped = computed?.dropped ?? const <PlanDroppedSkill>[];
    // Both halves. A list of only the losses reads as a failure, and this plan still teaches most
    // of what was asked.
    final kept = [
      for (final day in computed?.days ?? const <PlanComputedDay>[]) ...day.outcomes,
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
          for (final text in kept.take(3)) PlanAbilityRow(text: text, hit: true, divider: false),
          for (final skill in dropped.take(3))
            PlanAbilityRow(text: skill.outcome, hit: false, divider: false),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: EntrySecondary(
                  label: l.planTightAddMinutes(20),
                  minHeight: 46,
                  enabled: !busy,
                  onPressed: onAddMinutes,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: EntrySecondary(
                  label: l.planPreviewDropDay,
                  minHeight: 46,
                  enabled: !busy && onDropScene != null,
                  onPressed: onDropScene,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Center(
            child: InkWell(
              onTap: onKeep,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                child: Text(
                  l.planTightKeep,
                  style: AppText.translation.copyWith(
                    fontSize: 13.5,
                    color: AppColors.secondary,
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

/// One block arriving — 12 pt up and a fade, at the delay the записка gives it.
///
/// A widget rather than a controller per screen: the order of appearance is a property of the
/// COMPOSITION («заголовок и подзаголовок первыми, затем строка-ориентир, затем лестница»), so it
/// belongs beside the block it delays and not in a list of intervals somewhere else.
class _Appear extends StatefulWidget {
  const _Appear({required this.delay, required this.child});

  final Duration delay;
  final Widget child;

  @override
  State<_Appear> createState() => _AppearState();
}

class _AppearState extends State<_Appear> {
  bool _in = false;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer(widget.delay, () {
      if (mounted) setState(() => _in = true);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedSlide(
    offset: _in ? Offset.zero : const Offset(0, 0.035),
    duration: const Duration(milliseconds: 240),
    curve: Curves.easeOut,
    child: AnimatedOpacity(
      opacity: _in ? 1 : 0,
      duration: const Duration(milliseconds: 240),
      child: widget.child,
    ),
  );
}
