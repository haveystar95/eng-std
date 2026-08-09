import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/local/app_database.dart';
import '../../../data/models.dart';
import '../../../data/providers.dart';
import 'session_grading.dart';

/// One committed answer, handed up to the shell so it can record the RAW review (the server
/// grades it) and tally the summary. [verdict] is the client's instant read — feedback only.
class SessionAnswer {
  const SessionAnswer({
    required this.response,
    required this.verdict,
    required this.usedHint,
    required this.latencyMs,
  });

  final String response;
  final LocalCheck verdict;
  final bool usedHint;
  final int? latencyMs;
}

/// The pixel width the prompt photo is decoded to — the on-screen banner width (F20). Shared by the
/// live [Image.network] `cacheWidth` and the shell's precache so both hit the SAME image-cache entry.
int promptPhotoCacheWidth(BuildContext context) {
  final mq = MediaQuery.of(context);
  return (mq.size.width * mq.devicePixelRatio).round();
}

/// Warm-decode a term's prompt photo at the display width so an upcoming card transition meets an
/// already-decoded image instead of a cold ~4000px decode + GPU upload mid-slide (F20). Fire-and-
/// forget; a null/empty url or a decode error is ignored.
Future<void> precacheSessionImage(BuildContext context, String? url) async {
  if (url == null || url.isEmpty) return;
  final provider = ResizeImage(NetworkImage(url), width: promptPhotoCacheWidth(context));
  try {
    await precacheImage(provider, context);
  } catch (_) {
    // a broken image must never take down the session
  }
}

/// The sliding content of one session card: the prompt, the mode-specific interaction, and — once
/// answered — the feedback that expands in the bottom of the same card (§4е). Owns its own
/// answering→feedback state; the shell owns the header, progression and recording.
class SessionExerciseCard extends ConsumerStatefulWidget {
  const SessionExerciseCard({
    super.key,
    required this.card,
    required this.autoPronounce,
    required this.onAnswered,
    required this.onSpeak,
    this.isCurrent = _alwaysCurrent,
  });

  static bool _alwaysCurrent() => true;

  final SessionCard card;
  final bool autoPronounce;

  /// True while this card is still the on-screen one. Deferred side-effects (autopronounce, listening
  /// autoplay, keyboard focus) check it so a fast «Дальше-Дальше» that leaves the card before the
  /// post-transition callback fires cancels the effect instead of firing it on the next card (F20).
  final bool Function() isCurrent;

  /// Called exactly once, when the user commits their answer. The shell then reveals the pinned
  /// «Дальше» bar (advancing lives on the shell, not in the card — device-batch F9).
  final ValueChanged<SessionAnswer> onAnswered;

  /// Pronounce a target-language string via the shell's TTS (respects the auto-pronounce toggle
  /// at call sites; here it's an explicit speak). [slow] backs the listening «замедленно» replay.
  final Future<void> Function(String text, {bool slow}) onSpeak;

  @override
  ConsumerState<SessionExerciseCard> createState() => _SessionExerciseCardState();
}

class _SessionExerciseCardState extends ConsumerState<SessionExerciseCard> {
  final _shownAt = DateTime.now(); // ~paint time, for latency
  final _input = TextEditingController();
  final _focus = FocusNode();

  bool _answered = false;
  LocalCheck? _verdict;
  bool _usedHint = false;
  String? _picked; // multiple_choice / listening-recognition
  late final List<String> _chips = List.of(widget.card.chips ?? const []);
  final List<int> _placed = []; // indices into _chips, in assembled order

  SessionCard get _card => widget.card;
  ExerciseMode get _mode => _card.mode;

  bool get _isListening => _mode == ExerciseMode.listening;
  bool get _isCloze => _mode == ExerciseMode.cloze;

  /// A listening card with options is recognition (12g); without, production/typing (12h). The
  /// backend currently sends no options for listening, so this is the typed path — but it stays
  /// forward-compatible if options ever arrive.
  bool get _isRecognitionListening => _isListening && (_card.options?.isNotEmpty ?? false);

  Timer? _deferTimer;
  Timer? _speakTimer;

  @override
  void initState() {
    super.initState();
    // F20: side-effects that used to fire DURING the card's slide-in (and stalled it) now run AFTER
    // the transition settles, and are cancelled if the card is no longer current (fast «Дальше»).
    if (_isListening) {
      // Listening plays on appearance — the term is never shown, only heard.
      _afterTransition(() => widget.onSpeak(_card.answer));
    } else if (_mode == ExerciseMode.typing || _isCloze) {
      // Raise the keyboard after the slide, not during it (the keyboard-attach channel call was a
      // per-transition stall). Field autofocus is off; we request focus here instead.
      _afterTransition(() => _focus.requestFocus());
    }
    // Cloze types straight INTO the blank (кадр 12j): the hidden field captures the keyboard, and
    // the sentence's blank shows the letters as they're typed. Rebuild the sentence on each change.
    if (_isCloze) {
      _input.addListener(_onClozeInput);
    }
  }

  /// Run [fn] once the slide-in animation has settled, unless the card was left in the meantime.
  void _afterTransition(VoidCallback fn) {
    _deferTimer?.cancel();
    _deferTimer = Timer(AppMotion.nextTaskEnter + const Duration(milliseconds: 30), () {
      if (mounted && widget.isCurrent()) fn();
    });
  }

  void _onClozeInput() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _deferTimer?.cancel();
    _speakTimer?.cancel();
    if (_isCloze) _input.removeListener(_onClozeInput);
    _input.dispose();
    _focus.dispose();
    super.dispose();
  }

  int? _latency() {
    final ms = DateTime.now().difference(_shownAt).inMilliseconds;
    return ms > 0 ? ms : null;
  }

  void _commit(String response, {bool usedHint = false}) {
    if (_answered) return;
    final verdict = SessionGrader.check(response, _card.answer);
    switch (verdict) {
      case LocalCheck.correct:
      case LocalCheck.typo:
        AppHaptics.success();
      case LocalCheck.wrong:
        AppHaptics.warning();
    }
    setState(() {
      _answered = true;
      _verdict = verdict;
      _usedHint = usedHint;
    });
    _focus.unfocus();
    // Auto-pronounce the correct form (§4е). The TTS platform-channel call stalls the main thread
    // ~40 ms, so fire it only AFTER the feedback has settled — on a static screen that stall is
    // invisible, and a fast «Дальше» cancels it (isCurrent) so it never lands on the slide (F20).
    if (widget.autoPronounce) {
      _speakTimer?.cancel();
      _speakTimer = Timer(const Duration(milliseconds: 420), () {
        if (mounted && widget.isCurrent()) widget.onSpeak(_card.answer);
      });
    }
    widget.onAnswered(SessionAnswer(
      response: response,
      verdict: verdict,
      usedHint: usedHint,
      latencyMs: _latency(),
    ));
  }

  // ── interactions ──────────────────────────────────────────────────────────

  void _pick(String option) {
    if (_answered) return;
    setState(() => _picked = option);
    _commit(option);
  }

  void _placeChip(int i) {
    if (_answered || _placed.contains(i)) return;
    AppHaptics.light();
    setState(() => _placed.add(i));
  }

  void _unplaceChip(int i) {
    if (_answered) return;
    setState(() => _placed.remove(i));
  }

  String get _assembled => _placed.map((i) => _chips[i]).join(' ');

  void _submitAssembled() {
    if (_placed.isEmpty) return;
    _commit(_assembled);
  }

  void _submitTyped() {
    final text = _input.text.trim();
    if (text.isEmpty) return;
    _commit(text);
  }

  void _useFirstLetter() {
    if (_answered || _usedHint) return;
    final first = _card.answer.characters.isEmpty ? '' : _card.answer.characters.first;
    setState(() {
      _usedHint = true;
      if (_input.text.isEmpty) {
        _input.text = first;
        _input.selection = TextSelection.collapsed(offset: _input.text.length);
      }
    });
    _focus.requestFocus();
  }

  void _giveUp() => _commit('', usedHint: _usedHint); // honest fail — shows the answer

  // ── build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _promptCard(l),
        if (_mode == ExerciseMode.multipleChoice || _isRecognitionListening) ...[
          const SizedBox(height: AppSpacing.s12),
          _options(l),
        ],
        if (_mode == ExerciseMode.wordBank) ...[
          const SizedBox(height: AppSpacing.s16),
          _chipTray(l),
        ],
        if (!_answered && (_mode.isTyped && !_isRecognitionListening)) ...[
          const SizedBox(height: AppSpacing.s12),
          _auxButtons(l),
        ],
        if (!_answered && _mode == ExerciseMode.wordBank && _placed.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s12),
          // «Проверить», not «Дальше»: this submits the assembled phrase (grades it) — the
          // feedback block then shows the real «Дальше» that advances. Two distinct steps, two
          // distinct labels, so the first tap doesn't read as a no-op (device-batch F12).
          PrimaryButton(label: l.sessionCheck, onPressed: _submitAssembled),
        ],
        if (_answered) ...[
          const SizedBox(height: AppSpacing.s12),
          _FeedbackBlock(
            card: _card,
            verdict: _verdict!,
            onSpeak: widget.onSpeak,
          ),
        ],
      ],
    );
  }

  // The prompt / question region — differs per mode.
  Widget _promptCard(AppLocalizations l) {
    if (_isListening && !_isRecognitionListening) return _listeningPrompt(l, typed: true);
    if (_isRecognitionListening) return _listeningPrompt(l, typed: false);
    if (_isCloze) return _clozePrompt(l);
    if (_mode == ExerciseMode.wordBank) return _wordBankPrompt(l);
    if (_mode == ExerciseMode.typing) return _typingPrompt(l);
    return _choicePrompt(l); // multiple_choice
  }

  String _instructionFor(AppLocalizations l) => switch (_mode) {
        ExerciseMode.multipleChoice => l.sessionInstrChoose,
        ExerciseMode.wordBank => l.sessionInstrAssemble,
        ExerciseMode.typing => l.sessionInstrType,
        ExerciseMode.cloze => l.sessionInstrType,
        ExerciseMode.listening => _isRecognitionListening ? l.sessionInstrListenChoose : l.sessionInstrListenType,
      };

  String _typeLabel(AppLocalizations l) => switch (_card.type) {
        'word' => l.triageTermTypeWord,
        'phrase' => l.triageTermTypePhrase,
        'idiom' => l.triageTermTypeIdiom,
        'phrasal_verb' => l.triageTermTypePhrasalVerb,
        _ => l.triageTermTypePhrase,
      };

  Widget _instructionLine(AppLocalizations l, {bool withType = true}) {
    final text = withType ? '${_typeLabel(l)} · ${_instructionFor(l)}' : _instructionFor(l);
    return Text(text, style: AppTextExercise.taskInstruction);
  }

  // multiple_choice — photo (when the term has one) + prompt + instruction.
  Widget _choicePrompt(AppLocalizations l) {
    return PaperCard(
      clipContent: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PromptPhoto(termId: _card.termId), // photo shown immediately; precache keeps it warm (F20)
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l),
        ],
      ),
    );
  }

  // typing — prompt + inline input field (12c). No photo in the question.
  Widget _typingPrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l, withType: false),
          const SizedBox(height: AppSpacing.s16),
          _inputField(),
        ],
      ),
    );
  }

  // word_bank — prompt + the assembly line inside the card (12b).
  Widget _wordBankPrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(_card.prompt ?? '', style: AppTextExercise.taskPromptRu),
          const SizedBox(height: AppSpacing.s4),
          _instructionLine(l),
          const SizedBox(height: AppSpacing.s16),
          _AssemblyLine(
            words: _placed.map((i) => _chips[i]).toList(),
            answered: _answered,
            correct: _verdict?.isAccepted ?? false,
            onTapWord: (idx) => _unplaceChip(_placed[idx]),
          ),
        ],
      ),
    );
  }

  // cloze — the example with a blank at the answer's position (12i/12j).
  Widget _clozePrompt(AppLocalizations l) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.sessionClozeInsert.toUpperCase(), style: AppText.sectionLabel),
          const SizedBox(height: AppSpacing.s12),
          _ClozeSentence(
            example: _card.example ?? _card.answer,
            answer: _card.answer,
            // Answered → the correct word fills the blank (verdict-coloured). While typing → the live
            // input fills it (plain), so the user sees what they write right in the sentence (12j).
            filled: _answered ? _card.answer : (_input.text.trim().isEmpty ? null : _input.text),
            answered: _answered,
            correct: _verdict?.isAccepted ?? false,
          ),
          if (_card.exampleTranslation != null) ...[
            const SizedBox(height: AppSpacing.s12),
            Text(_card.exampleTranslation!, style: AppText.translation.copyWith(height: 1.4)),
          ],
          if (!_answered)
            // The visible answer is the blank in the sentence above; this field is invisible and
            // only captures the keyboard (transparent text, no underline).
            _inputField(hideText: true, borderless: true),
        ],
      ),
    );
  }

  // listening — a big play circle (tap = replay) + a «замедленно» slow-replay control; typed answer
  // below when production (12h), options handled outside. The term text is never shown — only heard.
  Widget _listeningPrompt(AppLocalizations l, {required bool typed}) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(child: _PlayCircle(onTap: () => widget.onSpeak(_card.answer), label: l.sessionListenReplay)),
          const SizedBox(height: AppSpacing.s12),
          Center(
            child: QuietButton(
              label: l.sessionListenReplaySlow,
              icon: LucideIcons.gauge,
              onPressed: () => widget.onSpeak(_card.answer, slow: true),
            ),
          ),
          const SizedBox(height: AppSpacing.s16),
          Text(_instructionFor(l), textAlign: TextAlign.center, style: AppTextExercise.taskInstruction),
          if (typed && !_answered) ...[
            const SizedBox(height: AppSpacing.s16),
            _inputField(),
          ],
        ],
      ),
    );
  }

  Widget _inputField({bool hideText = false, bool borderless = false}) {
    // The answer renders in antiqua as it's typed — the word becomes dictionary-like (12c note).
    // [borderless] + [hideText] together make an invisible keyboard-capture field (cloze types into
    // the sentence blank, so the field itself must not show a stray underline).
    const noBorder = UnderlineInputBorder(borderSide: BorderSide(color: Colors.transparent));
    return TextField(
      controller: _input,
      focusNode: _focus,
      // Focus is requested AFTER the slide (see initState) so the keyboard-raise doesn't stall the
      // transition (F20); listening never auto-focuses (let the user hear first).
      autofocus: false,
      style: hideText
          ? const TextStyle(color: Colors.transparent, height: 0.01)
          : AppTextExercise.typingInput,
      cursorColor: borderless ? Colors.transparent : AppColors.ink,
      textInputAction: TextInputAction.done,
      autocorrect: false,
      enableSuggestions: false,
      textCapitalization: TextCapitalization.none,
      onSubmitted: (_) => _submitTyped(),
      decoration: InputDecoration(
        isDense: true,
        contentPadding: const EdgeInsets.only(bottom: AppSpacing.s8),
        enabledBorder: borderless ? noBorder : const UnderlineInputBorder(borderSide: BorderSide(color: AppColors.track, width: 1.5)),
        focusedBorder: borderless ? noBorder : const UnderlineInputBorder(borderSide: BorderSide(color: AppColors.ink, width: 1.5)),
      ),
    );
  }

  Widget _options(AppLocalizations l) {
    final opts = _card.options ?? const <String>[];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final o in opts) ...[
          _SessionOption(
            text: o,
            answered: _answered,
            isAnswer: SessionGrader.check(o, _card.answer).isAccepted,
            isPicked: _picked == o,
            onTap: () => _pick(o),
          ),
          if (o != opts.last) const SizedBox(height: AppSpacing.s12),
        ],
      ],
    );
  }

  Widget _chipTray(AppLocalizations l) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (var i = 0; i < _chips.length; i++)
              _WordChip(
                text: _chips[i],
                used: _placed.contains(i),
                onTap: _answered ? null : () => _placeChip(i),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.s16),
        Text(l.sessionChipReturnHint, style: AppTextExercise.taskInstruction),
      ],
    );
  }

  Widget _auxButtons(AppLocalizations l) {
    return Row(
      children: [
        Expanded(child: QuietButton(label: l.sessionHintFirstLetter, onPressed: _usedHint ? null : _useFirstLetter)),
        const SizedBox(width: AppSpacing.s12),
        Expanded(child: QuietButton(label: l.sessionDontRemember, onPressed: _giveUp)),
      ],
    );
  }
}

// ── option (multiple choice / listening recognition) ──────────────────────────

class _SessionOption extends StatelessWidget {
  const _SessionOption({
    required this.text,
    required this.answered,
    required this.isAnswer,
    required this.isPicked,
    required this.onTap,
  });

  final String text;
  final bool answered;
  final bool isAnswer;
  final bool isPicked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // Post-answer marking: the correct option draws a sage underline + check; a wrong pick a
    // terracotta underline + cross. Untouched options stay plain.
    final showCorrect = answered && isAnswer;
    final showWrong = answered && isPicked && !isAnswer;
    final markColor = showCorrect ? AppColors.verdictKnown : (showWrong ? AppColors.destructiveText : null);
    final icon = showCorrect ? LucideIcons.check : (showWrong ? LucideIcons.x : null);

    return PaperCard(
      onTap: answered ? null : onTap,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text(text, style: AppTextExercise.answerOption)),
              if (icon != null) Icon(icon, size: 17, color: markColor),
            ],
          ),
          if (markColor != null) ...[
            const SizedBox(height: 9),
            _DrawnUnderline(color: markColor, draw: showCorrect),
          ],
        ],
      ),
    );
  }
}

/// A 2-px verdict underline. The correct one draws left→right (220 ms ease-out, §4е); a wrong
/// mark shows immediately full-width (it's not a reward). Reduce-motion → instant either way.
class _DrawnUnderline extends StatelessWidget {
  const _DrawnUnderline({required this.color, required this.draw});
  final Color color;
  final bool draw;

  @override
  Widget build(BuildContext context) {
    final line = SizedBox(height: 2, child: ColoredBox(color: color));
    if (!draw || MediaQuery.of(context).disableAnimations) {
      return line;
    }
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.answerCorrect,
      curve: AppMotion.easeOut,
      builder: (_, t, _) => Align(
        alignment: Alignment.centerLeft,
        child: FractionallySizedBox(widthFactor: t, child: line),
      ),
    );
  }
}

// ── word-bank pieces ──────────────────────────────────────────────────────────

class _WordChip extends StatelessWidget {
  const _WordChip({required this.text, required this.used, required this.onTap});
  final String text;
  final bool used;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    // A placed chip leaves a faded copy behind (§4е); an available chip is raised paper.
    if (used) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 9),
        decoration: BoxDecoration(color: AppColors.faintInk, borderRadius: BorderRadius.circular(AppRadii.field)),
        child: Text(text, style: AppTextExercise.dictionaryChip.copyWith(color: AppColors.tertiary)),
      );
    }
    return Material(
      color: AppColors.surfaceRaised,
      borderRadius: BorderRadius.circular(AppRadii.field),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.field),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 9),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(AppRadii.field), boxShadow: AppShadows.card),
          child: Text(text, style: AppTextExercise.dictionaryChip),
        ),
      ),
    );
  }
}

class _AssemblyLine extends StatelessWidget {
  const _AssemblyLine({
    required this.words,
    required this.answered,
    required this.correct,
    required this.onTapWord,
  });

  final List<String> words;
  final bool answered;
  final bool correct;
  final ValueChanged<int> onTapWord;

  @override
  Widget build(BuildContext context) {
    final underline = answered
        ? (correct ? AppColors.verdictKnown : AppColors.destructiveText)
        : AppColors.track;
    return Container(
      constraints: const BoxConstraints(minHeight: 42),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: underline, width: 1.5)),
      ),
      padding: const EdgeInsets.only(bottom: 7),
      child: words.isEmpty
          ? const SizedBox(height: 30)
          : Wrap(
              spacing: 9,
              runSpacing: 4,
              crossAxisAlignment: WrapCrossAlignment.end,
              children: [
                for (var i = 0; i < words.length; i++)
                  GestureDetector(
                    onTap: answered ? null : () => onTapWord(i),
                    child: Text(words[i], style: AppTextExercise.assemblyLine),
                  ),
              ],
            ),
    );
  }
}

// ── cloze ────────────────────────────────────────────────────────────────────

class _ClozeSentence extends StatelessWidget {
  const _ClozeSentence({
    required this.example,
    required this.answer,
    required this.filled,
    required this.answered,
    required this.correct,
  });

  final String example;
  final String answer;

  /// The word to show in the blank: the live typed text while answering, or the correct word once
  /// answered; null when the blank is still empty.
  final String? filled;
  final bool answered;
  final bool correct;

  @override
  Widget build(BuildContext context) {
    // Split the example around the answer (case-insensitive), keeping the sentence in italic
    // antiqua. If the answer isn't found, put the blank at the end so the card still plays.
    final idx = example.toLowerCase().indexOf(answer.toLowerCase());
    final before = idx >= 0 ? example.substring(0, idx) : '$example ';
    final after = idx >= 0 ? example.substring(idx + answer.length) : '';

    final InlineSpan blank;
    if (filled == null) {
      // Empty blank ≈ the word's width, with a caret so it reads as "type here" (12i).
      blank = WidgetSpan(
        alignment: PlaceholderAlignment.baseline,
        baseline: TextBaseline.alphabetic,
        child: Container(
          width: 100,
          height: 22,
          alignment: Alignment.centerLeft,
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.tertiary, width: 1.5)),
          ),
          child: const SizedBox(width: 1.5, height: 20, child: ColoredBox(color: AppColors.ink)),
        ),
      );
    } else {
      // Answered → verdict-coloured underline; while typing → a plain ink underline (no verdict yet).
      blank = TextSpan(
        text: filled,
        style: AppTextExercise.clozeExample.copyWith(
          fontStyle: FontStyle.normal,
          fontWeight: FontWeight.w500,
          color: AppColors.ink,
          decoration: TextDecoration.underline,
          decorationColor: answered
              ? (correct ? AppColors.verdictKnown : AppColors.destructiveText)
              : AppColors.tertiary,
          decorationThickness: answered ? 2 : 1.5,
        ),
      );
    }

    return Text.rich(
      TextSpan(
        style: AppTextExercise.clozeExample,
        children: [TextSpan(text: before), blank, TextSpan(text: after)],
      ),
    );
  }
}

// ── listening play circle ─────────────────────────────────────────────────────

class _PlayCircle extends StatefulWidget {
  const _PlayCircle({required this.onTap, required this.label});
  final VoidCallback onTap;
  final String label;

  @override
  State<_PlayCircle> createState() => _PlayCircleState();
}

class _PlayCircleState extends State<_PlayCircle> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse =
      AnimationController(vsync: this, duration: AppMotion.listenPulse, lowerBound: 1.0, upperBound: 1.04);

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  void _tap() {
    AppHaptics.light();
    if (!MediaQuery.of(context).disableAnimations) {
      _pulse.forward(from: 1.0).then((_) => _pulse.reverse());
    }
    widget.onTap();
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: widget.label,
      child: GestureDetector(
        onTap: _tap,
        child: ScaleTransition(
          scale: _pulse,
          child: Container(
            width: 112,
            height: 112,
            decoration: const BoxDecoration(color: AppColors.ink, shape: BoxShape.circle, boxShadow: AppShadows.anchor),
            child: const Icon(LucideIcons.volume2, color: AppColors.paper, size: 44),
          ),
        ),
      ),
    );
  }
}

// ── prompt photo (from the local term mirror) ─────────────────────────────────

class _PromptPhoto extends ConsumerStatefulWidget {
  const _PromptPhoto({required this.termId});
  final String termId;

  @override
  ConsumerState<_PromptPhoto> createState() => _PromptPhotoState();
}

class _PromptPhotoState extends ConsumerState<_PromptPhoto> {
  // Query once (not on every rebuild). The image is shown as soon as it's ready; precache keeps it
  // warm so a photo card meets a decoded image rather than a cold network load (F20).
  late final Future<Term?> _term = ref.read(appDatabaseProvider).termById(widget.termId);

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Term?>(
      future: _term,
      builder: (context, snap) {
        final resolved = snap.connectionState == ConnectionState.done;
        final url = snap.data?.imageUrl;
        // A resolved term with no photo collapses; while the (fast, local) lookup is in flight we
        // reserve the banner height so resolving it doesn't jump the layout mid-slide.
        if (resolved && (url == null || url.isEmpty)) return const SizedBox.shrink();

        final showImage = url != null && url.isNotEmpty;
        return Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.field),
            child: SizedBox(
              height: 150,
              width: double.infinity,
              child: showImage
                  ? Image.network(
                      url,
                      height: 150,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      // Decode to the on-screen width, not the source's native ~4000px; matches
                      // `precacheSessionImage` so the precache warms this exact cache entry (F20).
                      cacheWidth: promptPhotoCacheWidth(context),
                      // Soft fade-in as the (precached) image lands, so it appears smoothly (F20).
                      frameBuilder: (context, child, frame, wasSync) => AnimatedOpacity(
                        opacity: frame == null ? 0 : 1,
                        duration: const Duration(milliseconds: 180),
                        child: child,
                      ),
                      errorBuilder: (_, _, _) => const ColoredBox(color: AppColors.track),
                    )
                  : const ColoredBox(color: AppColors.track), // placeholder during the slide / load
            ),
          ),
        );
      },
    );
  }
}

// ── feedback (12d, in the bottom of the same card) ────────────────────────────

class _FeedbackBlock extends ConsumerWidget {
  const _FeedbackBlock({
    required this.card,
    required this.verdict,
    required this.onSpeak,
  });

  final SessionCard card;
  final LocalCheck verdict;
  final Future<void> Function(String) onSpeak;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final wrong = verdict == LocalCheck.wrong;

    final verdictRow = _verdictRow(l);
    final content = <Widget>[
      verdictRow,
      if (wrong) ...[
        const SizedBox(height: AppSpacing.s16),
        _PromptPhoto(termId: card.termId),
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(child: _WritesItself(text: card.answer, style: AppTextExercise.feedbackTerm)),
            _SpeakDot(onTap: () => onSpeak(card.answer)),
          ],
        ),
        if (card.transcription != null && card.transcription!.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s4),
          Text('/${card.transcription}/', style: AppTextExercise.feedbackTranscription),
        ],
      ],
      if (card.example != null && card.example!.isNotEmpty) ...[
        const SizedBox(height: AppSpacing.s12),
        Text(card.example!, style: AppText.usageExample),
      ],
      _NextDue(termId: card.termId),
    ];

    return AnimatedSize(
      duration: AppMotion.feedbackReveal,
      curve: AppMotion.easeOut,
      alignment: Alignment.topCenter,
      child: PaperCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: content),
      ),
    );
  }

  Widget _verdictRow(AppLocalizations l) {
    final (color, icon, text) = switch (verdict) {
      LocalCheck.correct => (AppColors.verdictKnown, LucideIcons.check, l.sessionFeedbackCorrect),
      LocalCheck.typo => (AppColors.verdictKnown, LucideIcons.check, l.sessionFeedbackAlmost),
      LocalCheck.wrong => (AppColors.destructiveText, LucideIcons.x, l.sessionFeedbackWrong),
    };
    return Row(
      children: [
        Icon(icon, size: 17, color: color),
        const SizedBox(width: 9),
        Flexible(
          child: Text.rich(
            TextSpan(
              style: AppTextExercise.feedbackVerdict.copyWith(color: color),
              children: [
                TextSpan(text: text),
                // A typo shows the corrected form right after «Почти:».
                if (verdict == LocalCheck.typo)
                  TextSpan(text: ' ${card.answer}', style: AppTextExercise.feedbackCorrectForm),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// The real next-due, read reactively from the local progress mirror — it lands after the
/// answer's upload + sync. The client never computes an interval; if the schedule isn't known
/// yet (offline / not synced), the line is simply absent.
class _NextDue extends ConsumerWidget {
  const _NextDue({required this.termId});
  final String termId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final prog = ref.watch(termProgressForProvider(termId));
    final due = prog.value?.dueAt;
    if (due == null) return const SizedBox.shrink();
    final days = daysUntil(due.toLocal(), DateTime.now());
    final when = days == 0
        ? l.sessionDueToday
        : days == 1
            ? l.sessionDueTomorrow
            : l.sessionDueInDays(days);
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.s16),
      child: Text(l.sessionSeeAgain(when), style: AppTextExercise.feedbackNextDue),
    );
  }
}

/// The correct form «writes itself»: characters reveal in sequence, 24 ms each, capped ≤ 350 ms
/// (§4е). Reduce-motion → the whole word at once.
class _WritesItself extends StatelessWidget {
  const _WritesItself({required this.text, required this.style});
  final String text;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations || text.isEmpty) {
      return Text(text, style: style);
    }
    final total = Duration(
      milliseconds: (AppMotion.writePerChar.inMilliseconds * text.characters.length)
          .clamp(0, AppMotion.writeTotalCap.inMilliseconds),
    );
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: total,
      curve: AppMotion.easeOut,
      builder: (_, t, _) {
        final n = (t * text.characters.length).round().clamp(0, text.characters.length);
        return Text(text.characters.take(n).toString(), style: style);
      },
    );
  }
}

class _SpeakDot extends StatelessWidget {
  const _SpeakDot({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: InkResponse(
        onTap: onTap,
        radius: 24,
        child: Container(
          width: 38,
          height: 38,
          decoration: const BoxDecoration(
            shape: BoxShape.circle,
            border: Border.fromBorderSide(BorderSide(color: AppColors.hairline)),
          ),
          child: const Icon(LucideIcons.volume2, size: 18, color: AppColors.ink),
        ),
      ),
    );
  }
}
