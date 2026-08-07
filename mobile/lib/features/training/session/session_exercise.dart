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

/// The sliding content of one session card: the prompt, the mode-specific interaction, and — once
/// answered — the feedback that expands in the bottom of the same card (§4е). Owns its own
/// answering→feedback state; the shell owns the header, progression and recording.
class SessionExerciseCard extends ConsumerStatefulWidget {
  const SessionExerciseCard({
    super.key,
    required this.card,
    required this.autoPronounce,
    required this.onAnswered,
    required this.onNext,
    required this.onSpeak,
  });

  final SessionCard card;
  final bool autoPronounce;

  /// Called exactly once, when the user commits their answer.
  final ValueChanged<SessionAnswer> onAnswered;

  /// Advance to the next card (or the summary).
  final VoidCallback onNext;

  /// Pronounce a target-language string via the shell's TTS (respects the auto-pronounce toggle
  /// at call sites; here it's an explicit speak).
  final Future<void> Function(String text) onSpeak;

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

  @override
  void initState() {
    super.initState();
    // Listening plays on appearance — the term is never shown, only heard.
    if (_isListening) {
      WidgetsBinding.instance.addPostFrameCallback((_) => widget.onSpeak(_card.answer));
    }
  }

  @override
  void dispose() {
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
    // Auto-pronounce the correct form when the answer resolves (§4е «слово пишется само»).
    if (widget.autoPronounce) widget.onSpeak(_card.answer);
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
          PrimaryButton(label: l.sessionNext, trailingIcon: LucideIcons.arrowRight, onPressed: _submitAssembled),
        ],
        if (_answered) ...[
          const SizedBox(height: AppSpacing.s12),
          _FeedbackBlock(
            card: _card,
            verdict: _verdict!,
            onSpeak: widget.onSpeak,
            onNext: widget.onNext,
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
          _PromptPhoto(termId: _card.termId),
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
            filled: _answered ? _card.answer : null,
            correct: _verdict?.isAccepted ?? false,
          ),
          if (_card.exampleTranslation != null) ...[
            const SizedBox(height: AppSpacing.s12),
            Text(_card.exampleTranslation!, style: AppText.translation.copyWith(height: 1.4)),
          ],
          if (!_answered) ...[
            const SizedBox(height: AppSpacing.s12),
            _inputField(hideText: true), // the visible answer is the blank; keep input off-screen-ish
          ],
        ],
      ),
    );
  }

  // listening — a big play circle; typed answer below when production (12h), options handled outside.
  Widget _listeningPrompt(AppLocalizations l, {required bool typed}) {
    return PaperCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(child: _PlayCircle(onTap: () => widget.onSpeak(_card.answer), label: l.sessionListenReplay)),
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

  Widget _inputField({bool hideText = false}) {
    // The answer renders in antiqua as it's typed — the word becomes dictionary-like (12c note).
    return TextField(
      controller: _input,
      focusNode: _focus,
      autofocus: !_isListening, // listening: let the user hear first
      style: hideText
          ? const TextStyle(color: Colors.transparent, height: 0.01)
          : AppTextExercise.typingInput,
      cursorColor: AppColors.ink,
      textInputAction: TextInputAction.done,
      autocorrect: false,
      enableSuggestions: false,
      textCapitalization: TextCapitalization.none,
      onSubmitted: (_) => _submitTyped(),
      decoration: InputDecoration(
        isDense: true,
        contentPadding: const EdgeInsets.only(bottom: AppSpacing.s8),
        enabledBorder: const UnderlineInputBorder(borderSide: BorderSide(color: AppColors.track, width: 1.5)),
        focusedBorder: const UnderlineInputBorder(borderSide: BorderSide(color: AppColors.ink, width: 1.5)),
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
    required this.correct,
  });

  final String example;
  final String answer;
  final String? filled; // the resolved word, once answered
  final bool correct;

  @override
  Widget build(BuildContext context) {
    // Split the example around the answer (case-insensitive), keeping the sentence in italic
    // antiqua. If the answer isn't found, put the blank at the end so the card still plays.
    final idx = example.toLowerCase().indexOf(answer.toLowerCase());
    final before = idx >= 0 ? example.substring(0, idx) : '$example ';
    final after = idx >= 0 ? example.substring(idx + answer.length) : '';

    final blank = filled == null
        ? WidgetSpan(
            alignment: PlaceholderAlignment.baseline,
            baseline: TextBaseline.alphabetic,
            child: Container(
              width: 100,
              height: 20,
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: AppColors.tertiary, width: 1.5)),
              ),
            ),
          )
        : TextSpan(
            text: filled,
            style: AppTextExercise.clozeExample.copyWith(
              fontStyle: FontStyle.normal,
              fontWeight: FontWeight.w500,
              color: AppColors.ink,
              decoration: TextDecoration.underline,
              decorationColor: correct ? AppColors.verdictKnown : AppColors.destructiveText,
              decorationThickness: 2,
            ),
          );

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

class _PromptPhoto extends ConsumerWidget {
  const _PromptPhoto({required this.termId});
  final String termId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FutureBuilder<Term?>(
      future: ref.read(appDatabaseProvider).termById(termId),
      builder: (context, snap) {
        final url = snap.data?.imageUrl;
        if (url == null || url.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.field),
            child: Image.network(
              url,
              height: 150,
              width: double.infinity,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => const SizedBox.shrink(),
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
    required this.onNext,
  });

  final SessionCard card;
  final LocalCheck verdict;
  final Future<void> Function(String) onSpeak;
  final VoidCallback onNext;

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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          PaperCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: content),
          ),
          const SizedBox(height: AppSpacing.s12),
          PrimaryButton(label: l.sessionNext, trailingIcon: LucideIcons.arrowRight, onPressed: onNext),
        ],
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
