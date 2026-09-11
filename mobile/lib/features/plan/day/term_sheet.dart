import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/plan_contract.dart';
import '../../../data/pronouncer.dart';
import 'cards/phrase_cards.dart' show phraseWithKey;
import 'day_texts.dart';

/// Состояние термина в дне для строки шита — «сдал · день 2» / «с подсказкой» / «вернётся в
/// день 3» / «из дня 1» (ещё не пройден).
enum SheetTermState { pending, passed, hinted, returns }

/// ШИТ ТЕРМИНА (кадры 23-14 / 23-15; «термин в шите» из «Базы»): bottom sheet без кнопок — слово
/// Literata 28 с воспроизведением 44, перевод 16, чтение 15, определение по-английски
/// (Intermediate) Literata italic 15, фото 96×70 и пример с переводом, строка состояния в дне
/// цветом вердикта. Фраза — Literata 26 с подчёркнутым ключом и «В разговоре» (Врач → Ты).
Future<void> showTermSheet(
  BuildContext context, {
  required DayTerm term,
  required SheetTermState state,
  required int dayNumber,
  required int returnDay,
  required String targetLang,
  required String partnerRole,
  String? inTalkPartner,
  String? inTalkOwn,
  int? fromDay,
}) {
  return showAppBottomSheet<void>(
    context: context,
    builder: (_) => _TermSheet(
      term: term,
      state: state,
      dayNumber: dayNumber,
      returnDay: returnDay,
      targetLang: targetLang,
      partnerRole: partnerRole,
      inTalkPartner: inTalkPartner,
      inTalkOwn: inTalkOwn,
      fromDay: fromDay,
    ),
  );
}

class _TermSheet extends StatefulWidget {
  const _TermSheet({
    required this.term,
    required this.state,
    required this.dayNumber,
    required this.returnDay,
    required this.targetLang,
    required this.partnerRole,
    this.inTalkPartner,
    this.inTalkOwn,
    this.fromDay,
  });

  final DayTerm term;
  final SheetTermState state;
  final int dayNumber;
  final int returnDay;
  final String targetLang;
  final String partnerRole;
  final String? inTalkPartner;
  final String? inTalkOwn;
  final int? fromDay;

  @override
  State<_TermSheet> createState() => _TermSheetState();
}

class _TermSheetState extends State<_TermSheet> {
  late final Pronouncer _voice = Pronouncer();

  @override
  void dispose() {
    unawaited(_voice.release());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final t = widget.term;
    final isPhrase = t.kind == 'phrase';
    final image = t.image?.url;
    final example = t.exampleTarget?.trim() ?? '';
    final (stateText, stateColor) = switch (widget.state) {
      SheetTermState.passed => (l.daySheetStatePassed(widget.dayNumber), AppColors.verdictKnown),
      SheetTermState.hinted => (l.daySheetStateHinted(widget.dayNumber), AppColors.verdictUnsure),
      SheetTermState.returns => (l.daySheetStateReturns(widget.returnDay), AppColors.destructiveText),
      SheetTermState.pending => (widget.fromDay == null ? null : l.dayFromDay(widget.fromDay!), AppColors.brassInk),
    };

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
                child: isPhrase
                    ? Text.rich(phraseWithKey(t.textTarget, t.speakingKey), style: AppTextDay.phrase)
                    : Text(t.textTarget, style: AppTextDay.sheetWord),
              ),
              const SizedBox(width: 14),
              PlayCircle(onTap: () => unawaited(_voice.speakText(t.textTarget, targetLang: widget.targetLang))),
            ],
          ),
          const SizedBox(height: 10),
          Text(t.textNative, style: AppTextDay.introTranslation.copyWith(fontSize: 16)),
          if (DayTexts.reading(t.pronunciationNative) case final r?) ...[
            const SizedBox(height: 6),
            Text(r, style: AppTextDay.introReading.copyWith(fontSize: 15)),
          ],
          if (t.definitionTarget case final d? when d.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(d, style: AppTextDay.sheetDefinition),
          ],
          if (!isPhrase && (image != null || example.isNotEmpty)) ...[
            const SizedBox(height: 16),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (image != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: SizedBox(
                      width: 96,
                      height: 70,
                      child: ColoredBox(
                        color: AppColors.photoSlot,
                        child: Image(image: CachedNetworkImage(image), fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                ],
                if (example.isNotEmpty)
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(example, style: AppTextDay.example),
                        if (t.exampleNative case final tr? when tr.trim().isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Text(tr, style: AppTextDay.exampleTranslation),
                        ],
                      ],
                    ),
                  ),
              ],
            ),
          ],
          if (isPhrase && (widget.inTalkPartner ?? '').isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(l.daySheetInTalk.toUpperCase(), style: AppTextDay.sectionLabel),
            const SizedBox(height: 8),
            Text.rich(
              TextSpan(
                children: [
                  TextSpan(text: '${widget.partnerRole}: ', style: AppTextDay.brassNote.copyWith(fontWeight: FontWeight.w600)),
                  TextSpan(text: widget.inTalkPartner),
                ],
              ),
              style: AppTextDay.exchangePartner.copyWith(height: 1.4),
            ),
            const SizedBox(height: 4),
            Text.rich(
              TextSpan(
                children: [
                  TextSpan(text: '${l.daySheetRoleYou} ', style: AppTextDay.brassNote.copyWith(fontWeight: FontWeight.w600)),
                  TextSpan(text: widget.inTalkOwn ?? t.textTarget),
                ],
              ),
              style: AppTextDay.exchangeOwn.copyWith(height: 1.4),
            ),
          ],
          if (stateText != null) ...[
            const SizedBox(height: 16),
            Row(
              children: [
                Container(width: 8, height: 8, decoration: BoxDecoration(shape: BoxShape.circle, color: stateColor)),
                const SizedBox(width: 8),
                Text(stateText, style: AppTextDay.sheetState.copyWith(color: stateColor)),
              ],
            ),
          ],
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}
