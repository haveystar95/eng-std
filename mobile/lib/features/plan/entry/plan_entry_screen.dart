import 'dart:async';

import 'package:flutter/foundation.dart' show listEquals;

import 'package:flutter/cupertino.dart' show CupertinoDatePicker, CupertinoDatePickerMode;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:speech_to_text/speech_to_text.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/languages.dart' show languageByCode, sttLocaleFor, studyLanguagesFor;
import '../../../data/plan_models.dart';
import '../../../data/providers.dart';
import '../../profile/profile_screen.dart';
import '../plan_ui.dart';
import 'entry_listen_screen.dart';
import 'entry_ui.dart';
import 'plan_assembly_screen.dart';

/// ВХОД В ПЛАН — серия «Вход v4», кадры V4·01…04б.
///
/// One question per screen, and the answers already given stay visible as a ribbon above the
/// current one. That is the whole difference from направление Б (the three-row card this replaces):
/// a card asks three questions at once and makes the goal one field of a form, and the goal is the
/// only thing the plan is actually built from.
///
/// ## Four steps, and the third one may not exist
///
/// Goal → language and level → LISTENING (optional) → date and minutes. The listening step is
/// offered only when the server managed to write three lines for it; a vendor outage, an empty
/// answer or a slow one all mean the same thing here — the step is not offered, and the flow goes
/// straight to the date with nothing said about it. The dots stay at FOUR either way: the road did
/// not get shorter because one stop was closed, and a header that lost a dot halfway would say it
/// did.
///
/// ## Nothing is created until «Собрать план»
///
/// The whole entry is local state. There is no draft on the server to abandon, so «назад» costs
/// nothing and the answers survive every step of walking back and forth — which is what makes
/// «Изм.» in the ribbon honest.
class PlanEntryScreen extends ConsumerStatefulWidget {
  const PlanEntryScreen({super.key, this.initialGoal});

  /// Prefilled from a chip on the home invitation, when the learner came in through one.
  final String? initialGoal;

  @override
  ConsumerState<PlanEntryScreen> createState() => _PlanEntryScreenState();
}

enum _Step { goal, language, listen, when }

class _PlanEntryScreenState extends ConsumerState<PlanEntryScreen> {
  late final TextEditingController _goal = TextEditingController(text: widget.initialGoal ?? '');
  final _goalFocus = FocusNode();

  _Step _step = _Step.goal;

  late String _targetLang;
  PlanLevel _level = PlanLevel.basic;

  /// NULL is «Без даты» (кадр V4·04б) and it is a real answer, not a missing one.
  DateTime? _eventDate = _dayOnly(DateTime.now()).add(const Duration(days: 2));
  int _minutes = 20;

  /// The warm-up, in flight or finished. Started when the goal is left, so the learner's time on
  /// the level cards is the model's time to write three lines.
  Future<ListenWarmup>? _warmup;
  List<ListenLine> _warmupLines = const [];

  /// «Дописать за тебя» / «Можно добавить» — the learner's own goal carried further, from the model.
  ///
  /// STATIC SUGGESTIONS WERE THE BUG. Two ready sentences about a doctor sat under a goal about an
  /// IT interview and read as a broken screen: an example of a goal is a fair thing to show when
  /// there is no goal yet, but a CONTINUATION of somebody's sentence has to be a continuation of
  /// THAT sentence (решение владельца 03.09).
  List<String> _continuations = const [];

  /// Answers already paid for, by the exact goal text.
  ///
  /// The call fires on a typing PAUSE, so without this a learner who edits a word and undoes it
  /// buys the same two sentences twice. Bounded by how many distinct goals one person types on one
  /// screen, which is a handful.
  final Map<String, List<String>> _continuationCache = {};

  /// The pause. Long enough that typing a sentence is one call and not five.
  Timer? _continuationTimer;

  /// The goal a request is in flight for — so a pause inside the same text does not start a second.
  String? _continuationsInFlight;

  /// What the learner tapped, or NULL — the step was skipped or never offered. Two different facts
  /// on the ribbon («шаг пропущен» + «Пройти» vs the verdict + «Изм.»), and two different facts to
  /// the server.
  List<ListenAnswer>? _listened;

  /// The learner chose «Пропустить» rather than never being offered the step. Only this one gets
  /// the «Пройти» row: an offer that was never made has nothing to go back to.
  bool _listenOffered = false;

  // Voice fill for the goal field, exactly as «Собрать коллекцию» does it (кадр 6c).
  final SpeechToText _speech = SpeechToText();
  bool _speechInitDone = false;
  bool _listening = false;
  String _voiceBase = '';

  @override
  void initState() {
    super.initState();
    final profile = ref.read(authControllerProvider).value?.profile;
    _targetLang = profile?.targetLanguage ?? 'en';
    if (_targetLang == (profile?.nativeLanguage ?? 'ru')) _targetLang = 'en';
    _goal.addListener(_onGoalChanged);
    if (_goalText.length >= _serverFloor) _scheduleContinuations();
  }

  /// Every keystroke: redraw, and restart the pause after which the continuations are asked for.
  void _onGoalChanged() {
    setState(() {});
    _scheduleContinuations();
  }

  /// Ask for continuations after the learner stops typing — and only then.
  ///
  /// Bounded on purpose, because every fire is a paid call:
  ///   only while the GOAL step is open — nothing else shows them;
  ///   only past the server's floor — a two-letter goal has nothing to continue;
  ///   never for a goal that is already long enough to lose the block (кадр V4·01г);
  ///   never twice for the same text — the cache answers, and an in-flight request is not doubled.
  void _scheduleContinuations() {
    _continuationTimer?.cancel();
    if (_step != _Step.goal) return;

    final goal = _goalText;
    if (goal.length < _serverFloor || _goalDetailed) return;

    final cached = _continuationCache[goal];
    if (cached != null) {
      if (!listEquals(cached, _continuations)) setState(() => _continuations = cached);

      return;
    }

    _continuationTimer = Timer(_continuationPause, () => unawaited(_askContinuations(goal)));
  }

  static const _continuationPause = Duration(milliseconds: 900);

  Future<void> _askContinuations(String goal) async {
    if (_continuationsInFlight == goal) return;
    _continuationsInFlight = goal;

    // NO target language: on this step it has not been chosen, and its absence is what asks the
    // server for the continuations alone.
    final answer = await ref.read(apiClientProvider).listenWarmup(
      goalText: goal,
      level: _level.wire,
    );
    _continuationsInFlight = null;
    if (!mounted) return;

    _continuationCache[goal] = answer.continuations;
    // The learner may have typed on while we were asking. Their CURRENT text owns the screen.
    if (_goalText != goal) return;
    setState(() => _continuations = answer.continuations);
  }


  @override
  void dispose() {
    _continuationTimer?.cancel();
    if (_speech.isListening) _speech.stop();
    _goal.dispose();
    _goalFocus.dispose();
    super.dispose();
  }

  static DateTime _dayOnly(DateTime d) => DateTime(d.year, d.month, d.day);

  String get _goalText => _goal.text.trim();

  /// The server's own floor. Below it `POST /plans` answers 422, so a button offered here would
  /// spend a tap on an error (the live run of направление Б found this the fastest way possible:
  /// its own «Врач» chip was four characters).
  static const _serverFloor = 5;

  /// «ХВАТИТ ДЛЯ ПЛАНА» — the client's own, higher bar, and the reason кадр V4·01в exists.
  ///
  /// Not a validation rule: a goal of five characters is legal for the server and useless for the
  /// model, and the screen's job is to say what is missing rather than to accept a plan that will
  /// come back thin. «К врачу» is seven characters and is exactly the case the frame draws.
  static const _enoughChars = 24;

  /// Past this the field has more to say than the examples do, so they leave (кадр V4·01г).
  static const _detailedChars = 150;

  bool get _goalEnough => _goalText.length >= _enoughChars;
  bool get _goalTooShort => _goalText.isNotEmpty && _goalText.length < _enoughChars;
  bool get _goalDetailed => _goalText.length >= _detailedChars;

  /// Every step the flow HAS, including one that may not be offered. Four dots, always.
  static const _stepCount = 4;

  int get _stepNumber => switch (_step) {
    _Step.goal => 1,
    _Step.language => 2,
    _Step.listen => 3,
    _Step.when => 4,
  };

  // ── the warm-up ─────────────────────────────────────────────────────────────────────────────

  /// Ask for three lines, in the background, as soon as the goal is answered.
  ///
  /// The learner then spends fifteen seconds on the language and the level, which is the model's
  /// time to write them. Re-fired when the language or the level changes, because both are inputs:
  /// lines for «понимаю простое» are not the lines for «свободно».
  void _startWarmup() {
    if (_goalText.length < _serverFloor) return;
    final api = ref.read(apiClientProvider);
    _warmupLines = const [];
    _warmup = api.listenWarmup(
      goalText: _goalText,
      targetLang: _targetLang,
      level: _level.wire,
    );
  }

  /// Whether to offer the step at all — and the ONE place «упал или пусто» is decided.
  ///
  /// Waits for the request only if it is still in flight, and not for long: the step is optional,
  /// and a person who has just answered «сколько минут» is not waiting for an offer they never
  /// asked for. A timeout resolves the same way a failure does — the step is not offered, silently.
  Future<bool> _warmupReady() async {
    if (_warmupLines.isNotEmpty) return true;
    final pending = _warmup;
    if (pending == null) return false;

    try {
      final answer = await pending.timeout(const Duration(seconds: 12));
      _warmupLines = answer.lines;
      // The full call answers with continuations too, and they are fresher than whatever the goal
      // step asked for. Kept, so «Изм.» back to the goal does not go and buy them again.
      if (answer.continuations.isNotEmpty) {
        _continuationCache[_goalText] = answer.continuations;
        _continuations = answer.continuations;
      }
    } catch (_) {
      _warmupLines = const [];
    }

    return _warmupLines.isNotEmpty;
  }

  // ── moving between steps ────────────────────────────────────────────────────────────────────

  void _go(_Step step) {
    AppHaptics.light();
    _goalFocus.unfocus();
    setState(() => _step = step);
    // Coming BACK to the goal through «Изм.»: the block is drawn from what was already paid for,
    // or asked for again after a pause if the text has changed since.
    if (step == _Step.goal) _scheduleContinuations();
  }

  Future<void> _fromGoal() async {
    _startWarmup();
    _go(_Step.language);
  }

  /// Language → the listening offer, or straight to the date when there is nothing to offer.
  bool _movingOn = false;

  Future<void> _fromLanguage() async {
    if (_movingOn) return;
    setState(() => _movingOn = true);
    final ready = await _warmupReady();
    if (!mounted) return;
    setState(() => _movingOn = false);
    _go(ready ? _Step.listen : _Step.when);
  }

  /// «Послушать» — the minute itself, as a sheet coming up from the bottom.
  Future<void> _openListen() async {
    AppHaptics.light();
    final answers = await Navigator.of(context).push<List<ListenAnswer>>(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 300),
        reverseTransitionDuration: const Duration(milliseconds: 240),
        pageBuilder: (_, _, _) =>
            EntryListenScreen(lines: _warmupLines, targetLang: _targetLang),
        transitionsBuilder: (_, animation, _, child) => SlideTransition(
          position: Tween(begin: const Offset(0, 1), end: Offset.zero)
              .chain(CurveTween(curve: Curves.easeOutCubic))
              .animate(animation),
          child: child,
        ),
      ),
    );
    if (!mounted) return;

    setState(() {
      _listenOffered = true;
      // An empty list is «вышел, ничего не сказав» — the same fact as skipping.
      _listened = (answers == null || answers.isEmpty) ? null : answers;
    });
    _go(_Step.when);
  }

  void _skipListen() {
    setState(() {
      _listenOffered = true;
      _listened = null;
    });
    _go(_Step.when);
  }

  // ── the build ───────────────────────────────────────────────────────────────────────────────

  /// «Собрать план» — and from here the assembly screen owns the flow (кадры V4·05…06б).
  void _build() {
    AppHaptics.light();
    _goalFocus.unfocus();
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PlanAssemblyScreen(
          goalText: _goalText,
          targetLang: _targetLang,
          level: _level,
          eventDate: _eventDate == null ? null : _isoDate(_eventDate!),
          minutesPerDay: _minutes,
          listened: _listened ?? const [],
        ),
      ),
    );
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
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                EntryHeader(
                  kicker: _step == _Step.listen ? l.planListenKicker : l.planEntryKicker,
                  step: _stepNumber,
                  steps: _stepCount,
                  onBack: _back,
                ),
                Expanded(
                  // «Между шагами — сдвиг на 16 pt со затуханием, 260 мс, ease-out» (записка).
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 260),
                    switchInCurve: Curves.easeOut,
                    switchOutCurve: Curves.easeOut,
                    transitionBuilder: (child, animation) => FadeTransition(
                      opacity: animation,
                      child: SlideTransition(
                        position: Tween(begin: const Offset(0, 0.045), end: Offset.zero)
                            .animate(animation),
                        child: child,
                      ),
                    ),
                    child: KeyedSubtree(key: ValueKey(_step), child: _body(l)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _back() {
    switch (_step) {
      case _Step.goal:
        Navigator.of(context).maybePop();
      case _Step.language:
        _go(_Step.goal);
      case _Step.listen:
        _go(_Step.language);
      case _Step.when:
        _go(_listenOffered && _warmupLines.isNotEmpty ? _Step.listen : _Step.language);
    }
  }

  Widget _body(AppLocalizations l) => switch (_step) {
    _Step.goal => _goalStep(l),
    _Step.language => _languageStep(l),
    _Step.listen => _listenStep(l),
    _Step.when => _whenStep(l),
  };

  // ── the ribbon ──────────────────────────────────────────────────────────────────────────────

  List<EntryRibbonRow> _ribbon(AppLocalizations l) {
    final rows = <EntryRibbonRow>[];
    if (_step == _Step.goal) return rows;

    rows.add(EntryRibbonRow(
      text: _goalText,
      action: l.planStepEdit,
      onTap: () => _go(_Step.goal),
    ));

    if (_step == _Step.language) return rows;

    rows.add(EntryRibbonRow(
      text: l.planEntryRibbonLangLevel(_languageName, _levelLabel(l, _level)),
      action: l.planStepEdit,
      onTap: () => _go(_Step.language),
    ));

    if (_step == _Step.listen) return rows;

    // THE LISTENING ROW EXISTS ONLY IF THE STEP DID. An offer that was never made has nothing to
    // say and nothing to go back to, so a plan built without the warm-up shows two rows, not three.
    if (_listenOffered) {
      final answers = _listened;
      if (answers == null) {
        rows.add(EntryRibbonRow(
          text: l.planEntryRibbonListenSkipped,
          action: l.planEntryPass,
          muted: true,
          onTap: _openListen,
        ));
      } else {
        rows.add(EntryRibbonRow(
          text: ListenEmphasis.of(answers) == ListenEmphasis.speaking
              ? l.planEntryRibbonListenSpeaking
              : l.planEntryRibbonListenUnderstanding,
          action: l.planStepEdit,
          onTap: _openListen,
        ));
      }
    }

    return rows;
  }

  String get _languageName {
    final options = studyLanguagesFor(_targetLang);

    return options
        .firstWhere((x) => x.code == _targetLang, orElse: () => options.first)
        .endonym;
  }

  // ── кадры V4·01…01г ─────────────────────────────────────────────────────────────────────────

  Widget _goalStep(AppLocalizations l) {
    final suggestions = _goalSuggestions(l);

    return ListView(
      padding: const EdgeInsets.only(top: 30, bottom: 32),
      children: [
        Text(
          l.planEntryGoalTitle,
          style: AppText.collectionNameScreen.copyWith(
            fontSize: _goalDetailed ? 27 : 33,
            height: _goalDetailed ? 1.2 : 1.16,
          ),
        ),
        // On a long goal the subtitle leaves with the examples: the field has already said more
        // than the instruction could.
        if (!_goalDetailed) ...[
          const SizedBox(height: 12),
          Text(
            _goalText.isEmpty ? l.planEntryGoalSubtitleLong : l.planEntryGoalSubtitle,
            style: AppText.translation.copyWith(
              fontSize: 15,
              height: 1.6,
              color: AppColors.secondary,
            ),
          ),
        ],
        SizedBox(height: _goalDetailed ? 18 : 22),
        _goalField(l),
        if (suggestions != null) ...[
          const SizedBox(height: 24),
          EntryOverline(suggestions.title),
          const SizedBox(height: 13),
          for (final s in suggestions.items) ...[
            EntrySuggestion(
              text: s,
              brass: suggestions.brass,
              onTap: () => _applySuggestion(s, append: suggestions.append),
            ),
            if (s != suggestions.items.last) const SizedBox(height: 9),
          ],
        ],
        const SizedBox(height: 24),
        EntryCta(
          label: l.planEntryNext,
          enabled: _goalEnough,
          onPressed: _fromGoal,
        ),
        const SizedBox(height: 32),
      ],
    );
  }

  Widget _goalField(AppLocalizations l) {
    final border = _goalTooShort
        ? AppColors.destructiveText.withValues(alpha: 0.55)
        : (_goalText.isEmpty
              ? AppColors.ink.withValues(alpha: 0.14)
              : AppColors.brassInk.withValues(alpha: 0.5));

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border, width: _goalText.isEmpty ? 1 : 1.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _goal,
            focusNode: _goalFocus,
            // FOUR to SIX lines, then it scrolls inside itself (кадр V4·01г) — the screen must not
            // start scrolling before the field has used the room it was promised.
            minLines: 4,
            maxLines: 6,
            textCapitalization: TextCapitalization.sentences,
            keyboardType: TextInputType.multiline,
            // The one coloured pixel of this screen besides the button.
            cursorColor: AppColors.destructiveText,
            style: AppText.collectionNameCard.copyWith(
              fontSize: _goalDetailed ? 18 : 19,
              height: 1.5,
            ),
            decoration: InputDecoration(
              isDense: true,
              contentPadding: EdgeInsets.zero,
              border: InputBorder.none,
              hintText: l.planEntryGoalPlaceholder,
              hintMaxLines: 4,
              hintStyle: AppText.collectionNameCard.copyWith(
                fontSize: 19,
                height: 1.5,
                color: AppColors.plateLabel,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(child: _goalCounter(l)),
              const SizedBox(width: 12),
              _MicButton(
                listening: _listening,
                label: l.planEntryDictate,
                onTap: _listening ? _stopListening : _startListening,
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// The line under the field: a counter, then a promise, then a hint about what is missing.
  ///
  /// «Ошибка не красное сообщение и не блокировка: рамка поля темнеет до терракоты, подсказка
  /// говорит, чего не хватает» (кадр V4·01в). The word «ошибка» never appears, and neither does red.
  Widget _goalCounter(AppLocalizations l) {
    if (_goalTooShort) {
      return Text(
        l.planEntryTooShort,
        style: AppText.translation.copyWith(
          fontSize: 12.5,
          height: 1.45,
          color: AppColors.destructiveText,
        ),
      );
    }

    final enough = _goalEnough;

    return Text(
      _goalDetailed
          ? l.planEntryDetailed
          : (enough ? l.planEntryEnough : l.planEntryLinesHint),
      style: AppText.translation.copyWith(
        fontSize: 11,
        letterSpacing: 0.35,
        color: enough ? AppColors.brassInk : AppColors.planInactive,
      ),
    );
  }

  ({String title, List<String> items, bool append, bool brass})? _goalSuggestions(
    AppLocalizations l,
  ) {
    // A detailed goal needs no examples — «они уже не нужны» (кадр V4·01г).
    if (_goalDetailed) return null;

    // EMPTY FIELD: static EXAMPLES, and they stay static honestly. These are examples of what a
    // goal looks like, not continuations of one — there is nothing yet to continue, and three ready
    // sentences are the fastest way to show what a good answer is.
    if (_goalText.isEmpty) {
      return (
        title: l.planEntryExamplesTitle,
        items: [l.planEntryExample1, l.planEntryExample2, l.planEntryExample3],
        append: false,
        brass: false,
      );
    }

    // ANYTHING TYPED: the block is the model's continuations of THAT sentence, or it is not shown.
    // The same silent contract the listening step has — a block that could not be written does not
    // apologise, it is absent.
    if (_continuations.isEmpty) return null;

    return _goalTooShort
        ? (
            title: l.planEntryFinishTitle,
            items: _continuations,
            // «Дописать за тебя» APPENDS as well: the continuations are written to carry the
            // learner's own sentence on, and replacing it would throw away what they typed.
            append: true,
            brass: true,
          )
        : (
            title: l.planEntryAdditionsTitle,
            items: _continuations,
            append: true,
            brass: false,
          );
  }

  /// «Тап заполняет поле сразу, без печати по буквам» (записка).
  ///
  /// v1 is STATIC: the continuations are the frames' own sentences, not a generated completion of
  /// what the learner wrote. Generating one is a model call on every keystroke's worth of doubt, and
  /// it is deliberately not in this наряд.
  void _applySuggestion(String text, {required bool append}) {
    final current = _goal.text.trimRight();
    // The «…» of a written-out addition is a typographic lead-in, not part of the sentence.
    final addition = text.replaceFirst(RegExp(r'^…\s*'), '').trim();

    // A CONTINUATION THAT RESTATED THE GOAL IS REPLACED, NOT APPENDED.
    //
    // The prompt says «never restate what is already written» and the live run of 03.09 caught it
    // being disobeyed on exactly half the goals: «Иду к врачу, болит спина» came back as «Иду к
    // врачу, болит спина, и мне нужно объяснить, где именно болит.» Appending that would have put
    // the learner's own sentence on screen twice. Whatever the model meant, what it wrote IS the
    // whole goal carried further, so it takes the field rather than joining it.
    final restatesGoal =
        current.isNotEmpty && addition.toLowerCase().startsWith(current.toLowerCase());

    final next = append && current.isNotEmpty && !restatesGoal
        ? '$current $addition'
        : addition;
    _goal.value = TextEditingValue(
      text: next,
      selection: TextSelection.collapsed(offset: next.length),
    );
  }

  // ── dictation (the same recogniser «Собрать коллекцию» uses) ────────────────────────────────

  Future<void> _startListening() async {
    // The GOAL is written in the learner's own language, so the recogniser listens for that one —
    // the support language, never the language being studied.
    final support = ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';
    _goalFocus.unfocus();

    if (!_speechInitDone) {
      _speechInitDone = true;
      try {
        final ok = await _speech.initialize(
          onStatus: (s) {
            if ((s == 'notListening' || s == 'done') && mounted) {
              setState(() => _listening = false);
            }
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
      listenOptions: SpeechListenOptions(
        partialResults: true,
        cancelOnError: true,
        localeId: sttLocaleFor(support),
      ),
    );
  }

  Future<void> _stopListening() async {
    await _speech.stop();
    if (mounted) setState(() => _listening = false);
  }

  // ── кадр V4·02 ──────────────────────────────────────────────────────────────────────────────

  Widget _languageStep(AppLocalizations l) {
    final support = ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';

    return ListView(
      padding: const EdgeInsets.only(top: 20, bottom: 32),
      children: [
        EntryRibbon(rows: _ribbon(l)),
        const SizedBox(height: 28),
        Text(l.planEntryLangTitle, style: AppText.collectionNameScreen.copyWith(fontSize: 30)),
        const SizedBox(height: 18),
        Wrap(
          spacing: 9,
          runSpacing: 9,
          children: [
            for (final lang in studyLanguagesFor(_targetLang))
              _LangChip(
                label: lang.endonym,
                selected: lang.code == _targetLang,
                onTap: () {
                  setState(() => _targetLang = lang.code);
                  // A different language is a different set of lines.
                  _startWarmup();
                },
              ),
          ],
        ),
        const SizedBox(height: 15),
        // THE NATIVE LANGUAGE IS NOT ASKED — it is the account's, decided once at onboarding. The
        // line states it and points at the one place it can be changed, which is not this screen.
        Row(
          children: [
            Flexible(
              child: Text(
                l.planEntryTranslationsInto(languageByCode(support).endonym),
                style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
              ),
            ),
            Text(
              ' · ',
              style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
            ),
            Semantics(
              button: true,
              child: InkWell(
                // PUSHED, never popped: the answers already given live on this screen, and a link
                // that dropped the learner out of the entry to change one setting would take them
                // with it. Coming back lands on the same step, filled in.
                onTap: () {
                  AppHaptics.light();
                  Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const ProfileScreen()),
                  );
                },
                child: Text(
                  l.planEntrySettingsLink,
                  style: AppText.translation.copyWith(
                    fontSize: 13,
                    color: AppColors.destructiveText,
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 30),
        Text(l.planEntryLevelTitle, style: AppText.collectionNameScreen.copyWith(fontSize: 26)),
        const SizedBox(height: 16),
        for (final value in PlanLevel.values) ...[
          EntryChoice(
            selected: value == _level,
            onTap: () {
              setState(() => _level = value);
              // …and a different level is a different set of lines.
              _startWarmup();
            },
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _levelLabel(l, value),
                  style: AppText.collectionNameCard.copyWith(
                    fontSize: 16.5,
                    fontWeight: value == _level ? FontWeight.w600 : FontWeight.w400,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _levelHint(l, value),
                  style: AppText.translation.copyWith(
                    fontSize: 13,
                    height: 1.45,
                    color: value == _level ? AppColors.secondary : AppColors.tertiary,
                  ),
                ),
              ],
            ),
          ),
          if (value != PlanLevel.values.last) const SizedBox(height: 9),
        ],
        const SizedBox(height: 24),
        EntryCta(
          label: l.planEntryNext,
          enabled: !_movingOn,
          onPressed: _fromLanguage,
        ),
        const SizedBox(height: 32),
      ],
    );
  }

  // ── кадр V4·03 ──────────────────────────────────────────────────────────────────────────────

  Widget _listenStep(AppLocalizations l) => ListView(
    padding: const EdgeInsets.only(top: 20, bottom: 32),
    children: [
      EntryRibbon(rows: _ribbon(l)),
      const SizedBox(height: 44),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
        decoration: BoxDecoration(
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: AppColors.brassInk.withValues(alpha: 0.34)),
          boxShadow: [
            BoxShadow(
              color: AppColors.ink.withValues(alpha: 0.07),
              blurRadius: 30,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Column(
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.planSelected,
                border: Border.all(color: AppColors.brassInk.withValues(alpha: 0.3)),
              ),
              child: const Icon(Icons.headset_outlined, size: 26, color: AppColors.brassInk),
            ),
            const SizedBox(height: 20),
            Text(
              l.planListenOfferTitle,
              textAlign: TextAlign.center,
              style: AppText.collectionNameScreen.copyWith(fontSize: 27, height: 1.24),
            ),
            const SizedBox(height: 14),
            Text(
              l.planListenOfferBody,
              textAlign: TextAlign.center,
              style: AppText.translation.copyWith(
                fontSize: 15,
                height: 1.6,
                color: AppColors.inkBody,
              ),
            ),
            const SizedBox(height: 22),
            EntryCta(
              label: l.planListenListen,
              icon: Icons.play_arrow_rounded,
              onPressed: _openListen,
            ),
            const SizedBox(height: 10),
            // The same height as the offer, and no persuasion under it.
            EntrySecondary(label: l.planListenSkip, onPressed: _skipListen),
            const SizedBox(height: 16),
            Text(
              l.planListenReassure,
              textAlign: TextAlign.center,
              style: AppText.translation.copyWith(
                fontSize: 12.5,
                height: 1.5,
                color: AppColors.tertiary,
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 44),
    ],
  );

  // ── кадры V4·04 / 04б ───────────────────────────────────────────────────────────────────────

  Widget _whenStep(AppLocalizations l) {
    final dated = _eventDate != null;
    final days = dated
        ? _eventDate!.difference(_dayOnly(DateTime.now())).inDays
        : null;

    return ListView(
      padding: const EdgeInsets.only(top: 20, bottom: 32),
      children: [
        EntryRibbon(rows: _ribbon(l)),
        const SizedBox(height: 30),
        Text(l.planEntryWhenTitle, style: AppText.collectionNameScreen.copyWith(fontSize: 30)),
        const SizedBox(height: 18),
        Row(
          children: [
            Expanded(
              child: EntryChoice(
                selected: dated,
                showTick: false,
                padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
                onTap: _pickDate,
                child: dated
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Text(
                            planDateLabel(context, _isoDate(_eventDate!)),
                            style: AppText.collectionNameCard.copyWith(
                              fontSize: 17,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            _weekday(context, _eventDate!).toUpperCase(),
                            style: AppText.blockLabel.copyWith(
                              fontSize: 10.5,
                              letterSpacing: 1.1,
                              color: AppColors.brassInk,
                            ),
                          ),
                        ],
                      )
                    : Center(
                        child: Text(
                          l.planEntryPickDate,
                          style: AppText.translation.copyWith(
                            fontSize: 15,
                            color: AppColors.inkBody,
                          ),
                        ),
                      ),
              ),
            ),
            const SizedBox(width: 9),
            EntryChoice(
              selected: !dated,
              showTick: false,
              padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 18),
              onTap: () => setState(() => _eventDate = null),
              child: Text(
                l.planNoDate,
                textAlign: TextAlign.center,
                style: AppText.translation.copyWith(
                  fontSize: 15,
                  fontWeight: dated ? FontWeight.w400 : FontWeight.w600,
                  color: AppColors.inkBody,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        // THE HONEST ORIENTATION LINE — and «без даты» is not «по неделям»: what the server actually
        // does with a dateless plan is open its scenes one after another, so that is what it says.
        Text(
          switch (days) {
            null => l.planEntryHintNoDate,
            <= 0 => l.planEntryHintToday,
            final int d => l.planEntryHintDays(d),
          },
          style: AppText.translation.copyWith(
            fontSize: 13,
            height: 1.55,
            color: AppColors.tertiary,
          ),
        ),
        const SizedBox(height: 32),
        Text(l.planEntryMinutesTitle, style: AppText.collectionNameScreen.copyWith(fontSize: 26)),
        const SizedBox(height: 16),
        Row(
          children: [
            for (final value in const [10, 20, 40]) ...[
              if (value != 10) const SizedBox(width: 9),
              Expanded(
                child: EntryChoice(
                  selected: value == _minutes,
                  showTick: false,
                  padding: const EdgeInsets.symmetric(vertical: 18),
                  onTap: () => setState(() => _minutes = value),
                  child: Column(
                    children: [
                      Text(
                        '$value',
                        textAlign: TextAlign.center,
                        style: AppText.collectionNameCard.copyWith(
                          fontSize: 26,
                          fontWeight: value == _minutes ? FontWeight.w600 : FontWeight.w400,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        l.planEntryMinutesUnit,
                        textAlign: TextAlign.center,
                        style: AppText.translation.copyWith(
                          fontSize: 11.5,
                          color: value == _minutes ? AppColors.secondary : AppColors.tertiary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 28),
        EntryCta(label: l.planEntryBuild, onPressed: _build),
        const SizedBox(height: 32),
      ],
    );
  }

  String _weekday(BuildContext context, DateTime date) =>
      planWeekdayLabel(context, _isoDate(date));

  Future<void> _pickDate() async {
    AppHaptics.light();
    final today = _dayOnly(DateTime.now());
    var chosen = _eventDate == null || _eventDate!.isBefore(today) ? today : _eventDate!;

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
}

String _levelLabel(AppLocalizations l, PlanLevel level) => switch (level) {
  PlanLevel.zero => l.planLevelZero,
  PlanLevel.basic => l.planLevelBasic,
  PlanLevel.conversational => l.planLevelConversational,
  PlanLevel.fluent => l.planLevelFluent,
};

String _levelHint(AppLocalizations l, PlanLevel level) => switch (level) {
  PlanLevel.zero => l.planLevelZeroHint,
  PlanLevel.basic => l.planLevelBasicHint,
  PlanLevel.conversational => l.planLevelConversationalHint,
  PlanLevel.fluent => l.planLevelFluentHint,
};

/// The studied-language chip — ink when chosen, with a brass tick inside it.
class _LangChip extends StatelessWidget {
  const _LangChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? AppColors.ink : AppColors.surfaceRaised,
    clipBehavior: Clip.antiAlias,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(24),
      side: selected ? BorderSide.none : const BorderSide(color: AppColors.track),
    ),
    child: InkWell(
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      // No `alignment` on the Container: inside a Wrap it would stretch the chip to the whole line.
      child: Container(
        constraints: const BoxConstraints(minHeight: 48),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (selected) ...[
              const Icon(Icons.check, size: 14, color: AppColors.brass),
              const SizedBox(width: 8),
            ],
            Text(
              label,
              style: AppText.translation.copyWith(
                fontSize: 15,
                fontWeight: selected ? FontWeight.w500 : FontWeight.w400,
                color: selected ? AppColors.paper : AppColors.ink,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// The dictation button inside the goal field.
class _MicButton extends StatelessWidget {
  const _MicButton({required this.listening, required this.label, required this.onTap});

  final bool listening;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: InkResponse(
      onTap: onTap,
      radius: 26,
      child: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: listening ? AppColors.destructiveText : AppColors.planSelected,
          border: Border.all(color: AppColors.brassInk.withValues(alpha: 0.32)),
        ),
        child: Icon(
          listening ? Icons.stop_rounded : Icons.mic_none_rounded,
          size: 19,
          color: listening ? AppColors.paper : AppColors.destructiveText,
        ),
      ),
    ),
  );
}
