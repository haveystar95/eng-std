import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/plan_models.dart';
import '../../data/providers.dart';
import '../training/session/session_grading.dart' show LocalCheck;
import 'plan_day_stages.dart';
import 'plan_ui.dart';

/// ИТОГ ПОСАДКИ — три вердикта одной вёрсткой (серия «День v1», кадры D·06, D·06б, D·06в).
///
/// The question this screen answers is «справлюсь ли я в этой сцене», never «сколько слов я выучил»
/// (канон §13). So the headline is the scene, the body is what went well and what did not — by name,
/// with what happens to it next — and the numbers underneath are the stages, in the words a person
/// uses: познакомился → применяешь → говоришь сам.
///
/// ## Three verdicts, one layout
///
///   **закрыт** — the server says the day is `done`. Brass над-title, the scene, the ladder, and the
///   next day.
///   **почти** — the sitting ended and the day did not close: named remainder, and a CHOICE, because
///   whether to finish now or meet them in tomorrow's warm-up is the learner's call and not ours
///   (кадр D·06б).
///   **прерван / ещё не известно** — a soft run, or the verdict still in flight. It says only what
///   is certainly true.
///
/// ## No percentage, because none is computed
///
/// «Пока процент не считается, его нет вообще» (кадр D·06в). Readiness for a scene is a server
/// number and the server does not compute one per scene yet; inventing one out of the stages here
/// would be the client answering a question the product has not answered.
class PlanDaySummary extends ConsumerStatefulWidget {
  const PlanDaySummary({
    super.key,
    required this.envelope,
    required this.cards,
    required this.onDone,
    this.results = const [],
    this.onTrainMore,
  });

  /// The session's plan envelope — which plan, which day, and whether it counted.
  final PlanSessionEnvelope envelope;

  /// The cards actually played, so the summary can count phrases and words apart without a second
  /// request: the kind rides on every task already.
  final List<SessionCard> cards;

  /// HOW EACH ANSWER WENT — what «Далось» and «Не далось» are made of (кадр D·06).
  ///
  /// The sitting's own verdicts, not a second read: the screen already has them, and asking the
  /// server «which cards went wrong» would be a second opinion about an evening it did not watch.
  final List<({SessionCard card, LocalCheck verdict})> results;

  /// Open the day again and deal what is left — «Дотренировать» (кадр D·06б). Null when there is no
  /// way back (the run-through), and then the choice is not offered.
  final VoidCallback? onTrainMore;

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
    //
    // …AFTER THE ANSWERS HAVE LANDED. The verdict is derived from the reviews, and the last of
    // them is still on its way up when this screen opens: read back too early, the plan is the
    // plan as it stood before the sitting, and the summary said «почти» over a day the server
    // had already closed (живой день 2, 07.09). Until then the headline says only what is
    // certainly true — the sitting is over.
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await ref.read(reviewSyncProvider).settled();
      if (!mounted) return;
      ref.invalidate(planProvider(widget.envelope.planId));
      setState(() => _answersLanded = true);
    });
  }

  /// The reviews of this sitting have been offered to the server; the plan read after that point
  /// is the one whose verdict may be shown.
  bool _answersLanded = false;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final envelope = widget.envelope;
    final cards = widget.cards;
    // THE DAY'S VERDICT IS THE SERVER'S, AND IT IS ASKED FOR — never assumed from «the sitting was
    // strict». A strict sitting that ends is not a day that passed: a miss does not close its rung,
    // so a day with one wrong card is still `ready` and the plan still says «Продолжить». The
    // screen said «День 1 пройден» over exactly that, on the owner's phone, 02.09.
    //
    // `isLoading` matters: `invalidate` above keeps the PREVIOUS value while the re-read is in
    // flight, and the previous value is the plan as it was BEFORE the sitting — which would call
    // every finished day unclosed for half a second. So the verdict waits for a fresh read, and
    // until it lands the headline says only what is certainly true ([planDaySittingDone]).
    final planAsync = ref.watch(planProvider(envelope.planId));
    final plan = planAsync.value;
    final fresh = planAsync.isLoading ? null : planAsync.value;
    // THE SAME WORD AS THE PLAN TAB (наряд DAY-FIX-2, Ч.3): `day_state` is the census the tab, the
    // day screen and the sitting read; the row's `status` turns `done` only when `POST /complete`
    // lands, and between the two the summary said «почти» over a day the tab already called
    // «пройден» (живой прогон 06.09).
    final freshDay = fresh?.dayAt(envelope.dayIndex);
    final dayPassed =
        freshDay?.dayState == PlanDayState.done || freshDay?.status == PlanDayStatus.done;
    final verdictKnown = _answersLanded && fresh != null;

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

    // The breakdown by `kind` («3 слова · 2 связки · 8 фраз») is GONE from this screen, not moved.
    // It was a receipt, and an итог that answers «справлюсь ли я в этой сцене» has no line for one
    // (канон §13). What the day's terms are still counted for is the ladder's first rung: how many
    // things this sitting introduced.

    // «ДАЛЬШЕ» ЗОВЁТ В ТО, ЧТО ДЕЙСТВИТЕЛЬНО ДАЛЬШЕ (наряд DAY-GATE-1, доработка, п. 2), и пока
    // вердикт не известен, не зовёт никуда: строка, напечатанная по плану ДО присеста, назвала бы
    // следующим то, что человек только что прошёл.
    final nextIndex = plan?.nextDayIndex;
    final nextDay = nextIndex == null || nextIndex <= envelope.dayIndex
        ? null
        : plan?.dayAt(nextIndex);
    final nextTitle = verdictKnown
        ? planSittingNextTitle(l, day: freshDay, nextDay: nextDay, dayPassed: dayPassed)
        : null;
    final scene = plan?.dayAt(envelope.dayIndex)?.title ?? '';

    // WHAT WENT WELL AND WHAT DID NOT, by card and once each. The LAST verdict of a term wins: a
    // card missed and then met again at the end of the присест (Ч-5) is a card that went well in the
    // end, and listing it under «Не далось» would be the app remembering a moment the learner has
    // already moved past.
    final verdicts = <String, ({SessionCard card, bool ok})>{};
    for (final result in widget.results) {
      verdicts[result.card.termId] = (card: result.card, ok: result.verdict.isAccepted);
    }
    final missed = verdicts.values.where((v) => !v.ok).toList(growable: false);
    final gotIt = verdicts.values.where((v) => v.ok).toList(growable: false);

    // «Почти» — the sitting ended and the day did not close. It is a VERDICT and not an error: the
    // remainder is named, and finishing it now or meeting it in tomorrow's warm-up is a choice.
    final almost = envelope.strict && verdictKnown && !dayPassed;

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
          // THE OVER-TITLE CARRIES THE VERDICT and nothing else does: brass when the day is closed,
          // grey when it is not (записка «Итог дня»). The word «День» is allowed here and only here
          // — this IS today's sitting, which is the one place the schedule's word belongs.
          Center(
            child: PlanLabel(
              switch ((envelope.strict, verdictKnown, dayPassed)) {
                (false, _, _) => l.planDaySoftDone(envelope.dayIndex),
                (true, false, _) => l.planDaySittingDone,
                (true, true, true) => l.planDayDone(envelope.dayIndex),
                (true, true, false) => l.planDayAlmost(envelope.dayIndex),
              },
              color: dayPassed ? AppColors.brassInk : AppColors.tertiary,
            ),
          ),
          const SizedBox(height: 14),
          // THE SCENE, and the scene is the headline. «Справлюсь ли я в регистратуре» is the
          // question the day was for; «9 фраз и слов в работе» answered a different one, and it is
          // the sentence канон §13 names as the wrong sentence.
          Text(
            scene.isEmpty ? (plan?.title ?? '') : l.planSceneNamed(scene),
            textAlign: TextAlign.center,
            style: AppText.displayTerm.copyWith(fontSize: 30, height: 1.24),
          ),
          if (almost) ...[
            const SizedBox(height: 12),
            Text(
              missed.isEmpty
                  ? l.planDayNotClosedNote
                  : l.planDayAlmostLead(missed.length),
              textAlign: TextAlign.center,
              style: AppText.collectionNameCard.copyWith(fontSize: 17, color: AppColors.inkBody),
            ),
          ],
          const SizedBox(height: 28),
          // ЧТО НЕ ДАЛОСЬ — first, and by name: these are the cards that come back tomorrow, and a
          // list of them is the one thing on this screen the learner can act on.
          if (missed.isNotEmpty) ...[
            PlanLabel(l.planDayMissed, color: AppColors.verdictUnknown),
            const SizedBox(height: 8),
            for (final entry in missed.take(5)) _CardLine(text: entry.card.answerText),
            const SizedBox(height: 8),
            Text(
              l.planDayMissedNote,
              style: AppText.translation.copyWith(
                fontSize: 13,
                height: 1.5,
                color: AppColors.tertiary,
              ),
            ),
            const SizedBox(height: AppSpacing.s22),
          ],
          if (gotIt.isNotEmpty) ...[
            PlanLabel(l.planDayGotIt),
            const SizedBox(height: 8),
            for (final entry in gotIt.take(4)) _CardLine(text: entry.card.answerText),
            const SizedBox(height: AppSpacing.s22),
          ],
          // THE LADDER, in the words a person uses — no percentage and no «N из M» (кадр D·06в,
          // наряд DAY-FIX-2, Ч.5.6): what this sitting met, and whether the plan's material has
          // all been met yet.
          PlanLabel(l.planLadderLegend),
          const SizedBox(height: 8),
          _LadderRow(
            name: l.planLadderA,
            value: byTerm.isEmpty ? '' : l.planSummaryMetToday,
          ),
          if (plan != null)
            _LadderRow(
              name: l.planLadderB,
              value: plan.stageAClosed >= plan.cardsTotal
                  ? l.planSummaryAppliedAll
                  : l.planSummaryAppliedSome,
            ),
          _LadderRow(name: l.planLadderC, value: ''),
          const SizedBox(height: 10),
          Text(
            l.planLadderNoReadiness,
            style: AppText.translation.copyWith(
              fontSize: 13,
              height: 1.5,
              color: AppColors.tertiary,
            ),
          ),
          if (reviewTerms.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s16),
            _SummaryRow(label: l.planReviewRow, value: l.planReviewCount(reviewTerms.length)),
          ],
          const SizedBox(height: AppSpacing.s22),
          if (nextTitle != null) _NextRow(caption: l.planNext, title: nextTitle),
          const SizedBox(height: AppSpacing.s26),
          // THE CHOICE, and only on «почти»: finish the remainder now, or meet it in tomorrow's
          // warm-up. Both are named before either is pressed, so «оставить» is a decision and not a
          // thing that happens by walking away.
          if (almost && widget.onTrainMore != null) ...[
            // The number that used to ride the button is gone (DAY-FIX-2): the cards are named
            // above, and a plan screen does not count out loud.
            PrimaryButton(
              label: l.planDayTrainMore,
              minHeight: 52,
              onPressed: widget.onTrainMore!,
            ),
            const SizedBox(height: AppSpacing.s12),
            QuietButton(label: l.planDayLeaveForTomorrow, onPressed: widget.onDone),
            const SizedBox(height: 10),
            Text(
              l.planDayLeaveNote,
              textAlign: TextAlign.center,
              style: AppText.translation.copyWith(
                fontSize: 13,
                height: 1.5,
                color: AppColors.tertiary,
              ),
            ),
          ] else
            PrimaryButton(label: l.planDayBackToPlan, minHeight: 52, onPressed: widget.onDone),
        ],
      ),
    );
  }
}

/// One card named on the итог — «My child has a fever.», nothing around it.
class _CardLine extends StatelessWidget {
  const _CardLine({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
    ),
    alignment: Alignment.centerLeft,
    child: Text(text, style: AppText.termInList, maxLines: 2, overflow: TextOverflow.ellipsis),
  );
}

/// «Познакомился · 14» — одна ступень зрелости, СЛОВОМ, без буквы.
///
/// Кадр D·06в ставит расшифровку рядом с лестницей, а не в справке, и она здесь и осталась. Ушла
/// только латунная буква: A/B/C — внутреннее имя механики, и на экранах продукта его нет нигде
/// (наряд DAY-2-FIX, Ч.3а). Слово «познакомился» человек понимает без легенды, буква «A» — нет.
class _LadderRow extends StatelessWidget {
  const _LadderRow({required this.name, required this.value});

  final String name, value;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: AppSpacing.minTap),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Text(
            name,
            style: AppText.translation.copyWith(fontSize: 15, color: AppColors.inkBody),
          ),
        ),
        if (value.isNotEmpty)
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

// «РАЗГОВОР — СКОРО» IS GONE FROM THIS SCREEN, and it is gone rather than hidden.
//
// It was a locked dark plate promising the conversation the plan is for. The conversation exists
// now — it is the scene's own dialogue, played inside the sitting (наряд DAY-2, Ч.3) — so a plate
// saying «скоро» over an итог would be the app promising something the learner has just done.

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
