import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/session_models.dart';

/// СЛОВА СЕССИИ — выбор строки ARB по этапу, уровню и числу. Ни одного слова здесь, только выбор.
abstract final class SessionTexts {
  static String stage(AppLocalizations l, PlanStage s) => switch (s) {
    PlanStage.words => l.planPlateStageWords,
    PlanStage.phrases => l.planPlateStagePhrases,
    PlanStage.dialogue => l.planPlateStageDialog,
    PlanStage.listen => l.planPlateStageListen,
    PlanStage.speak || PlanStage.unknown => l.planPlateStageSpeak,
  };

  /// Описание этапа на входе (30-1); [units] — единицы этапа.
  static String description(AppLocalizations l, PlanStage s, int units) => switch (s) {
    PlanStage.words => l.planSessionDescWords(units),
    PlanStage.phrases => l.planSessionDescPhrases(units),
    PlanStage.dialogue => l.planSessionDescDialogue,
    PlanStage.listen => l.planSessionDescListen,
    PlanStage.speak || PlanStage.unknown => l.planSessionDescSpeak,
  };

  /// «ещё 4 слова» — справа в шапке (30-2).
  static String left(AppLocalizations l, PlanStage s, int units) => switch (s) {
    PlanStage.phrases => l.planSessionLeftPhrases(units),
    _ => l.planSessionLeftWords(units),
  };

  /// «Слова пройдены · 6 минут» (30-6).
  static String done(AppLocalizations l, PlanStage s, int minutes) {
    final m = l.planMinutesCount(minutes);
    return s == PlanStage.phrases ? l.planSessionDonePhrases(m) : l.planSessionDoneWords(m);
  }

  /// «Остальные 6 слов закрыты.» / «Все 8 слов закрыты.» (30-6).
  static String closed(AppLocalizations l, PlanStage s, {required int closed, required bool someReturn}) =>
      switch ((s, someReturn)) {
        (PlanStage.phrases, true) => l.planSessionRestPhrases(closed),
        (PlanStage.phrases, false) => l.planSessionAllPhrases(closed),
        (_, true) => l.planSessionRestWords(closed),
        (_, false) => l.planSessionAllWords(closed),
      };

  /// Роль в строке спутника — со строчной («регистратор поймёт»); склонения нет.
  static String roleInline(String role) => role.isEmpty ? role : role[0].toLowerCase() + role.substring(1);

  /// Карточки этого вида — экран есть в этой сборке.
  static bool screened(SessionKind k) => k.hasScreen;
}
