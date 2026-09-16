import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/session_models.dart';

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

  /// «4 words left» — on the right of the header (30-2).
  static String left(AppLocalizations l, PlanStage s, int units) => switch (s) {
    PlanStage.phrases => l.planSessionLeftPhrases(units),
    _ => l.planSessionLeftWords(units),
  };

  /// «Words done · 6 minutes» (30-6).
  static String done(AppLocalizations l, PlanStage s, int minutes) {
    final m = l.planMinutesCount(minutes);
    return s == PlanStage.phrases ? l.planSessionDonePhrases(m) : l.planSessionDoneWords(m);
  }

  /// «The other 6 words are done.» / «All 8 words done.» (30-6).
  static String closed(AppLocalizations l, PlanStage s, {required int closed, required bool someReturn}) =>
      switch ((s, someReturn)) {
        (PlanStage.phrases, true) => l.planSessionRestPhrases(closed),
        (PlanStage.phrases, false) => l.planSessionAllPhrases(closed),
        (_, true) => l.planSessionRestWords(closed),
        (_, false) => l.planSessionAllWords(closed),
      };

  /// The role in the companion line — with a lowercase first letter («receptionist will understand»); no declension.
  static String roleInline(String role) => role.isEmpty ? role : role[0].toLowerCase() + role.substring(1);

  /// Cards of this kind — there is a screen for them in this build.
  static bool screened(SessionKind k) => k.hasScreen;
}
