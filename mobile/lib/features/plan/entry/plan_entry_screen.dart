import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:speech_to_text/speech_to_text.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/api_client.dart';
import '../../../data/languages.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/providers.dart';
import '../plan_format.dart';
import '../plan_providers.dart';
import '../plan_ready_notification_host.dart';
import 'entry_days_step.dart';
import 'entry_goal_step.dart';
import 'entry_language_step.dart';
import 'entry_preview_step.dart';
import 'entry_scaffold.dart';
import 'entry_state.dart';
import 'entry_tape.dart';

/// Open the entry over the tab. Resolves with the started plan, or null when the learner left.
Future<Plan?> openPlanEntry(BuildContext context) =>
    Navigator.of(context).push<Plan>(MaterialPageRoute(builder: (_) => const PlanEntryScreen()));

/// ВХОД В ПЛАН — кадры 22-1a … 22-5 (наряд PLAN-UI, §4).
///
/// Four steps under one header: the goal, the language and level, the days and the date, the
/// preview. The answers ride in [EntryState]; each step is a widget that reads it and calls back.
/// The plan is asked for when the days step is left — `POST /plans` answers 202 and the preview
/// polls the build (§4: «промт плана — асинхронный job»); «Начать» is `POST /plans/{id}/start`,
/// and day one the server writes on its own — the tab only asks whether it has.
class PlanEntryScreen extends ConsumerStatefulWidget {
  const PlanEntryScreen({super.key});

  @override
  ConsumerState<PlanEntryScreen> createState() => _PlanEntryScreenState();
}

class _PlanEntryScreenState extends ConsumerState<PlanEntryScreen> {
  EntryState _s = const EntryState();
  final _goal = TextEditingController();
  final _goalFocus = FocusNode();
  Timer? _poll;

  /// The answers the current plan was built from — a changed answer asks for a NEW plan.
  ({String goal, String lang, PlanLevel level, int days, DateTime? date})? _builtFrom;

  // ── dictation (кадр 22-1a: микрофон в поле) ────────────────────────────────────────────────
  final SpeechToText _speech = SpeechToText();
  bool _speechInitDone = false;
  bool _listening = false;
  String _voiceBase = '';

  static const _pollEvery = Duration(seconds: 2);

  @override
  void initState() {
    super.initState();
    final profile = ref.read(authControllerProvider).value?.profile;
    final lang = profile?.targetLanguage ?? 'en';
    // «Средний» is preselected off the account's level — B1 and above — and the frame's line
    // about «свободно говорю» stands under it (кадр 22-2).
    final level = _levelFor(profile?.cefrLevel);
    _s = _s.copyWith(targetLang: lang, level: level);
    _goal.addListener(() {
      if (_goal.text != _s.goal) setState(() => _s = _s.copyWith(goal: _goal.text, clearChip: _s.chip != null && _goal.text != _templateOf(_s.chip)));
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    if (_speech.isListening) _speech.stop();
    _goal.dispose();
    _goalFocus.dispose();
    super.dispose();
  }

  static PlanLevel _levelFor(String? cefr) => switch (cefr) {
    'B1' || 'B2' || 'C1' || 'C2' => PlanLevel.intermediate,
    _ => PlanLevel.beginner,
  };

  bool get _fluentNote {
    final cefr = ref.read(authControllerProvider).value?.profile?.cefrLevel;

    return _levelFor(cefr) == PlanLevel.intermediate;
  }

  String? _templateOf(EntryGoalChip? chip) =>
      chip == null ? null : EntryGoalStep.templateFor(AppLocalizations.of(context), chip);

  // ── navigation between the steps ───────────────────────────────────────────────────────────

  void _go(EntryStep step) {
    _goalFocus.unfocus();
    setState(() => _s = _s.copyWith(step: step));
    if (step == EntryStep.preview) unawaited(_ensureBuilt());
  }

  void _next() {
    switch (_s.step) {
      case EntryStep.goal:
        if (!_s.goalFilled) return;
        _go(EntryStep.language);
      case EntryStep.language:
        _go(EntryStep.days);
      case EntryStep.days:
        _go(EntryStep.preview);
      case EntryStep.preview:
        break;
    }
  }

  void _cancel() => Navigator.of(context).pop();

  // ── the build (кадры 22-4a … 22-4d) ────────────────────────────────────────────────────────

  ({String goal, String lang, PlanLevel level, int days, DateTime? date}) get _answers => (
    goal: _s.goal.trim(),
    lang: _s.targetLang,
    level: _s.level,
    days: _s.days,
    date: _s.effectiveDate,
  );

  /// Ask for a plan when the answers changed since the last one — «Изм.» that changes nothing
  /// keeps the route already on screen.
  Future<void> _ensureBuilt() async {
    final answers = _answers;
    if (_builtFrom == answers && _s.phase != EntryBuildPhase.idle && _s.phase != EntryBuildPhase.failed) return;
    _builtFrom = answers;
    await _create();
  }

  Future<void> _create() async {
    _poll?.cancel();
    _photoReads = 0;
    setState(() => _s = _s.copyWith(phase: EntryBuildPhase.building, clearPlan: true, offline: false));
    final online = ref.read(connectivityProvider).value ?? true;
    if (!online) {
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.failed, offline: true));

      return;
    }
    try {
      final build = await ref.read(apiClientProvider).createPlan(
        goalText: _s.goal.trim(),
        targetLang: _s.targetLang,
        level: _s.level,
        daysTotal: _s.days,
        eventDate: _s.effectiveDate == null ? null : PlanFormat.wireDate(_s.effectiveDate!),
      );
      if (!mounted) return;
      setState(() => _s = _s.copyWith(planId: build.id));
      _onBuild(build);
    } catch (e) {
      if (!mounted) return;
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.failed, offline: isOffline(e)));
    }
  }

  /// «Ещё раз» (22-4c): retry the same plan when the server has one, ask afresh otherwise.
  Future<void> _retry() async {
    final id = _s.planId;
    if (id == null || _s.offline) {
      _builtFrom = _answers;
      await _create();

      return;
    }
    setState(() => _s = _s.copyWith(phase: EntryBuildPhase.building, clearPlan: true, planId: id, offline: false));
    try {
      final build = await ref.read(apiClientProvider).retryPlanBuild(id);
      if (mounted) _onBuild(build);
    } catch (e) {
      if (!mounted) return;
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.failed, offline: isOffline(e)));
    }
  }

  void _onBuild(PlanBuild build) {
    _poll?.cancel();
    switch (build.status) {
      case PlanStatus.building:
        _poll = Timer(_pollEvery, () => unawaited(_tick(build.id)));
      case PlanStatus.unclear:
        setState(() => _s = _s.copyWith(phase: EntryBuildPhase.unclear));
      case PlanStatus.failed:
        setState(() => _s = _s.copyWith(phase: EntryBuildPhase.failed));
      default:
        unawaited(_loadPlan(build.id));
    }
  }

  Future<void> _tick(String id) async {
    if (!mounted || _s.planId != id) return;
    try {
      _onBuild(await ref.read(apiClientProvider).planBuild(id));
    } catch (e) {
      if (!mounted) return;
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.failed, offline: isOffline(e)));
    }
  }

  /// How many times the ready plan is re-read for its photos — see [_loadPlan].
  int _photoReads = 0;

  Future<void> _loadPlan(String id) async {
    try {
      final plan = await ref.read(apiClientProvider).plan(id);
      if (!mounted || _s.planId != id) return;
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.ready, plan: plan));
      // THE PHOTOS ARRIVE A MOMENT AFTER `ready`: the server picks them after the route is written,
      // and the plan read on the very tick the build finished has none. Re-read a few times, quietly
      // — the route is already on screen and only the pictures change.
      final missing = plan.coverImage == null ||
          plan.scenes.any((s) => s.image == null);
      if (missing && _photoReads < 4) {
        _photoReads++;
        _poll?.cancel();
        _poll = Timer(const Duration(seconds: 4), () {
          if (mounted && _s.planId == id && _s.phase == EntryBuildPhase.ready) unawaited(_loadPlan(id));
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.failed, offline: isOffline(e)));
    }
  }

  /// «Убери день свайпом» (22-4b): the server answers with the rebuilt route; the core scene is
  /// refused (409 `plan_core_scene`) and the row springs back — the frames draw nothing for it.
  Future<void> _removeScene(PlanScene scene) async {
    final id = _s.planId;
    if (id == null || _s.phase != EntryBuildPhase.ready) return;
    try {
      final plan = await ref.read(apiClientProvider).removePlanScene(id, scene.id);
      if (!mounted) return;
      AppHaptics.light();
      setState(() => _s = _s.copyWith(plan: plan));
    } catch (_) {
      AppHaptics.warning();
    }
  }

  /// «Начать» (22-4b → 22-5a): the plan goes live and the entry closes over the tab.
  Future<void> _start() async {
    final id = _s.planId;
    if (id == null || _s.phase != EntryBuildPhase.ready) return;
    setState(() => _s = _s.copyWith(phase: EntryBuildPhase.starting));
    try {
      final plan = await ref.read(planTabProvider.notifier).start(id);
      // The one notification the plan sends is «План готов» — asked for now, when the learner has
      // just said there is a day they care about (кадр 22-6).
      unawaited(ref.read(planReadyNotificationProvider).requestPermission());
      if (mounted) Navigator.of(context).pop(plan);
    } catch (_) {
      if (!mounted) return;
      AppHaptics.warning();
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.ready));
    }
  }

  // ── dictation ──────────────────────────────────────────────────────────────────────────────

  Future<void> _toggleMic() async {
    if (_listening) {
      await _speech.stop();
      if (mounted) setState(() => _listening = false);

      return;
    }
    // The GOAL is written in the learner's own language, so the recogniser listens for that one.
    final support = ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';
    _goalFocus.unfocus();
    if (!_speechInitDone) {
      _speechInitDone = true;
      try {
        final ok = await _speech.initialize(
          onStatus: (s) {
            if ((s == 'notListening' || s == 'done') && mounted) setState(() => _listening = false);
          },
          onError: (_) {
            if (mounted) setState(() => _listening = false);
          },
        );
        if (!ok) {
          _speechInitDone = false;

          return;
        }
      } catch (_) {
        _speechInitDone = false;

        return;
      }
    }
    if (!_speech.isAvailable) return;
    AppHaptics.light();
    _voiceBase = _goal.text;
    setState(() => _listening = true);
    await _speech.listen(
      onResult: (r) {
        if (!mounted) return;
        final base = _voiceBase.trimRight();
        final combined = base.isEmpty ? r.recognizedWords : '$base ${r.recognizedWords}';
        _goal.value = TextEditingValue(
          text: combined,
          selection: TextSelection.collapsed(offset: combined.length),
        );
      },
      listenOptions: SpeechListenOptions(partialResults: true, cancelOnError: true, localeId: sttLocaleFor(support)),
    );
  }

  void _chip(EntryGoalChip chip) {
    AppHaptics.light();
    final template = _templateOf(chip);
    // «Заготовка проявляется 160 мс, курсор в конце» — the text lands whole, the cursor after it.
    _goal.value = TextEditingValue(
      text: template ?? '',
      selection: TextSelection.collapsed(offset: (template ?? '').length),
    );
    setState(() => _s = _s.copyWith(goal: template ?? '', chip: chip));
    _goalFocus.requestFocus();
  }

  Future<void> _pickDate() async {
    final today = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _s.eventDate ?? today.add(const Duration(days: 7)),
      firstDate: DateTime(today.year, today.month, today.day),
      lastDate: today.add(const Duration(days: 365)),
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: AppColors.ink,
            onPrimary: AppColors.paper,
            surface: AppColors.surfaceRaised,
            onSurface: AppColors.ink,
          ),
        ),
        child: child!,
      ),
    );
    if (picked == null || !mounted) return;
    final date = DateTime(picked.year, picked.month, picked.day);
    // «Чип дней переезжает с 5 на 3» — the date that does not fit the chosen days moves the chip.
    setState(() => _s = _s.copyWith(eventDate: date, days: _fits(_s.requestedDays, date: date)));
  }

  /// The days the date leaves of [chosen] — [chosen] itself without a date or with room enough.
  int _fits(int chosen, {DateTime? date}) {
    final event = date ?? _s.effectiveDate;
    if (event == null) return chosen;
    final left = PlanFormat.daysUntil(event, DateTime.now());

    return PlanFormat.shortenedDays(chosen: chosen, daysLeft: left) ?? chosen;
  }

  // ── build ──────────────────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final languageName = languageNameFor(_s.targetLang, locale);
    final levelName = switch (_s.level) {
      PlanLevel.beginner => l.planEntryLevelBeginner,
      PlanLevel.intermediate => l.planEntryLevelIntermediate,
    };

    final tape = <EntryTapeRow>[
      if (_s.step.index > EntryStep.goal.index)
        EntryTapeRow(label: l.planEntryTapeGoal, value: _s.goal.trim(), onEdit: () => _go(EntryStep.goal)),
      if (_s.step.index > EntryStep.language.index)
        EntryTapeRow(
          label: l.planEntryTapeLanguage,
          value: l.planEntryTapeLanguageValue(languageName, levelName),
          onEdit: () => _go(EntryStep.language),
        ),
      if (_s.step.index > EntryStep.days.index)
        EntryTapeRow(label: l.planEntryTapeDays, value: _daysValue(l, locale), onEdit: () => _go(EntryStep.days)),
    ];

    final Widget body = switch (_s.step) {
      EntryStep.goal => EntryGoalStep(
        controller: _goal,
        focus: _goalFocus,
        chip: _s.chip,
        listening: _listening,
        onChip: _chip,
        onMic: _toggleMic,
      ),
      EntryStep.language => EntryLanguageStep(
        tape: tape,
        languages: studyLanguagesFor(_s.targetLang),
        targetLang: _s.targetLang,
        level: _s.level,
        fluentNote: _fluentNote,
        onLanguage: (code) => setState(() => _s = _s.copyWith(targetLang: code)),
        onLevel: (level) => setState(() => _s = _s.copyWith(level: level)),
      ),
      EntryStep.days => EntryDaysStep(
        tape: tape,
        days: _s.days,
        requestedDays: _s.requestedDays,
        dateEnabled: _s.dateEnabled,
        eventDate: _s.eventDate,
        onDays: (n) => setState(() => _s = _s.copyWith(days: _fits(n), requestedDays: n)),
        onDateEnabled: (on) => setState(() => _s = _s.copyWith(dateEnabled: on, days: on ? _fits(_s.requestedDays) : _s.requestedDays)),
        onPickDate: _pickDate,
      ),
      EntryStep.preview => EntryPreviewStep(
        tape: tape,
        state: _s,
        languageName: languageName,
        onRetry: _retry,
        onEditGoal: () => _go(EntryStep.goal),
        onRemoveScene: _removeScene,
      ),
    };

    final preview = _s.step == EntryStep.preview;
    final canStart = preview && _s.phase == EntryBuildPhase.ready;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) _cancel();
        },
        child: EntryScaffold(
          step: _s.step,
          onCancel: _cancel,
          onNext: _next,
          showNext: !preview,
          nextEnabled: _s.step != EntryStep.goal || _s.goalFilled,
          dock: preview ? _StartDock(enabled: canStart, onStart: _start) : null,
          child: body,
        ),
      ),
    );
  }

  /// «5 · приём 15 сентября» — the event's word is the server's once the plan is built; before
  /// that the days and the date alone.
  String _daysValue(AppLocalizations l, String locale) {
    final date = _s.effectiveDate;
    final plan = _s.plan;
    if (date == null) return '${_s.days}';
    final formatted = PlanFormat.date(date, locale);
    final event = (plan?.eventNative ?? '').trim();
    if (event.isEmpty) return l.planEntryTapeDaysValueDated(_s.days, formatted);

    return l.planEntryTapeDaysValue(_s.days, event.toLowerCase(), formatted);
  }
}

/// The dock over the safe area on the preview: the hint line and «Начать» 54 / radius 18.
class _StartDock extends StatelessWidget {
  const _StartDock({required this.enabled, required this.onStart});

  final bool enabled;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.s26, 8, AppSpacing.s26, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (enabled) ...[
            Text(
              l.planEntryPreviewHint,
              textAlign: TextAlign.center,
              style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary),
            ),
            const SizedBox(height: 12),
          ],
          Semantics(
            button: true,
            enabled: enabled,
            label: l.planEntryPreviewCta,
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              height: 54,
              decoration: BoxDecoration(
                color: enabled ? AppColors.ink : AppColors.ink.withValues(alpha: .10),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Material(
                type: MaterialType.transparency,
                borderRadius: BorderRadius.circular(18),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: enabled
                      ? () {
                          AppHaptics.light();
                          onStart();
                        }
                      : null,
                  child: Center(
                    child: Text(
                      l.planEntryPreviewCta,
                      style: TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 15.5,
                        fontWeight: FontWeight.w700,
                        color: enabled ? AppColors.paper : AppColors.tertiary,
                      ),
                    ),
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
