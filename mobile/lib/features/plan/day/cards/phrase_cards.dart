import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_rules.dart';
import '../../../../data/plan/day_contract.dart';
import '../day_card_frame.dart';
import '../day_texts.dart';
import '../speech_attempt.dart';
import 'card_context.dart';
import 'word_cards.dart' show returnNoteText, returnNoteStyle;

/// Ключ фразы в её тексте — позиция куска `speaking_key`, или null.
({int at, int length})? keySpan(String text, String? key) {
  final k = key?.trim() ?? '';
  if (k.isEmpty) return null;
  final at = text.toLowerCase().indexOf(k.toLowerCase());
  return at < 0 ? null : (at: at, length: k.length);
}

/// Фраза с подчёркнутым ключом (2 px ink) — знакомство (23-4) и шит (23-15); [wash] — шалфейная
/// подложка на ключе, когда его услышали (23-5).
InlineSpan phraseWithKey(String text, String? key, {bool wash = false}) {
  final span = keySpan(text, key);
  if (span == null) {
    return wash
        ? TextSpan(text: text, style: TextStyle(background: Paint()..color = AppColors.sageWash))
        : TextSpan(text: text);
  }
  final keyStyle = wash
      ? TextStyle(background: Paint()..color = AppColors.sageWash)
      : const TextStyle(decoration: TextDecoration.underline, decorationColor: AppColors.ink, decorationThickness: 2);
  return TextSpan(
    children: [
      TextSpan(text: text.substring(0, span.at)),
      TextSpan(text: text.substring(span.at, span.at + span.length), style: keyStyle),
      TextSpan(text: text.substring(span.at + span.length)),
    ],
  );
}

/// ЗНАКОМСТВО С ФРАЗОЙ (кадр 23-4): во весь экран без фото — бейдж «новая фраза», фраза Literata
/// 30 с ключом, подчёркнутым 2 px ink, воспроизведение 44, перевод, чтение; ниже «В РАЗГОВОРЕ» —
/// где фраза живёт (реплика собеседника и ответ). «Понятно» на доке.
class PhraseIntroCard extends DayCardWidget {
  const PhraseIntroCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<PhraseIntroCard> createState() => _PhraseIntroCardState();
}

class _PhraseIntroCardState extends State<PhraseIntroCard> {
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
    final from = card.isReturned ? widget.context.returnedFromDay?.call(card.sourceDayId) : null;
    final inTalk = card.exampleTarget?.trim() ?? '';

    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 60, AppSpacing.screenH, 90),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                card.isReturned
                    ? BrassPill(l.dayReturnedBadge(from ?? widget.context.returnDay - 1))
                    : Text(l.dayPhraseBadgeNew.toUpperCase(), style: AppTextDay.sectionLabel),
                const SizedBox(height: 22),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: Text.rich(phraseWithKey(card.textTarget, card.speakingKey), style: AppTextDay.phraseIntro)),
                    const SizedBox(width: 14),
                    PlayCircle(onTap: () => unawaited(widget.context.voice.speak(card.textTarget))),
                  ],
                ),
                const SizedBox(height: 16),
                Text(card.textNative, style: AppTextDay.introTranslation),
                if (DayTexts.reading(card.pronunciationNative) case final r?) ...[
                  const SizedBox(height: 8),
                  Text(r, style: AppTextDay.introReading),
                ],
                if (inTalk.isNotEmpty) ...[
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 22),
                    child: Divider(height: 1, thickness: 1, color: AppColors.hairlineSoft),
                  ),
                  Text(l.dayPhraseInTalk.toUpperCase(), style: AppTextDay.sectionLabel),
                  const SizedBox(height: 10),
                  Text(inTalk, style: AppTextDay.exchangePartner.copyWith(height: 1.4)),
                  const SizedBox(height: 6),
                  Text(card.textTarget, style: AppTextDay.example),
                  if (card.exampleNative case final tr? when tr.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(tr, style: AppTextDay.exampleTranslation),
                  ],
                ],
              ],
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

/// «ПОВТОРИ ВСЛУХ» (кадр 23-5): блок задания оборачивает фразу Literata 26 с воспроизведением,
/// перевод и чтение; микрофон 80 внизу. Услышали ключ — шалфейная заливка-подложка на ключе,
/// «Услышали»; нет — «Ещё раз», потом «Пропустить».
class PhraseRepeatCard extends DayCardWidget {
  const PhraseRepeatCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<PhraseRepeatCard> createState() => _PhraseRepeatCardState();
}

class _PhraseRepeatCardState extends State<PhraseRepeatCard> {
  late final SpeechAttemptController _mic;
  bool _skipped = false;
  Timer? _leave;

  @override
  void initState() {
    super.initState();
    _mic = widget.context.speech(widget.card)..addListener(_onMic);
  }

  void _onMic() {
    if (mounted) setState(() {});
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
    await widget.context.session.answer(widget.card, DayCardResult.passed, attempts: _mic.attempts + 1, spokenText: _mic.transcript);
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
    final caption = switch (_mic.state) {
      RecordState.listening => l.daySayListening,
      RecordState.thinking => l.daySayThinking,
      RecordState.heard => l.daySayHeardShort,
      _ => l.daySayMic,
    };

    return DayCardFrame(
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TaskBlock(
            label: l.dayTaskRepeat,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text.rich(
                        phraseWithKey(card.textTarget, card.speakingKey ?? card.textTarget, wash: _mic.accepted),
                        style: AppTextDay.phrase,
                      ),
                    ),
                    const SizedBox(width: 14),
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
          const SizedBox(height: 26),
          if (_skipped)
            Text(l.dayReturnDay(widget.context.returnDay), textAlign: TextAlign.center, style: AppTextDay.returnNote)
          else
            SpeechVerdictLine(controller: _mic),
        ],
      ),
      bottom: Column(
        children: [
          RecordButton(state: _mic.state, caption: caption, level: _mic.level, onTap: () => unawaited(_tap())),
          if (_mic.canSkip && !_skipped) ...[
            const SizedBox(height: 12),
            TextButton(onPressed: _skip, child: Text(l.daySaySkip, style: AppTextDay.quiet)),
          ],
          QaSpeechRow(controller: _mic),
        ],
      ),
    );
  }
}

/// СБОРКА ФРАЗЫ (12b по «Базе», 23-7e-стандарт): блок задания «СОБЕРИ ПО-АНГЛИЙСКИ» + перевод в
/// кавычках, подложка сборки с плитками 44 (собранные — ink, доступные — бумага, одна лишняя из
/// словаря дня). Вердикт — тонировка подложки и маркер слева; лишняя плитка вздрагивает.
class PhraseAssembleCard extends DayCardWidget {
  const PhraseAssembleCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<PhraseAssembleCard> createState() => _PhraseAssembleCardState();
}

class _PhraseAssembleCardState extends State<PhraseAssembleCard> {
  final List<int> _placed = [];
  bool _answered = false;
  bool _correct = false;
  bool _sent = false;

  List<String> get _placedTexts => [for (final i in _placed) widget.card.tiles[i]];

  Future<void> _place(int i) async {
    if (_answered) return;
    AppHaptics.light();
    setState(() => _placed.add(i));
    if (_placed.length >= DayRules.tokens(widget.card.answer).length) await _check();
  }

  void _unplace(int idx) {
    if (_answered) return;
    setState(() => _placed.removeAt(idx));
  }

  Future<void> _check() async {
    final ok = DayRules.assembledMatches(placed: _placedTexts, answer: widget.card.answer);
    setState(() {
      _answered = true;
      _correct = ok;
    });
    if (ok) {
      AppFeedback.correct();
    } else {
      AppFeedback.wrong();
    }
    final attempt = DayRules.graded(correct: ok, isRetry: widget.card.retryOf != null);
    _sent = true;
    await widget.context.session.answer(widget.card, DayRules.resultOf(attempt), attempts: 1);
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final verdict = !_answered
        ? AnswerOptionVerdict.none
        : _correct
        ? AnswerOptionVerdict.correct
        : AnswerOptionVerdict.wrong;

    return DayCardFrame(
      topCentered: false,
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TaskBlock(
            label: l.dayTaskAssemblePhrase(DayTexts.adverb(l, widget.context.targetLang)),
            text: '«${card.promptNative ?? card.textNative}»',
          ),
          const SizedBox(height: 16),
          AssemblyBoard(
            placed: _placedTexts,
            verdict: verdict,
            onTapPlaced: _answered ? null : _unplace,
            shakeIndex: _answered && !_correct ? DayRules.extraTileIndex(placed: _placedTexts, answer: card.answer) : null,
          ),
          const SizedBox(height: 12),
          TileTray(tiles: card.tiles, used: _placed.toSet(), onTap: _answered ? null : (i) => unawaited(_place(i))),
          if (_answered && !_correct) ...[
            const SizedBox(height: 14),
            PaperCard(
              radius: AppRadii.field,
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(card.answer, style: AppTextDay.listWord),
                  const SizedBox(height: 6),
                  Text(card.textNative, style: AppTextDay.partnerTranslation),
                ],
              ),
            ),
          ],
        ],
      ),
      bottom: const SizedBox.shrink(),
      dock: _answered
          ? DayDock(
              label: l.dayNext,
              onTap: _sent ? widget.onNext : null,
              note: _correct ? null : returnNoteText(l, card, widget.context.returnDay),
              noteStyle: returnNoteStyle(card),
            )
          : null,
    );
  }
}
