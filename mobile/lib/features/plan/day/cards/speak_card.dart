import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_rules.dart';
import '../../../../data/plan/plan_contract.dart';
import '../day_texts.dart';
import '../day_voice.dart';
import '../speech_attempt.dart';
import 'card_context.dart';
import 'phrase_cards.dart' show phraseWithKey;

/// «ГОВОРЮ САМ» (кадры 23-8a–e): сессия-разговор. Лента прошедших обменов сверху — врач слева с
/// текстом, ты справа тёмной плитой с тем, что распознано; текущий обмен внизу: «ВРАЧ ГОВОРИТ» с
/// волной без текста, блок задания «СКАЖИ ПО-АНГЛИЙСКИ» + перевод в кавычках, микрофон 80,
/// «Подсказка»: первая — ключ Literata 19 с подчёркиванием (зачёт полный), вторая — весь текст
/// (зачёт с подсказкой, охра, «вернётся в день N»), «Пропустить» после двух попыток. После записи
/// в любом случае звучит следующая реплика — без «Дальше».
class SpeakCard extends DayCardWidget {
  const SpeakCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<SpeakCard> createState() => _SpeakCardState();
}

class _SpeakCardState extends State<SpeakCard> {
  late final SpeechAttemptController _mic;
  int _hint = 0;
  bool _skipped = false;
  bool _done = false;
  final _scroll = ScrollController();
  Timer? _leave;

  /// Собеседник начинает обмен — его реплика звучит до хода; иначе он отвечает после.
  bool get _partnerFirst => widget.card.initiator != 'B';

  @override
  void initState() {
    super.initState();
    _mic = widget.context.speech(widget.card)..addListener(_onMic);
    widget.context.voice.speaking.addListener(_onSpeaking);
    if (!_partnerFirst) _mic.waitForPartner(false);
    WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToEnd());
  }

  void _onMic() {
    if (mounted) setState(() {});
  }

  void _onSpeaking() {
    _mic.waitForPartner(widget.context.voice.speaking.value);
  }

  void _scrollToEnd() {
    if (!mounted || !_scroll.hasClients) return;
    final end = _scroll.position.maxScrollExtent;
    if (end > _scroll.offset) _scroll.animateTo(end, duration: AppMotion.swipeReturn, curve: Curves.easeOut);
  }

  @override
  void dispose() {
    _leave?.cancel();
    widget.context.voice.speaking.removeListener(_onSpeaking);
    _mic.removeListener(_onMic);
    _mic.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _tap() async {
    final outcome = await _mic.tap();
    if (!mounted || outcome != SpeechAttemptOutcome.accepted) return;
    await _finish(DayRules.spokenResult(hintLevel: _hint), spoken: _mic.transcript);
  }

  Future<void> _skip() async {
    setState(() => _skipped = true);
    await _finish(DayCardResult.skipped);
  }

  /// Записали — ответ на сервер, звук, и сразу следующая реплика: без «Дальше».
  Future<void> _finish(DayCardResult result, {String? spoken}) async {
    setState(() => _done = true);
    if (result != DayCardResult.skipped) AppFeedback.correct();
    await widget.context.session.answer(widget.card, result, attempts: _mic.attempts + (spoken == null ? 0 : 1), spokenText: spoken);
    if (!mounted) return;
    // Ученик начинал — теперь отвечает врач, и его реплика должна прозвучать здесь.
    if (!_partnerFirst && widget.card.partner != null) {
      await widget.context.voice.speakLine(widget.card.partner!.textTarget);
    } else {
      await Future<void>.delayed(AppMotion.spokenAutoLeave);
    }
    if (mounted) widget.onNext();
  }

  void _showHint() {
    if (_hint >= 2 || _done) return;
    setState(() => _hint++);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final partner = card.partner;
    final role = widget.context.roleOf(partner);
    final caption = switch (_mic.state) {
      RecordState.listening => l.daySayListening,
      RecordState.thinking => l.daySayThinking,
      RecordState.heard => l.daySayHeardShort,
      _ => l.daySayMic,
    };

    final past = widget.context.session.cardsOf(DayStage.speak).where((c) => c.isAnswered && c.id != card.id).toList();
    final spoken = widget.context.session.spoken;

    return Column(
      children: [
        Expanded(
          child: ListView(
            controller: _scroll,
            // Без ленты группа стоит по центру своей зоны (2б); с лентой — прижата к её низу.
            shrinkWrap: past.isEmpty,
            padding: EdgeInsets.fromLTRB(AppSpacing.screenH, past.isEmpty ? 60 : 14, AppSpacing.screenH, 12),
            children: [
              for (final c in past) ...[
                if (c.initiator != 'B' && c.partner != null) _PartnerBubble(text: c.partner!.textTarget, onPlay: () => unawaited(widget.context.voice.speakLine(c.partner!.textTarget))),
                _OwnBubble(text: spoken[c.id] ?? c.expected, dimmed: spoken[c.id] == null),
                if (c.initiator == 'B' && c.partner != null) _PartnerBubble(text: c.partner!.textTarget, onPlay: () => unawaited(widget.context.voice.speakLine(c.partner!.textTarget))),
                const SizedBox(height: 8),
              ],
              if (partner != null && _partnerFirst)
                PartnerLineCard(
                  label: l.daySpeakerSays(role),
                  text: partner.textTarget,
                  translation: partner.textNative,
                  voice: widget.context.voice,
                  revealed: false,
                ),
              const SizedBox(height: 16),
              TaskBlock(
                label: l.dayTaskSay(DayTexts.adverb(l, widget.context.targetLang)),
                text: '«${card.taskNative ?? ''}»',
              ),
              if (_hint == 1 && (card.hintKey ?? '').isNotEmpty) ...[
                const SizedBox(height: 14),
                Center(child: Text.rich(phraseWithKey(card.hintKey!, card.hintKey), style: AppTextDay.hintKey.copyWith(decoration: TextDecoration.none))),
              ],
              if (_hint >= 2) ...[
                const SizedBox(height: 14),
                Center(child: Text(card.hintText ?? card.expected, textAlign: TextAlign.center, style: AppTextDay.hintText)),
              ],
              if (_skipped) ...[
                const SizedBox(height: 14),
                Text(l.dayReturnDay(widget.context.returnDay), textAlign: TextAlign.center, style: AppTextDay.returnNote),
              ] else if (_done && _hint >= 2) ...[
                const SizedBox(height: 14),
                Text(l.daySpeakHinted(widget.context.returnDay), textAlign: TextAlign.center, style: AppTextDay.hintedNote),
              ] else if (!_done && _mic.attempts > 0 && _mic.state == RecordState.idle) ...[
                const SizedBox(height: 14),
                Text(l.daySayRetry, textAlign: TextAlign.center, style: AppTextDay.retry),
              ],
              if (_done && !_partnerFirst && partner != null) ...[
                const SizedBox(height: 16),
                PartnerLineCard(
                  label: l.daySpeakerAnswers(role),
                  text: partner.textTarget,
                  translation: partner.textNative,
                  voice: widget.context.voice,
                  revealed: true,
                  autoplay: false,
                ),
              ],
            ],
          ),
        ),
        Padding(
          padding: EdgeInsets.fromLTRB(AppSpacing.screenH, 0, AppSpacing.screenH, MediaQuery.paddingOf(context).bottom + 12),
          child: Column(
            children: [
              RecordButton(state: _mic.state, caption: caption, level: _mic.level, onTap: () => unawaited(_tap())),
              const SizedBox(height: 8),
              if (!_done)
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (_hint < 2)
                      TextButton(onPressed: _showHint, child: Text(l.daySpeakHint, style: AppTextDay.quiet)),
                    if (_mic.canSkip) ...[
                      if (_hint < 2) const SizedBox(width: 16),
                      TextButton(onPressed: _skip, child: Text(l.daySaySkip, style: AppTextDay.quiet)),
                    ],
                  ],
                ),
              if (_mic.micUnavailable && _mic.probe != null && _mic.probe!.blockedInSettings)
                SpeechVerdictLine(controller: _mic),
              if (!_done) QaSpeechRow(controller: _mic),
            ],
          ),
        ),
      ],
    );
  }
}

class _PartnerBubble extends StatelessWidget {
  const _PartnerBubble({required this.text, required this.onPlay});
  final String text;
  final VoidCallback onPlay;

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.centerLeft,
    child: Container(
      constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.86 - AppSpacing.screenH),
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(14, 12, 44, 12),
      decoration: const BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16), bottomRight: Radius.circular(16), bottomLeft: Radius.circular(4)),
        boxShadow: AppShadows.card,
      ),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Text(text, style: AppTextDay.bubble),
          Positioned(right: -34, top: -2, child: PlayCircle(size: 28, onTap: onPlay)),
        ],
      ),
    ),
  );
}

class _OwnBubble extends StatelessWidget {
  const _OwnBubble({required this.text, required this.dimmed});
  final String text;
  final bool dimmed;

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.centerRight,
    child: Opacity(
      opacity: dimmed ? 0.6 : 1,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.86 - AppSpacing.screenH),
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        decoration: const BoxDecoration(
          color: AppColors.ink,
          borderRadius: BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16), bottomLeft: Radius.circular(16), bottomRight: Radius.circular(4)),
        ),
        child: Text(text, style: AppTextDay.bubbleOwn.copyWith(fontSize: 16)),
      ),
    ),
  );
}
