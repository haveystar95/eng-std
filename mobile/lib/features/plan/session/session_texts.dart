import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/plan/session/session_summary.dart';
import '../plan_stage_text.dart';

/// A line of a stage summary (30-6): its words, and whether it is the frame's softer third line (grey, not ink).
typedef StageLine = ({String text, bool soft});

/// SESSION WORDING — choosing the ARB string by stage, level and count. Not a single word here, only the choice.
abstract final class SessionTexts {
  /// One name per stage, the plan's own — `planStageName`.
  static String stage(AppLocalizations l, PlanStage s) => planStageName(l, s);

  /// The stage description on entry (30-1); [units] — the stage's units. The talk has an entry of
  /// its own (37-5) and never asks for this one.
  static String description(AppLocalizations l, PlanStage s, int units) => switch (s) {
    PlanStage.words => l.planSessionDescWords(units),
    PlanStage.phrases => l.planSessionDescPhrases(units),
    PlanStage.dialogue => l.planSessionDescDialogue,
    PlanStage.listen => l.planSessionDescListen,
    PlanStage.recall => l.planSessionDescRecall,
    // A review's stage is dealt the cards «Говорю сам» is dealt (`speak_answer` of the days it brings back), under its
    // own id since BACK-TAILS-2 — it asks the same thing of the learner.
    PlanStage.speak || PlanStage.repetition || PlanStage.conversation || PlanStage.unknown => l.planSessionDescSpeak,
  };

  /// «4 words left» — on the right of the header (30-2); the dialogue, speaking, a review and the rehearsal's
  /// «Вспомнить» count exchanges («lines», кадр 37-4: «ещё 5 реплик» … «ещё 1 реплика»).
  static String left(AppLocalizations l, PlanStage s, int units) => switch (s) {
    PlanStage.phrases => l.planSessionLeftPhrases(units),
    PlanStage.dialogue || PlanStage.speak || PlanStage.repetition || PlanStage.recall => l.planSessionLeftExchanges(units),
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

  /// THE TITLE OF A STAGE SUMMARY (30-6, решение архитектора 22.09) — «Слова пройдены · 6 минут», «Слушаю и отвечаю —
  /// пройдено · 5 минут»: the stage's own words, then the stage's minutes as the server counted them. No minutes from the
  /// server (a replay sends nothing) — the words alone, never «0 минут».
  static String passed(AppLocalizations l, PlanStage s, int? minutes) {
    final title = switch (s) {
      PlanStage.phrases => l.planSessionPassedPhrases,
      PlanStage.dialogue => l.planSessionPassedDialogue,
      PlanStage.listen => l.planSessionPassedListen,
      PlanStage.speak => l.planSessionPassedSpeak,
      PlanStage.recall => l.planSessionPassedRecall,
      PlanStage.repetition => l.planSessionPassedRepetition,
      PlanStage.words || PlanStage.conversation || PlanStage.unknown => l.planSessionPassedWords,
    };
    return minutes == null ? title : l.planWindowJoin(title, l.planMinutesCount(minutes));
  }

  /// THE THREE LINES OF A STAGE SUMMARY (30-6) — the frame's three slots, [StageLine.soft] on the third as the frame
  /// greys it. «Диалог» has the frame's own three lines («8 реплик, 6 с первого раза» · «сказал вслух 5 своих реплик» ·
  /// «дважды переспросил — врач повторил медленнее»); every other stage — its volume and first tries, what comes back
  /// tomorrow, and its warm line (решение архитектора 22.09). A line with nothing to say is not drawn: «Вспомнить» has
  /// nothing coming back, a stage of no units has no volume, and «0 своих реплик» is not a sentence this screen says.
  /// [role] — the scene's partner as a word inside a line («врач»), for the dialogue's third line.
  static List<StageLine> stageLines(AppLocalizations l, PlanStage s, StageTally t, {String? role}) {
    final volume = t.units < 1
        ? null
        : switch (s) {
            PlanStage.phrases => l.planSessionFirstTryPhrases(t.units, t.firstTry),
            PlanStage.dialogue || PlanStage.speak => l.planSessionFirstTryLines(t.units, t.firstTry),
            PlanStage.listen => l.planSessionFirstTryQuestions(t.units, t.firstTry),
            PlanStage.repetition => l.planSessionFirstTryCards(t.units, t.firstTry),
            PlanStage.recall => l.planSessionRecallLinesOfScenes(t.units, t.scenes),
            PlanStage.words || PlanStage.conversation || PlanStage.unknown => l.planSessionFirstTryWords(t.units, t.firstTry),
          };
    if (s == PlanStage.dialogue) {
      return [
        if (volume != null) (text: volume, soft: false),
        if (t.saidAloud > 0) (text: l.planSessionSaidAloud(t.saidAloud), soft: false),
        if (t.rescues > 0 && role != null && role.trim().isNotEmpty)
          (text: l.planSessionRescuedSlower(t.rescues, roleInline(role.trim())), soft: true),
      ];
    }
    final returns = s == PlanStage.recall ? null : (t.returns > 0 ? l.planSessionStageReturns(t.returns) : l.planSessionStageNoReturns);
    final warm = switch (s) {
      PlanStage.phrases => l.planSessionWarmPhrases,
      PlanStage.listen => l.planSessionWarmListen,
      PlanStage.speak => l.planSessionWarmSpeak,
      PlanStage.recall => l.planSessionWarmRecall,
      PlanStage.repetition => l.planSessionWarmRepetition,
      PlanStage.words || PlanStage.dialogue || PlanStage.conversation || PlanStage.unknown => l.planSessionWarmWords,
    };
    return [
      if (volume != null) (text: volume, soft: false),
      if (returns != null) (text: returns, soft: false),
      (text: warm, soft: true),
    ];
  }

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

  /// The role inside a line — the companion line («receptionist will understand») and the scene strip («Приём у
  /// врача · врач»): the first letter lowered, no declension. An abbreviation keeps its capitals — «ЛОР» stays «ЛОР»,
  /// not «лОР»: the letter is lowered only when the second one is lower-case already.
  static String roleInline(String role) {
    final runes = role.runes.toList();
    if (runes.length < 2) return role.toLowerCase();
    final second = String.fromCharCode(runes[1]);
    if (second.toLowerCase() != second) return role;
    return String.fromCharCode(runes.first).toLowerCase() + String.fromCharCodes(runes.skip(1));
  }
}
