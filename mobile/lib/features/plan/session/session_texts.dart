import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/plan/session/session_summary.dart';

/// SESSION WORDING — choosing the ARB string by stage, level and count. Not a single word here, only the choice.
abstract final class SessionTexts {
  static String stage(AppLocalizations l, PlanStage s) => switch (s) {
    PlanStage.words => l.planPlateStageWords,
    PlanStage.phrases => l.planPlateStagePhrases,
    PlanStage.dialogue => l.planPlateStageDialog,
    PlanStage.listen => l.planPlateStageListen,
    PlanStage.speak || PlanStage.unknown => l.planPlateStageSpeak,
  };

  /// The stage description on entry (30-1); [units] — the stage's units.
  static String description(AppLocalizations l, PlanStage s, int units) => switch (s) {
    PlanStage.words => l.planSessionDescWords(units),
    PlanStage.phrases => l.planSessionDescPhrases(units),
    PlanStage.dialogue => l.planSessionDescDialogue,
    PlanStage.listen => l.planSessionDescListen,
    PlanStage.speak || PlanStage.unknown => l.planSessionDescSpeak,
  };

  /// «4 words left» — on the right of the header (30-2); the dialogue and speaking count exchanges («lines»).
  static String left(AppLocalizations l, PlanStage s, int units) => switch (s) {
    PlanStage.phrases => l.planSessionLeftPhrases(units),
    PlanStage.dialogue || PlanStage.speak => l.planSessionLeftExchanges(units),
    _ => l.planSessionLeftWords(units),
  };

  /// The header's right side in «Listen and answer» (34-x) — its unit is the whole visit, so it names the card: «the
  /// whole conversation», «review», «pace», or the questions left, the current one included («last question»).
  static String listenLeft(AppLocalizations l, SessionKind current, Iterable<SessionCard> stageCards) {
    switch (current) {
      case SessionKind.listenDialogue:
        return l.planSessionWholeTalk;
      case SessionKind.listenReview:
        return l.planSessionReviewLabel;
      case SessionKind.listenPace:
        return l.planSessionPaceLabel;
      default:
        final left = stageCards.where((c) => SessionSummaries.listenQuestions.contains(c.kind) && !c.isAnswered).length;
        return left <= 1 ? l.planSessionLastQuestion : l.planSessionLeftQuestions(left);
    }
  }

  /// «Words done · 6 minutes» (30-6), «Dialogue done · 7 minutes» (33-8).
  static String done(AppLocalizations l, PlanStage s, int minutes) {
    final m = l.planMinutesCount(minutes);
    return switch (s) {
      PlanStage.phrases => l.planSessionDonePhrases(m),
      PlanStage.dialogue => l.planSessionDoneDialogue(m),
      _ => l.planSessionDoneWords(m),
    };
  }

  /// «The other 6 words are done.» / «All 8 words done.» (30-6); the exchanges — without a count (33-8, 35-6).
  static String closed(AppLocalizations l, PlanStage s, {required int closed, required bool someReturn}) =>
      switch ((s, someReturn)) {
        (PlanStage.phrases, true) => l.planSessionRestPhrases(closed),
        (PlanStage.phrases, false) => l.planSessionAllPhrases(closed),
        (PlanStage.dialogue || PlanStage.speak, true) => l.planSessionRestExchanges,
        (PlanStage.dialogue || PlanStage.speak, false) => l.planSessionAllExchanges,
        (_, true) => l.planSessionRestWords(closed),
        (_, false) => l.planSessionAllWords(closed),
      };

  /// «5 cards: 2 words, 2 phrases and 1 line.» (30-7) — null when nothing comes back.
  static String? dayReturns(AppLocalizations l, DayReturns r) {
    final parts = [
      if (r.words > 0) l.planSessionReturnWords(r.words),
      if (r.phrases > 0) l.planSessionReturnPhrases(r.phrases),
      if (r.exchanges > 0) l.planSessionReturnExchanges(r.exchanges),
    ];
    if (parts.isEmpty) return null;
    final joined = parts.length == 1
        ? parts.single
        : l.planSessionAnd(parts.sublist(0, parts.length - 1).join(', '), parts.last);
    return l.planSessionDayReturns(l.planCardsCount(r.words + r.phrases + r.exchanges), joined);
  }

  /// The role in the companion line — with a lowercase first letter («receptionist will understand»); no declension.
  static String roleInline(String role) => role.isEmpty ? role : role[0].toLowerCase() + role.substring(1);
}
