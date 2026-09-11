import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_contract.dart';
import '../day_card_frame.dart';
import 'card_context.dart';

/// ДИАЛОГ (кадры 23-6a/b): «Весь разговор» — лента реплик; лейблы «ВРАЧ» / «ТЫ» латунью один раз
/// на сторону; врач — бумага с воспроизведением 28, ты — плита ink. Beginner — переводы открыты;
/// Intermediate — свёрнуты в строку «перевод», тап раскрывает на месте за 200 мс. «Понятно» на доке.
class DialogueReadCard extends DayCardWidget {
  const DialogueReadCard({super.key, required super.card, required super.context, required super.onNext});

  @override
  State<DialogueReadCard> createState() => _DialogueReadCardState();
}

class _DialogueReadCardState extends State<DialogueReadCard> {
  final Set<String> _revealed = {};

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final collapsed = card.translationsCollapsed;
    var partnerLabelled = false, youLabelled = false;
    final bubbles = <Widget>[];
    for (final x in card.exchanges) {
      for (final m in x.messages) {
        final key = '${x.step}:${m.speaker}';
        final showLabel = m.isPartner ? !partnerLabelled : !youLabelled;
        if (m.isPartner) {
          partnerLabelled = true;
        } else {
          youLabelled = true;
        }
        bubbles.add(
          _Bubble(
            message: m,
            label: showLabel ? (m.isPartner ? widget.context.roleOf(m) : l.dayDialogRoleYou) : null,
            collapsed: collapsed && !_revealed.contains(key),
            onReveal: () => setState(() => _revealed.add(key)),
            onPlay: m.isPartner ? () => unawaited(widget.context.voice.speakLine(m.textTarget)) : null,
          ),
        );
      }
    }

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, 100),
            children: [
              Text(l.dayDialogTitle, style: AppTextDay.dialogueTitle),
              const SizedBox(height: 6),
              Text(l.dayDialogSub, style: AppTextDay.facts),
              const SizedBox(height: 18),
              for (var i = 0; i < bubbles.length; i++) ...[
                if (i > 0) const SizedBox(height: 10),
                bubbles[i],
              ],
            ],
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

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.collapsed, required this.onReveal, this.label, this.onPlay});
  final DayMessage message;
  final String? label;
  final bool collapsed;
  final VoidCallback onReveal;
  final VoidCallback? onPlay;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.of(context).disableAnimations;
    final partner = message.isPartner;
    final radius = partner
        ? const BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16), bottomRight: Radius.circular(16), bottomLeft: Radius.circular(4))
        : const BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16), bottomLeft: Radius.circular(16), bottomRight: Radius.circular(4));

    final translation = AnimatedSize(
      // Не Duration.zero: RenderAnimatedSize с нулевой длительностью перекладывается внутри
      // собственного layout и падает; миллисекунда неотличима от «сразу».
      duration: reduce ? const Duration(milliseconds: 1) : AppMotion.translationReveal,
      curve: AppMotion.easeOut,
      alignment: Alignment.topLeft,
      child: collapsed
          ? GestureDetector(
              onTap: onReveal,
              behavior: HitTestBehavior.opaque,
              child: Text(
                l.dayDialogTranslate,
                style: AppTextDay.bubbleTranslation.copyWith(color: partner ? AppColors.tertiary : AppColors.paper50),
              ),
            )
          : Text(
              message.textNative,
              style: AppTextDay.bubbleTranslation.copyWith(color: partner ? AppColors.secondary : AppColors.paper72),
            ),
    );

    final body = Container(
      constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.86 - AppSpacing.screenH),
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: partner ? AppColors.surfaceRaised : AppColors.ink,
        borderRadius: radius,
        boxShadow: partner ? AppShadows.card : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: EdgeInsets.only(right: partner ? 32 : 0),
            child: Text(message.textTarget, style: partner ? AppTextDay.bubble : AppTextDay.bubbleOwn),
          ),
          const SizedBox(height: 5),
          translation,
        ],
      ),
    );

    return Column(
      crossAxisAlignment: partner ? CrossAxisAlignment.start : CrossAxisAlignment.end,
      children: [
        if (label case final t?)
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 0, 4, 6),
            child: Text(t.toUpperCase(), style: AppTextDay.speakerLabel),
          ),
        Stack(
          children: [
            body,
            if (onPlay != null)
              Positioned(
                right: 10,
                top: 10,
                child: PlayCircle(size: 28, onTap: onPlay!),
              ),
          ],
        ),
      ],
    );
  }
}
