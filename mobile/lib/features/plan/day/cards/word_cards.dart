import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/day_rules.dart';
import '../../../../data/plan/day_contract.dart';
import '../../../training/session/session_exercise.dart' show spanPositionIn, termSearchForm;
import '../day_card_frame.dart';
import '../day_texts.dart';
import '../speech_attempt.dart';
import 'card_context.dart';

/// ЗНАКОМСТВО СО СЛОВОМ ВО ВЕСЬ ЭКРАН (кадры 23-1, 23-13; правка 16a «Базы»): фото 220 на всю
/// ширину, слово Literata 46 с воспроизведением 44, перевод, чтение кириллицей, пример курсивом с
/// подчёркнутым словом. Одна краска — фото; у вернувшегося слова латунная пилюля над словом стоит на
/// бумаге, а не на фото. «Понятно» на доке.
class WordIntroCard extends DayCardWidget {
  const WordIntroCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<WordIntroCard> createState() => _WordIntroCardState();
}

class _WordIntroCardState extends State<WordIntroCard> {
  Timer? _speak;

  @override
  void initState() {
    super.initState();
    if (widget.context.autoPronounce) {
      _speak = Timer(AppMotion.nextTaskEnter + const Duration(milliseconds: 60), () {
        if (mounted) unawaited(widget.context.voice.speak(widget.card.textTarget));
      });
    }
  }

  @override
  void dispose() {
    _speak?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final example = card.exampleTarget?.trim() ?? '';
    InlineSpan? span;
    if (example.isNotEmpty) {
      final needle = termSearchForm(card.textTarget);
      final at = spanPositionIn(example, needle);
      span = underlinedExample(example, at: at, length: needle.length);
    }
    final from = card.isReturned ? widget.context.returnedFromDay?.call(card.sourceDayId) : null;
    final image = card.image?.url;

    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 90),
            child: IntroLayout(
              photo: image == null ? null : CachedNetworkImage(image),
              badge: card.isReturned
                  ? BrassPill(l.dayReturnedBadge(from ?? widget.context.returnDay - 1))
                  : OutlineBadge(l.dayIntroBadgeNew),
              term: card.textTarget,
              translation: card.textNative,
              reading: DayTexts.reading(card.pronunciationNative),
              example: span,
              exampleTranslation: card.exampleNative,
              onSpeak: () => unawaited(widget.context.voice.speak(card.textTarget)),
            ),
          ),
        ),
        DayDock(
          label: l.dayIntroCta,
          onTap: () async {
            await widget.context.session.acknowledge(card);
            widget.onNext();
          },
        ),
      ],
    );
  }
}

/// «ПРОИЗНЕСИ» (кадры 23-3a–d): блок задания 4м оборачивает слово Literata 46 с воспроизведением,
/// перевод и чтение; пример с переводом под блоком; внизу микрофон 80 с амплитудой. Услышали —
/// шалфейная подложка на слове, строка «Услышали: …», микрофон шалфеем, звук «верно»; карточка
/// уходит сама через 600 мс. Не расслышали — «Ещё раз»; после второй попытки «Пропустить» —
/// карточка уходит с терракотовой пометкой «Вернётся в день N».
class WordSayCard extends DayCardWidget {
  const WordSayCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<WordSayCard> createState() => _WordSayCardState();
}

class _WordSayCardState extends State<WordSayCard> {
  late final SpeechAttemptController _mic;
  bool _skipped = false;
  Timer? _leave;

  @override
  void initState() {
    super.initState();
    _mic = widget.context.speech(widget.card)..addListener(_onMic);
  }

  void _onMic() {
    if (!mounted) return;
    setState(() {});
  }

  @override
  void dispose() {
    _leave?.cancel();
    _mic.removeListener(_onMic);
    _mic.dispose();
    super.dispose();
  }

  Future<void> _tap() async {
    final outcome = await _mic.tap();
    if (!mounted || outcome != SpeechAttemptOutcome.accepted) return;
    AppFeedback.correct();
    await widget.context.session.answer(
      widget.card,
      DayCardResult.passed,
      attempts: _mic.attempts + 1,
      spokenText: _mic.transcript,
    );
    _leave = Timer(AppMotion.spokenAutoLeave, () {
      if (mounted) widget.onNext();
    });
  }

  Future<void> _skip() async {
    setState(() => _skipped = true);
    await widget.context.session.answer(widget.card, DayCardResult.skipped, attempts: _mic.attempts);
    _leave = Timer(AppMotion.spokenAutoLeave, () {
      if (mounted) widget.onNext();
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final heard = _mic.accepted;

    final word = Text.rich(
      TextSpan(
        text: card.textTarget,
        style: heard
            ? AppTextDay.wordBig.copyWith(background: Paint()..color = AppColors.sageWash)
            : AppTextDay.wordBig,
      ),
    );

    return DayCardFrame(
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TaskBlock(
            label: l.dayTaskPronounce,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: word),
                    const SizedBox(width: 16),
                    PlayCircle(onTap: () => unawaited(widget.context.voice.speak(card.textTarget))),
                  ],
                ),
                const SizedBox(height: 12),
                Text(card.textNative, style: AppTextDay.taskTranslation),
                if (DayTexts.reading(card.pronunciationNative) case final r?) ...[
                  const SizedBox(height: 6),
                  Text(r, style: AppTextDay.taskReading),
                ],
              ],
            ),
          ),
          if (card.exampleTarget case final ex? when ex.isNotEmpty) ...[
            const SizedBox(height: 18),
            Text(ex, style: AppTextDay.example),
            if (card.exampleNative case final tr? when tr.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(tr, style: AppTextDay.exampleTranslation),
            ],
          ],
          const SizedBox(height: 26),
          if (_skipped)
            Text(l.dayReturnDay(widget.context.returnDay), textAlign: TextAlign.center, style: AppTextDay.returnNote)
          else
            SpeechVerdictLine(controller: _mic, heardWord: card.textTarget),
        ],
      ),
      bottom: _SpeechBottom(controller: _mic, onTap: _tap, onSkip: _skip, skipped: _skipped),
    );
  }
}

/// Микрофон 80, «Пропустить» после двух попыток, дев-ряд QA.
class _SpeechBottom extends StatelessWidget {
  const _SpeechBottom({required this.controller, required this.onTap, required this.onSkip, required this.skipped});
  final SpeechAttemptController controller;
  final VoidCallback onTap;
  final VoidCallback onSkip;
  final bool skipped;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final caption = switch (controller.state) {
      RecordState.listening => l.daySayListening,
      RecordState.thinking => l.daySayThinking,
      RecordState.heard => l.daySayHeardShort,
      _ => l.daySayMic,
    };
    return Column(
      children: [
        RecordButton(state: controller.state, caption: caption, level: controller.level, onTap: onTap),
        if (controller.canSkip && !skipped) ...[
          const SizedBox(height: 12),
          TextButton(onPressed: onSkip, child: Text(l.daySaySkip, style: AppTextDay.quiet)),
        ],
        QaSpeechRow(controller: controller),
      ],
    );
  }
}

/// ВЫБОР ИЗ ЧЕТЫРЁХ (12a по «Базе»): фото, блок задания «ВЫБЕРИ ПЕРЕВОД» + слово (Beginner) или
/// «ВЫБЕРИ СЛОВО» + определение (Intermediate), варианты 4л. Верно с первого раза — passed; ошибка
/// — «Вернётся в конце этапа»; вторая ошибка — терракотой «Вернётся в день N».
class WordChooseCard extends DayCardWidget {
  const WordChooseCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<WordChooseCard> createState() => _WordChooseCardState();
}

class _WordChooseCardState extends State<WordChooseCard> {
  int? _picked;
  bool _sent = false;

  bool get _answered => _picked != null;
  bool get _correct => _picked != null && widget.card.options[_picked!].correct;

  Future<void> _pick(int i) async {
    if (_answered) return;
    setState(() => _picked = i);
    if (widget.card.options[i].correct) {
      AppFeedback.correct();
    } else {
      AppFeedback.wrong();
    }
    final attempt = DayRules.graded(correct: _correct, isRetry: widget.card.retryOf != null);
    _sent = true;
    await widget.context.session.answer(widget.card, DayRules.resultOf(attempt), attempts: 1);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final byDefinition = card.mode == 'definition';
    final image = card.image?.url;

    return DayCardFrame(
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (image != null) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(AppRadii.field),
              child: SizedBox(
                height: 150,
                width: double.infinity,
                child: ColoredBox(
                  color: AppColors.photoSlot,
                  child: Image(image: CachedNetworkImage(image), fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
                ),
              ),
            ),
            const SizedBox(height: 14),
          ],
          if (byDefinition)
            TaskBlock(
              label: l.dayTaskChooseWord,
              child: Text(card.prompt ?? card.definitionTarget ?? '', style: AppTextExercise.answerOption),
            )
          else
            TaskBlock(label: l.dayTaskChoose, text: card.prompt ?? card.textTarget),
        ],
      ),
      bottom: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < card.options.length; i++) ...[
            if (i > 0) const SizedBox(height: 10),
            AnswerOption(
              text: card.options[i].text,
              target: byDefinition,
              verdict: _verdictOf(i),
              onTap: () => unawaited(_pick(i)),
            ),
          ],
        ],
      ),
      dock: _answered
          ? DayDock(
              label: l.dayNext,
              onTap: _sent ? widget.onNext : null,
              note: _correct ? null : returnNoteText(l, widget.card, widget.context.returnDay),
              noteStyle: returnNoteStyle(widget.card),
            )
          : null,
    );
  }

  AnswerOptionVerdict _verdictOf(int i) {
    if (!_answered) return AnswerOptionVerdict.none;
    final correct = widget.card.options[i].correct;
    if (i == _picked) return correct ? AnswerOptionVerdict.correct : AnswerOptionVerdict.wrong;
    if (correct) return AnswerOptionVerdict.correctQuiet;
    return AnswerOptionVerdict.dimmed;
  }
}

/// «Вернётся в конце этапа» на первой ошибке; терракотой «Вернётся в день N» на второй (карточка,
/// уже вернувшаяся после первой).
String returnNoteText(AppLocalizations l, DayCard card, int returnDay) =>
    card.retryOf != null ? l.dayReturnDay(returnDay) : l.dayReturnStage;

TextStyle returnNoteStyle(DayCard card) => card.retryOf != null ? AppTextDay.returnNote : AppTextDay.quiet;

/// СЛОВО В ПРИМЕР (12i по «Базе»): блок задания «ВСТАВЬ СЛОВО» + пример с пропуском ровно по
/// ширине слова, перевод примера под ним серым; варианты — слова дня (4л, Literata).
class WordClozeCard extends DayCardWidget {
  const WordClozeCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<WordClozeCard> createState() => _WordClozeCardState();
}

class _WordClozeCardState extends State<WordClozeCard> {
  int? _picked;
  bool _sent = false;

  bool get _answered => _picked != null;
  bool get _correct => _picked != null && widget.card.options[_picked!].correct;

  Future<void> _pick(int i) async {
    if (_answered) return;
    setState(() => _picked = i);
    if (widget.card.options[i].correct) {
      AppFeedback.correct();
    } else {
      AppFeedback.wrong();
    }
    final attempt = DayRules.graded(correct: _correct, isRetry: widget.card.retryOf != null);
    _sent = true;
    await widget.context.session.answer(widget.card, DayRules.resultOf(attempt), attempts: 1);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final sentence = card.sentenceTarget ?? '';

    return DayCardFrame(
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TaskBlock(
            label: l.dayTaskCloze,
            child: ClozeSentence(
              example: sentence,
              answer: card.answer,
              filled: _picked == null ? null : card.options[_picked!].text,
              answered: _answered,
              correct: _correct,
              style: AppTextExercise.clozeExample.copyWith(fontSize: 21, height: 1.55, color: AppColors.ink),
              mistakes: _answered && !_correct ? const {0} : const {},
            ),
          ),
          if (card.sentenceNative case final tr? when tr.isNotEmpty) ...[
            const SizedBox(height: 14),
            Text(tr, style: AppText.translation.copyWith(fontSize: 13.5, height: 1.45)),
          ],
        ],
      ),
      bottom: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < card.options.length; i++) ...[
            if (i > 0) const SizedBox(height: 10),
            AnswerOption(
              text: card.options[i].text,
              target: true,
              verdict: _verdictOf(i),
              onTap: () => unawaited(_pick(i)),
            ),
          ],
        ],
      ),
      dock: _answered
          ? DayDock(
              label: l.dayNext,
              onTap: _sent ? widget.onNext : null,
              note: _correct ? null : returnNoteText(l, widget.card, widget.context.returnDay),
              noteStyle: returnNoteStyle(widget.card),
            )
          : null,
    );
  }

  AnswerOptionVerdict _verdictOf(int i) {
    if (!_answered) return AnswerOptionVerdict.none;
    final correct = widget.card.options[i].correct;
    if (i == _picked) return correct ? AnswerOptionVerdict.correct : AnswerOptionVerdict.wrong;
    if (correct) return AnswerOptionVerdict.correctQuiet;
    return AnswerOptionVerdict.dimmed;
  }
}
