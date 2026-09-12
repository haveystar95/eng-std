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
import 'entry_date_step.dart';
import 'entry_days_step.dart';
import 'entry_goal_step.dart';
import 'entry_language_step.dart';
import 'entry_preview_step.dart';
import 'entry_scaffold.dart';
import 'entry_state.dart';

/// Open the entry over the tab. Resolves with the started plan, or null when the learner left.
Future<Plan?> openPlanEntry(BuildContext context) =>
    Navigator.of(context).push<Plan>(MaterialPageRoute(builder: (_) => const PlanEntryScreen()));

/// ВХОД В ПЛАН — кадры 22-1 … 22-4d (наряд PLAN-UI-2).
///
/// ЧЕТЫРЕ ВОПРОСА и результат: цель (22-1), язык с уровнем (22-2), длина (22-3a), дата (22-3b) —
/// и превью (22-4a…22-4d), которое шагом не считается. Ответы едут в [EntryState]; каждый шаг —
/// виджет, который его читает и звонит наружу.
///
/// План заказывается, когда уходят с шага ДАТЫ — `POST /plans` отвечает 202, превью опрашивает
/// сборку; «Начать» — это `POST /plans/{id}/start`, а день 1 сервер пишет сам, и таб только
/// спрашивает, написал ли.
///
/// Шапки с «Отмена / Новый план / Далее» здесь больше нет: наверху стрелка назад и четыре точки,
/// единственное действие шага — кнопка внизу.
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
  EntryMicState _mic = EntryMicState.idle;
  int _micSeconds = 0;
  Timer? _micTick;
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
      if (_goal.text != _s.goal) setState(() => _s = _s.copyWith(goal: _goal.text));
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    _micTick?.cancel();
    if (_speech.isListening) _speech.stop();
    _goal.dispose();
    _goalFocus.dispose();
    super.dispose();
  }

  static PlanLevel _levelFor(String? cefr) => switch (cefr) {
    'B1' || 'B2' || 'C1' || 'C2' => PlanLevel.intermediate,
    _ => PlanLevel.beginner,
  };

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
        // Уходя с длины, подставляем ближнюю дату: шаг даты открывается с предвыбранным ответом,
        // а не с тремя пустыми строками.
        setState(() => _s = _s.copyWith(eventDate: _s.eventDate ?? _suggestedDate()));
        _go(EntryStep.date);
      case EntryStep.date:
        _go(EntryStep.preview);
      case EntryStep.preview:
        break;
    }
  }

  /// СТРЕЛКА НАЗАД: на первом шаге закрывает вход, дальше возвращает на шаг назад. Из превью —
  /// на дату: план уже заказан, и человек возвращается к последнему своему ответу, а не к цели.
  void _back() {
    switch (_s.step) {
      case EntryStep.goal:
        Navigator.of(context).pop();
      case EntryStep.language:
        _go(EntryStep.goal);
      case EntryStep.days:
        _go(EntryStep.language);
      case EntryStep.date:
        _go(EntryStep.days);
      case EntryStep.preview:
        _go(EntryStep.date);
    }
  }

  /// Ближняя дата — через столько дней, сколько выбрано: разговор ровно в конце плана.
  DateTime _suggestedDate() {
    final today = DateTime.now();

    return DateTime(today.year, today.month, today.day).add(Duration(days: _s.days));
  }

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

  /// «Начать» (22-4b → 22-5a): the plan goes live and the entry closes over the tab.
  Future<void> _start() async {
    final id = _s.planId;
    if (id == null || _s.phase != EntryBuildPhase.ready) return;
    setState(() => _s = _s.copyWith(phase: EntryBuildPhase.starting));
    try {
      final plan = await ref.read(planTabProvider.notifier).start(id);
      // НИКАКИХ СИСТЕМНЫХ ОКОН НА «НАЧАТЬ». Раньше здесь просилось разрешение на уведомления, и
      // системный алерт всплывал поверх таба ровно в тот момент, которого в кадрах 22-5a/22-6 нет:
      // человек нажал «Начать», чтобы увидеть свой план, а увидел вопрос операционной системы.
      // Разрешение просит явное действие — выключатель «Напоминания» в профиле, и только он.
      if (mounted) Navigator.of(context).pop(plan);
    } catch (_) {
      if (!mounted) return;
      AppHaptics.warning();
      setState(() => _s = _s.copyWith(phase: EntryBuildPhase.ready));
    }
  }

  // ── dictation ──────────────────────────────────────────────────────────────────────────────

  /// МИКРОФОН В ЧЕТЫРЕ СОСТОЯНИЯ (кадр 22-1): покой → слушаю → распознаю → текст в поле.
  ///
  /// «Распознаю» — не украшение: движок отдаёт финальный результат уже после того, как перестал
  /// слушать, и без этого состояния поле секунду стояло пустым, будто запись пропала.
  Future<void> _toggleMic() async {
    if (_mic == EntryMicState.listening) {
      await _speech.stop();
      if (mounted) _setMic(EntryMicState.recognising);

      return;
    }
    if (_mic == EntryMicState.recognising) return;

    // Цель человек пишет на СВОЁМ языке, поэтому слушаем его, а не изучаемый.
    final support = ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';
    _goalFocus.unfocus();
    if (!_speechInitDone) {
      _speechInitDone = true;
      try {
        final ok = await _speech.initialize(
          onStatus: (status) {
            if (!mounted) return;
            if (status == 'notListening' && _mic == EntryMicState.listening) {
              _setMic(EntryMicState.recognising);
            } else if (status == 'done') {
              _setMic(_goal.text.trim().isEmpty ? EntryMicState.idle : EntryMicState.done);
            }
          },
          onError: (_) {
            if (mounted) _setMic(EntryMicState.idle);
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
    _setMic(EntryMicState.listening);
    await _speech.listen(
      onResult: (r) {
        if (!mounted) return;
        final base = _voiceBase.trimRight();
        final combined = base.isEmpty ? r.recognizedWords : '$base ${r.recognizedWords}';
        _goal.value = TextEditingValue(
          text: combined,
          selection: TextSelection.collapsed(offset: combined.length),
        );
        if (r.finalResult) _setMic(EntryMicState.done);
      },
      listenOptions: SpeechListenOptions(
        partialResults: true,
        cancelOnError: true,
        localeId: sttLocaleFor(support),
      ),
    );
  }

  /// Смена состояния микрофона вместе с таймером записи — «0:07 · говори, я слушаю».
  void _setMic(EntryMicState next) {
    _micTick?.cancel();
    if (next == EntryMicState.listening) {
      _micSeconds = 0;
      _micTick = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) setState(() => _micSeconds++);
      });
    }
    setState(() => _mic = next);
  }

  /// Тап по истории «так пишут другие» — текст встаёт в поле, курсор в конце (22-1).
  void _story(String text) {
    AppHaptics.light();
    _goal.value = TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
    _setMic(EntryMicState.idle);
    setState(() => _s = _s.copyWith(goal: text));
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
    final languageValue = l.planEntryTapeLanguageValue(languageName, levelName);
    final goal = _s.goal.trim();

    final Widget body = switch (_s.step) {
      EntryStep.goal => EntryGoalStep(
        controller: _goal,
        focus: _goalFocus,
        mic: _mic,
        micSeconds: _micSeconds,
        onMic: _toggleMic,
        onStory: _story,
      ),
      EntryStep.language => EntryLanguageStep(
        goal: goal,
        languages: planEntryLanguagesFor(_s.targetLang),
        targetLang: _s.targetLang,
        level: _s.level,
        onLanguage: (code) => setState(() => _s = _s.copyWith(targetLang: code)),
        onLevel: (level) => setState(() => _s = _s.copyWith(level: level)),
        onEditGoal: () => _go(EntryStep.goal),
      ),
      EntryStep.days => EntryDaysStep(
        goal: goal,
        languageValue: languageValue,
        days: _s.days,
        onDays: (n) => setState(() => _s = _s.copyWith(days: _fits(n), requestedDays: n)),
        onEditGoal: () => _go(EntryStep.goal),
        onEditLanguage: () => _go(EntryStep.language),
      ),
      EntryStep.date => EntryDateStep(
        goal: goal,
        languageValue: languageValue,
        days: _s.days,
        eventDate: _s.eventDate,
        suggested: _suggestedDate(),
        today: DateTime.now(),
        onPickSuggested: () => setState(
          () => _s = _s.copyWith(
            eventDate: _s.eventDate ?? _suggestedDate(),
            days: _fits(_s.requestedDays, date: _s.eventDate ?? _suggestedDate()),
          ),
        ),
        // «Дата пока неизвестна» — план идёт подряд, и длина возвращается к выбранной.
        onPickUnknown: () =>
            setState(() => _s = _s.copyWith(clearDate: true, days: _s.requestedDays)),
        onPickCustom: _pickDate,
        onEditGoal: () => _go(EntryStep.goal),
        onEditLanguage: () => _go(EntryStep.language),
        onEditDays: () => _go(EntryStep.days),
      ),
      EntryStep.preview => EntryPreviewStep(
        state: _s,
        summary: _summary(l, locale, languageName, levelName),
        onRetry: _retry,
        onEditGoal: () => _go(EntryStep.goal),
      ),
    };

    // Кнопка шага называет РЕЗУЛЬТАТ: с даты уходят «Собрать план», из превью — «Начать».
    final (String dockLabel, bool dockEnabled, VoidCallback dockTap) = switch (_s.step) {
      EntryStep.goal => (l.planEntryNext, _s.goalFilled, _next),
      EntryStep.language || EntryStep.days => (l.planEntryNext, true, _next),
      EntryStep.date => (l.planEntryDateCta, true, _next),
      EntryStep.preview => (
        l.planEntryPreviewCta,
        _s.phase == EntryBuildPhase.ready,
        _start,
      ),
    };

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) _back();
        },
        child: EntryScaffold(
          step: _s.step,
          onBack: _back,
          dock: EntryDock(label: dockLabel, enabled: dockEnabled, onTap: dockTap),
          child: body,
        ),
      ),
    );
  }

  /// «7 дней · английский · средний · приём 17 сентября» — одна строка под заголовком превью.
  ///
  /// Слово события — сервера («приём»), и до готовности плана его нет: тогда строка называет
  /// только дату. Ничего про событие клиент не склоняет.
  String _summary(AppLocalizations l, String locale, String languageName, String levelName) {
    final parts = <String>[
      l.planDaysCount(_s.days),
      languageName.toLowerCase(),
      levelName.toLowerCase(),
    ];
    final date = _s.effectiveDate;
    if (date != null) {
      final when = PlanFormat.date(date, locale);
      final event = (_s.plan?.eventNative ?? '').trim();
      parts.add(event.isEmpty ? when : '${event.toLowerCase()} $when');
    }

    return parts.join(' · ');
  }

}
