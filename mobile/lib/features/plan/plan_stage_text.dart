import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/plan/plan_models.dart';

/// Имя этапа — одно на плиту, кабинет и узел маршрута: «Слова», «Фразы», «Диалог», «Слушаю и
/// отвечаю», «Говорю сам» (решение владельца 12.09 при постановке PLAN-UI-3: пять этапов, как на
/// сервере; канва с тремя узлами расходится с продуктом и будет перерисована).
String planStageName(AppLocalizations l, PlanStage stage) => switch (stage) {
  PlanStage.words => l.planPlateStageWords,
  PlanStage.phrases => l.planPlateStagePhrases,
  PlanStage.dialogue => l.planPlateStageDialog,
  PlanStage.listen => l.planPlateStageListen,
  PlanStage.speak => l.planPlateStageSpeak,
  PlanStage.unknown => '',
};

/// Этап контракта → значок канвы (`assets/stages/`).
PlanStageMarkKind planStageMark(PlanStage stage) => switch (stage) {
  PlanStage.words => PlanStageMarkKind.words,
  PlanStage.phrases => PlanStageMarkKind.phrases,
  PlanStage.dialogue => PlanStageMarkKind.dialogue,
  PlanStage.listen => PlanStageMarkKind.listen,
  PlanStage.speak || PlanStage.unknown => PlanStageMarkKind.speak,
};
