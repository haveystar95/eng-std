import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/providers.dart';
import 'plan_ui.dart';

/// «День N пройден» — кадр 1c · 04.
///
/// The wording is «в работе», never «выучено», and the two rows under it say why: stage A is what a
/// SITTING can close, and stages B and C need nights. A summary that said «9 слов выучено» would be
/// claiming something the ladder explicitly does not claim — and the learner would find out it was
/// untrue at the appointment.
///
/// The «Дальше» line is a NEXT DAY and not a «завтра»: the plan's step may be one, two or three
/// calendar days, and naming the day rather than the date is the only version of that sentence that
/// is true for all three.
class PlanDaySummary extends ConsumerStatefulWidget {
  const PlanDaySummary({
    super.key,
    required this.envelope,
    required this.cards,
    required this.onDone,
  });

  /// The session's plan envelope — which plan, which day, and whether it counted.
  final PlanSessionEnvelope envelope;

  /// The cards actually played, so the summary can count phrases and words apart without a second
  /// request: the kind rides on every task already.
  final List<SessionCard> cards;

  final VoidCallback onDone;

  @override
  ConsumerState<PlanDaySummary> createState() => _PlanDaySummaryState();
}

class _PlanDaySummaryState extends ConsumerState<PlanDaySummary> {
  @override
  void initState() {
    super.initState();
    // The run was CLOSED when the last card was answered ({@see _SessionShellState._closeRun}) —
    // not here, and not in the ordinary summary either. Both used to hold their own copy of that
    // call, which is exactly how the plan's summary came to be the one without it: 103 answers in
    // the live run and not one `POST /study/sessions/{id}/complete`, so the day stayed `ready` and
    // day n+1 was never queued (Д-1, Д-28).
    //
    // What is still this screen's business is READING THE PLAN BACK, so the day is struck through
    // and the next one says «Собирается» without the learner having to leave and come back.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) ref.invalidate(planProvider(widget.envelope.planId));
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final envelope = widget.envelope;
    final cards = widget.cards;
    final plan = ref.watch(planProvider(envelope.planId)).value;

    // Distinct TERMS, not cards: one word arrives as three cards inside a stage, and «9 фраз и слов»
    // must be nine things and not twenty-seven questions.
    //
    // And the DAY's terms apart from the top-up. A plan session deals the day and then tops the
    // sitting up from the learner's ordinary queue; both were counted as the day here, so a day of
    // fourteen was announced as «21 фраза и слово» — seven of them out of another plan and, on the
    // account this was found on, another language. The seam is the server's answer now
    // ([PlanSessionEnvelope.isDayTaskAt]), not this screen's guess.
    final byTerm = <String, String?>{};
    final reviewTerms = <String>{};
    for (var i = 0; i < cards.length; i++) {
      final card = cards[i];
      if (envelope.isDayTaskAt(i)) {
        // THE KIND, not the card. What a term IS in its day is the only thing counted below.
        byTerm.putIfAbsent(card.termId, () => envelope.kindAt(i));
      } else if (!byTerm.containsKey(card.termId)) {
        // A term the day never introduced. Counted once, and never as the day's — a word that is
        // BOTH (dealt for its day and due again) belongs to the day, which is why the check reads
        // the day map first.
        reviewTerms.add(card.termId);
      }
    }

    // COUNTED BY `kind`, WHICH THE SERVER SENDS — never by how many words are in the text.
    //
    // Three kinds and three words for them: a `word` is «слово», a `chunk` is «связка», a `line` is
    // «фраза». The old count had two buckets and filled them by length, so a day of 4 word +
    // 2 chunk + 8 line was announced as «3 слова · 11 фраз» and the connector «five» was labelled a
    // phrase in the session itself (Д-5). A term with no kind at all is not from a plan day and
    // falls back to the lexical type, which is the only thing there is to go on.
    final counts = <String, int>{'word': 0, 'chunk': 0, 'line': 0};
    byTerm.forEach((termId, kind) {
      final key = kind ?? 'line';
      counts[key] = (counts[key] ?? 0) + 1;
    });
    final words = counts['word'] ?? 0;
    final chunks = counts['chunk'] ?? 0;
    final phrases = counts['line'] ?? 0;

    final nextIndex = plan?.nextDayIndex;
    final nextDay = nextIndex == null ? null : plan?.dayAt(nextIndex);

    return SafeArea(
      bottom: false,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenH,
          AppSpacing.s26,
          AppSpacing.screenH,
          AppSpacing.s26,
        ),
        children: [
          const SizedBox(height: 28),
          Center(child: PlanLabel(plan?.title ?? '')),
          const SizedBox(height: 14),
          Text(
            // A soft run closed nothing, so it does not get to say «пройден». It says what it was.
            envelope.strict
                ? l.planDayDone(envelope.dayIndex)
                : l.planDaySoftDone(envelope.dayIndex),
            textAlign: TextAlign.center,
            style: AppText.displayTerm.copyWith(fontSize: 34, height: 1.15),
          ),
          const SizedBox(height: 10),
          Text(
            l.planDayInWork(byTerm.length),
            textAlign: TextAlign.center,
            style: AppText.collectionNameCard.copyWith(
              fontSize: 17,
              color: AppColors.inkBody,
            ),
          ),
          const SizedBox(height: 30),
          if (envelope.strict) ...[
            const Divider(height: 1, thickness: 1, color: AppColors.hairline),
            _SummaryRow(
              label: l.planStageAClosed,
              value: [
                if (words > 0) l.planWordsCount(words),
                if (chunks > 0) l.planChunksCount(chunks),
                if (phrases > 0) l.planPhrasesCount(phrases),
              ].join(' · '),
            ),
            const Divider(height: 1, thickness: 1, color: AppColors.hairline),
            _SummaryRow(label: l.planStageBReturns, value: l.planStageBWhen),
            const Divider(height: 1, thickness: 1, color: AppColors.hairline),
            // The top-up, said out loud and on its own line. It was worth playing and it is not the
            // day: folding it into the count above is what made the day look bigger than it was.
            if (reviewTerms.isNotEmpty) ...[
              _SummaryRow(
                label: l.planReviewRow,
                value: l.planReviewCount(reviewTerms.length),
              ),
              const Divider(height: 1, thickness: 1, color: AppColors.hairline),
            ],
          ],
          const SizedBox(height: AppSpacing.s26),
          // The conversation is the day's main act and it is not built (CONV-1). A dark plate that
          // did nothing would be a button that lies, so it is drawn as what it is: the next thing,
          // named, with the reason it is not open yet.
          _LockedConversation(),
          const SizedBox(height: AppSpacing.s22),
          if (nextDay != null)
            _NextRow(
              caption: l.planNext,
              title: l.planNextDay(nextDay.index, nextDay.title),
            ),
          const SizedBox(height: AppSpacing.s26),
          PrimaryButton(label: l.planDayBackToPlan, minHeight: 52, onPressed: widget.onDone),
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value});
  final String label, value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: AppSpacing.s16),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: AppText.translation.copyWith(fontSize: 14.5, color: AppColors.inkBody),
          ),
        ),
        Text(
          value,
          style: AppText.translation.copyWith(
            fontSize: 12,
            color: AppColors.tertiary,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
      ],
    ),
  );
}

class _LockedConversation extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final paper = AppColors.paper;

    return PlanPlate(
      padding: const EdgeInsets.fromLTRB(22, 20, 22, 20),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                PlanLabel(l.planConversationLabel, color: AppColors.brass),
                const SizedBox(height: 6),
                Text(
                  l.planConversationSoon,
                  style: AppText.displayTerm.copyWith(color: paper, fontSize: 22, height: 1.2),
                ),
                const SizedBox(height: 6),
                Text(
                  l.planConversationLocked,
                  style: AppText.translation.copyWith(
                    fontSize: 13.5,
                    height: 1.5,
                    color: paper.withValues(alpha: 0.72),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.s12),
          Container(
            width: 44,
            height: 44,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: paper.withValues(alpha: 0.14),
            ),
            child: Icon(LucideIcons.lock, size: 17, color: paper.withValues(alpha: 0.7)),
          ),
        ],
      ),
    );
  }
}

class _NextRow extends StatelessWidget {
  const _NextRow({required this.caption, required this.title});
  final String caption, title;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.only(top: AppSpacing.s16),
    decoration: const BoxDecoration(
      border: Border(top: BorderSide(color: AppColors.dividerFaint)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                caption,
                style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
              ),
              const SizedBox(height: 3),
              Text(title, style: AppText.collectionNameCard.copyWith(fontSize: 18)),
            ],
          ),
        ),
        const Icon(LucideIcons.chevronRight, size: 16, color: AppColors.tertiary),
      ],
    ),
  );
}
