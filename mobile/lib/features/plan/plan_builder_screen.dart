import 'package:flutter/cupertino.dart' show CupertinoDatePicker, CupertinoDatePickerMode;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/api_client.dart';
import '../../data/languages.dart' show studyLanguagesFor;
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import 'plan_preview_screen.dart';
import 'plan_ui.dart';

/// «Составить план» — направление Б, три шага на одной карточке (кадры Б-01…Б-03).
///
/// The whole entrance is ONE screen and the learner can see that from the first frame: three
/// numbered rows, the open one expanded, the closed ones collapsed to their own answer with an
/// «Изм.». That is the difference from направление А, which grew downwards as a conversation — a
/// ribbon reads as «how many more of these are there», and this reads as «three».
///
/// What it does NOT ask is the support language. The pair is «родной аккаунта × изучаемый» (ONB-1),
/// decided once at onboarding, and the server reads it off the profile — a plan that asked again
/// would be a second place to answer one question.
class PlanBuilderScreen extends ConsumerStatefulWidget {
  const PlanBuilderScreen({super.key, this.initialGoal});

  /// Prefilled from a chip on the home invitation, when the learner came in through one.
  final String? initialGoal;

  @override
  ConsumerState<PlanBuilderScreen> createState() => _PlanBuilderScreenState();
}

/// Which of the three rows is expanded. Exactly one is, always: a card with everything closed has
/// nothing on it, and a card with everything open is направление А with extra steps.
enum _Step { goal, language, when }

class _PlanBuilderScreenState extends ConsumerState<PlanBuilderScreen> {
  late final TextEditingController _goal = TextEditingController(text: widget.initialGoal ?? '');
  final _goalFocus = FocusNode();

  _Step _open = _Step.goal;
  late String _targetLang;
  PlanLevel _level = PlanLevel.basic;

  /// The event, as a DATE. «Сегодня» and «Завтра» are the same field with a shortcut on it — the
  /// server takes `event_date` and nothing else, so there is no third state to keep in step.
  late DateTime _eventDate = _dayOnly(DateTime.now()).add(const Duration(days: 2));

  int _minutes = 20;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final profile = ref.read(authControllerProvider).value?.profile;
    _targetLang = profile?.targetLanguage ?? 'en';
    if (_targetLang == (profile?.nativeLanguage ?? 'ru')) _targetLang = 'en';
  }

  @override
  void dispose() {
    _goal.dispose();
    _goalFocus.dispose();
    super.dispose();
  }

  static DateTime _dayOnly(DateTime d) => DateTime(d.year, d.month, d.day);

  String get _goalText => _goal.text.trim();
  bool get _ready => _goalText.length >= 3;

  /// «Сегодня» hides the minutes block entirely (кадр Б-03's note): there is no «в день» when there
  /// is one day, and a per-day budget shown on such a plan is a promise about tomorrow.
  bool get _isToday => _eventDate.difference(_dayOnly(DateTime.now())).inDays <= 0;

  int get _daysToEvent => _eventDate.difference(_dayOnly(DateTime.now())).inDays;

  void _openStep(_Step step) {
    AppHaptics.light();
    if (step != _Step.goal) _goalFocus.unfocus();
    setState(() => _open = step);
  }

  Future<void> _pickDate() async {
    AppHaptics.light();
    final today = _dayOnly(DateTime.now());
    var chosen = _eventDate.isBefore(today) ? today : _eventDate;

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
                minimumDate: today,
                // A year is more than any preparation plan the scheduler will accept, and a picker
                // that can be spun to 2043 invites a plan the server then refuses.
                maximumDate: today.add(const Duration(days: 365)),
                onDateTimeChanged: (d) => chosen = _dayOnly(d),
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

    if (result != null && mounted) setState(() => _eventDate = result);
  }

  /// «Собрать план» — the draft and the outline, in that order, and both before anything is shown.
  ///
  /// Two calls and one spinner: the draft costs nothing and the outline is the paid one, so a
  /// failure between them leaves a draft the learner never sees and never pays for again. The
  /// preview is pushed only once there is something to preview.
  Future<void> _build() async {
    if (!_ready || _busy) return;
    AppHaptics.light();
    setState(() {
      _busy = true;
      _error = null;
    });

    final api = ref.read(apiClientProvider);
    try {
      final draft = await api.createPlan(
        goalText: _goalText,
        targetLang: _targetLang,
        level: _level.wire,
        eventDate: _isoDate(_eventDate),
        minutesPerDay: _minutes,
      );
      final outlined = await api.buildPlanOutline(draft.id);
      if (!mounted) return;

      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => PlanPreviewScreen(plan: outlined)),
      );
      // Coming BACK here means the learner did not start the plan. The card keeps their answers,
      // which is the whole reason this screen is not disposed on the way out.
      if (mounted) setState(() => _busy = false);
    } catch (e) {
      if (!mounted) return;
      final l = AppLocalizations.of(context);
      setState(() {
        _busy = false;
        _error = isOffline(e) ? l.planErrorOffline : l.planErrorBuildFailed;
      });
    }
  }

  static String _isoDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _TopBar(title: l.commonBack),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.screenH,
                    AppSpacing.s8,
                    AppSpacing.screenH,
                    AppSpacing.s26,
                  ),
                  children: [
                    Text(l.planBuilderTitle, style: AppText.collectionNameScreen),
                    const SizedBox(height: AppSpacing.s8),
                    Text(
                      l.planBuilderSubtitle,
                      style: AppText.translation.copyWith(
                        fontSize: 14.5,
                        height: 1.55,
                        color: AppColors.secondary,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.s22),
                    _StepCard(
                      children: [
                        _step(
                          _Step.goal,
                          index: '01',
                          openTitle: l.planStepGoalQuestion,
                          closedValue: _goalText,
                          closedFallback: l.planStepGoalClosed,
                          child: _GoalStep(
                            controller: _goal,
                            focusNode: _goalFocus,
                            onChanged: (_) => setState(() {}),
                            onChip: (text) {
                              _goal.text = text;
                              setState(() {});
                            },
                          ),
                        ),
                        _step(
                          _Step.language,
                          index: '02',
                          openTitle: l.planStepLanguageQuestion,
                          closedValue: _languageSummary(l),
                          closedFallback: l.planStepLanguageClosed,
                          child: _LanguageStep(
                            target: _targetLang,
                            level: _level,
                            onLang: (c) => setState(() => _targetLang = c),
                            onLevel: (v) => setState(() => _level = v),
                          ),
                        ),
                        _step(
                          _Step.when,
                          index: '03',
                          openTitle: l.planStepWhenQuestion,
                          closedValue: _whenSummary(context, l),
                          closedFallback: l.planStepWhenClosed,
                          last: true,
                          child: _WhenStep(
                            eventDate: _eventDate,
                            minutes: _minutes,
                            showMinutes: !_isToday,
                            onToday: () => setState(() => _eventDate = _dayOnly(DateTime.now())),
                            onTomorrow: () => setState(
                              () => _eventDate = _dayOnly(DateTime.now()).add(const Duration(days: 1)),
                            ),
                            onPickDate: _pickDate,
                            onMinutes: (m) => setState(() => _minutes = m),
                          ),
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
                    const SizedBox(height: AppSpacing.s22),
                    PrimaryButton(
                      label: _busy ? l.planBuilderWorking : l.planBuilderSubmit,
                      minHeight: 52,
                      enabled: _ready && !_busy,
                      onPressed: _build,
                    ),
                    const SizedBox(height: 10),
                    // The ORIENTATION under the button, and only the half the device can honestly
                    // compute: how many days there are. How much MATERIAL fits in them is the
                    // server's arithmetic (`ComputedPlan`), and a second estimate of it here would
                    // be a number that disagrees with the preview one screen later.
                    Center(
                      child: Text(
                        _isToday
                            ? l.planBuilderHintToday
                            : l.planBuilderHintDays(_daysToEvent, _minutes),
                        textAlign: TextAlign.center,
                        style: AppText.translation.copyWith(
                          fontSize: 12.5,
                          color: AppColors.tertiary,
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.s26),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _languageSummary(AppLocalizations l) {
    final lang = studyLanguagesFor(_targetLang).firstWhere(
      (x) => x.code == _targetLang,
      orElse: () => studyLanguagesFor(_targetLang).first,
    );

    return '${lang.endonym} · ${_levelLabel(l, _level)}';
  }

  String _whenSummary(BuildContext context, AppLocalizations l) {
    final date = planDateLabel(context, _isoDate(_eventDate));

    return _isToday ? date : '$date · ${l.planMinutesPerDay(_minutes)}';
  }

  /// One row of the card: a header that is either the OPEN question or the CLOSED answer.
  Widget _step(
    _Step step, {
    required String index,
    required String openTitle,
    required String closedValue,
    required String closedFallback,
    required Widget child,
    bool last = false,
  }) {
    final open = _open == step;
    final l = AppLocalizations.of(context);

    return _StepRow(
      first: step == _Step.goal,
      index: index,
      open: open,
      last: last,
      title: open ? openTitle : (closedValue.isEmpty ? closedFallback : closedValue),
      answered: closedValue.isNotEmpty,
      editLabel: l.planStepEdit,
      onOpen: () => _openStep(step),
      child: child,
    );
  }
}

String _levelLabel(AppLocalizations l, PlanLevel level) => switch (level) {
  PlanLevel.zero => l.planLevelZero,
  PlanLevel.basic => l.planLevelBasic,
  PlanLevel.conversational => l.planLevelConversational,
  PlanLevel.fluent => l.planLevelFluent,
};

/// The card the three rows live in — raised paper, hairline between the rows, nothing else.
class _StepCard extends StatelessWidget {
  const _StepCard({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => PaperCard(
    radius: 18,
    padding: EdgeInsets.zero,
    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children),
  );
}

class _StepRow extends StatelessWidget {
  const _StepRow({
    required this.index,
    required this.title,
    required this.open,
    required this.answered,
    required this.first,
    required this.last,
    required this.editLabel,
    required this.onOpen,
    required this.child,
  });

  final String index, title, editLabel;
  final bool open, answered, first, last;
  final VoidCallback onOpen;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final header = Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 3),
          child: Text(
            index,
            style: AppText.translation.copyWith(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.6,
              // Brass on the answered rows, grey on the ones still to come: the plan's own mark,
              // used here as a progress signal that costs no extra element.
              color: answered || open ? AppColors.brassInk : AppColors.tertiary,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            maxLines: open ? 2 : 1,
            overflow: TextOverflow.ellipsis,
            style: AppText.collectionNameCard.copyWith(
              fontSize: open ? 21 : 19,
              height: 1.25,
              color: open || answered ? AppColors.ink : AppColors.tertiary,
            ),
          ),
        ),
        if (!open) ...[
          const SizedBox(width: AppSpacing.s8),
          answered
              ? Text(
                  editLabel,
                  style: AppText.translation.copyWith(
                    fontSize: 13.5,
                    color: AppColors.destructiveText,
                  ),
                )
              : const Icon(LucideIcons.chevronDown, size: 16, color: AppColors.tertiary),
        ],
      ],
    );

    return Container(
      decoration: BoxDecoration(
        border: first
            ? null
            : const Border(top: BorderSide(color: AppColors.dividerFaint)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(
            button: !open,
            child: InkWell(
              onTap: open ? null : onOpen,
              child: Padding(
                padding: EdgeInsets.fromLTRB(18, open ? 18 : 0, 18, 0),
                child: SizedBox(
                  height: open ? null : 60,
                  child: open
                      ? header
                      : Align(alignment: Alignment.centerLeft, child: header),
                ),
              ),
            ),
          ),
          AnimatedSize(
            // The same easing the collection card uses when it opens — one «раскрытие» in the app.
            duration: AppMotion.collectionReady,
            curve: Curves.easeOut,
            alignment: Alignment.topCenter,
            child: open
                ? Padding(
                    padding: EdgeInsets.fromLTRB(18, 14, 18, last ? 20 : 18),
                    child: child,
                  )
                : const SizedBox(width: double.infinity),
          ),
        ],
      ),
    );
  }
}

/// Шаг 1 — free text first, chips under it as EXAMPLES rather than as a menu.
class _GoalStep extends StatelessWidget {
  const _GoalStep({
    required this.controller,
    required this.focusNode,
    required this.onChanged,
    required this.onChip,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onChip;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final chips = [
      l.planGoalChipInterview,
      l.planGoalChipDoctor,
      l.planGoalChipRent,
      l.planGoalChipTrip,
      l.planGoalChipVet,
      l.planGoalChipSchool,
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: controller,
          focusNode: focusNode,
          onChanged: onChanged,
          maxLines: 3,
          minLines: 1,
          textCapitalization: TextCapitalization.sentences,
          // Terracotta caret — the frame's one coloured pixel on this screen, and the same accent
          // the single action wears. Nothing else here is allowed it.
          cursorColor: AppColors.verdictUnknown,
          style: AppText.collectionNameCard.copyWith(fontSize: 19, height: 1.35),
          decoration: InputDecoration(
            isDense: true,
            contentPadding: const EdgeInsets.only(bottom: 10),
            hintText: l.planGoalPlaceholder,
            hintStyle: AppText.collectionNameCard.copyWith(
              fontSize: 19,
              color: AppColors.plateLabel,
            ),
            enabledBorder: const UnderlineInputBorder(
              borderSide: BorderSide(color: AppColors.hairline),
            ),
            focusedBorder: const UnderlineInputBorder(
              borderSide: BorderSide(color: AppColors.ink, width: 1.5),
            ),
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final chip in chips)
              _OutlineChip(label: chip, onTap: () => onChip(chip)),
          ],
        ),
      ],
    );
  }
}

/// Шаг 2 — the studied language (en/de) and the level in four human sentences.
class _LanguageStep extends StatelessWidget {
  const _LanguageStep({
    required this.target,
    required this.level,
    required this.onLang,
    required this.onLevel,
  });

  final String target;
  final PlanLevel level;
  final ValueChanged<String> onLang;
  final ValueChanged<PlanLevel> onLevel;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final lang in studyLanguagesFor(target))
              _FilledChip(
                label: lang.endonym,
                selected: lang.code == target,
                onTap: () => onLang(lang.code),
              ),
          ],
        ),
        const SizedBox(height: 20),
        PlanLabel(l.planLevelLabel, color: AppColors.tertiary, fontSize: 11.5),
        const SizedBox(height: 8),
        for (final value in PlanLevel.values)
          _RadioRow(
            label: _levelLabel(l, value),
            selected: value == level,
            last: value == PlanLevel.values.last,
            onTap: () => onLevel(value),
          ),
      ],
    );
  }
}

/// Шаг 3 — the date, and the minutes unless the event is today.
class _WhenStep extends StatelessWidget {
  const _WhenStep({
    required this.eventDate,
    required this.minutes,
    required this.showMinutes,
    required this.onToday,
    required this.onTomorrow,
    required this.onPickDate,
    required this.onMinutes,
  });

  final DateTime eventDate;
  final int minutes;
  final bool showMinutes;
  final VoidCallback onToday, onTomorrow, onPickDate;
  final ValueChanged<int> onMinutes;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final today = DateTime.now();
    final days = eventDate.difference(DateTime(today.year, today.month, today.day)).inDays;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: _DateButton(label: l.planWhenToday, selected: days <= 0, onTap: onToday),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _DateButton(label: l.planWhenTomorrow, selected: days == 1, onTap: onTomorrow),
            ),
            const SizedBox(width: 8),
            Expanded(
              flex: 2,
              child: _DateButton(
                label: planDateLabel(context, eventDate.toIso8601String()),
                selected: days > 1,
                onTap: onPickDate,
              ),
            ),
          ],
        ),
        if (showMinutes) ...[
          const SizedBox(height: 20),
          PlanLabel(l.planMinutesLabel, color: AppColors.tertiary, fontSize: 11.5),
          const SizedBox(height: 10),
          Row(
            children: [
              for (final value in const [10, 20, 40]) ...[
                if (value != 10) const SizedBox(width: 8),
                Expanded(
                  child: _MinutesButton(
                    value: value,
                    selected: value == minutes,
                    onTap: () => onMinutes(value),
                  ),
                ),
              ],
            ],
          ),
        ],
      ],
    );
  }
}

class _DateButton extends StatelessWidget {
  const _DateButton({required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? AppColors.ink : Colors.transparent,
    clipBehavior: Clip.antiAlias,
    // `shape` alone — Material asserts when it is given both a shape and a borderRadius.
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadii.small),
      side: selected ? BorderSide.none : const BorderSide(color: AppColors.track),
    ),
    child: InkWell(
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: Container(
        constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 6),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: AppText.translation.copyWith(
            fontSize: 15,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
            color: selected ? AppColors.paper : AppColors.ink,
          ),
        ),
      ),
    ),
  );
}

class _MinutesButton extends StatelessWidget {
  const _MinutesButton({required this.value, required this.selected, required this.onTap});
  final int value;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadii.small),
      side: BorderSide(
        color: selected ? AppColors.ink : AppColors.track,
        width: selected ? 1.5 : 1,
      ),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: Container(
        constraints: const BoxConstraints(minHeight: 52),
        alignment: Alignment.center,
        child: Text(
          '$value',
          style: AppText.displayTerm.copyWith(
            fontSize: 20,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
          ),
        ),
      ),
    ),
  );
}

class _RadioRow extends StatelessWidget {
  const _RadioRow({
    required this.label,
    required this.selected,
    required this.last,
    required this.onTap,
  });
  final String label;
  final bool selected, last;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: () {
      AppHaptics.light();
      onTap();
    },
    child: Container(
      height: 52,
      decoration: last
          ? null
          : const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.dividerFaint))),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: AppText.translation.copyWith(
                fontSize: 15.5,
                fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                color: AppColors.ink,
              ),
            ),
          ),
          Container(
            width: 17,
            height: 17,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                // A thick terracotta ring rather than a filled dot — the frames' own radio, and the
                // one place the accent appears without being a button.
                color: selected ? AppColors.destructiveText : AppColors.dashed,
                width: selected ? 5 : 1,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _OutlineChip extends StatelessWidget {
  const _OutlineChip({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    borderRadius: BorderRadius.circular(AppRadii.chip),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: Container(
        constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.chip),
          border: Border.all(color: AppColors.track),
        ),
        child: Text(label, style: AppText.translation.copyWith(fontSize: 14)),
      ),
    ),
  );
}

class _FilledChip extends StatelessWidget {
  const _FilledChip({required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? AppColors.ink : Colors.transparent,
    clipBehavior: Clip.antiAlias,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadii.chip),
      side: selected ? BorderSide.none : const BorderSide(color: AppColors.track),
    ),
    child: InkWell(
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: Container(
        constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Text(
          label,
          style: AppText.translation.copyWith(
            fontSize: 14.5,
            fontWeight: selected ? FontWeight.w500 : FontWeight.w400,
            color: selected ? AppColors.paper : AppColors.ink,
          ),
        ),
      ),
    ),
  );
}

/// A back chevron and nothing else — this screen's title is its own headline, not a bar.
class _TopBar extends StatelessWidget {
  const _TopBar({required this.title});
  final String title;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: AppSpacing.minTap,
    child: Row(
      children: [
        Semantics(
          button: true,
          label: title,
          child: InkResponse(
            onTap: () => Navigator.of(context).maybePop(),
            radius: 22,
            child: const SizedBox(
              width: AppSpacing.minTap,
              height: AppSpacing.minTap,
              child: Icon(LucideIcons.chevronLeft, size: 20, color: AppColors.secondary),
            ),
          ),
        ),
      ],
    ),
  );
}
