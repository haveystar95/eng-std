import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/plan/plan_models.dart';

/// Имя этапа — одно на плиту, кабинет, узел маршрута и вход в этап: «Слова», «Фразы», «Диалог»,
/// «Слушаю и отвечаю», «Говорю сам», «Разговор» (наряд CONV-1 — шестой этап; у репетиции вместо
/// первых пяти «Вспомнить», у повторения — «Повторение», и тот же «Разговор»).
///
/// Сколько их у дня — решает сервер: имя здесь есть у каждого этапа контракта, а какие из них
/// нарисовать, говорит `stages[]` ответа. У [PlanStage.unknown] имени нет: ряд без имени не рисуется.
String planStageName(AppLocalizations l, PlanStage stage) => switch (stage) {
  PlanStage.words => l.planPlateStageWords,
  PlanStage.phrases => l.planPlateStagePhrases,
  PlanStage.dialogue => l.planPlateStageDialog,
  PlanStage.listen => l.planPlateStageListen,
  PlanStage.speak => l.planPlateStageSpeak,
  PlanStage.repetition => l.planPlateStageRepetition,
  PlanStage.recall => l.planPlateStageRecall,
  PlanStage.conversation => l.planPlateStageTalk,
  PlanStage.unknown => '',
};

/// Этап контракта → значок канвы (`assets/stages/`). У разговора значок диалога, у «Вспомнить» —
/// значок фраз, у «Повторения» — значок слов «Aa»: так их нарисовала канва (23-0a, 37-1, 37-2).
PlanStageMarkKind planStageMark(PlanStage stage) => switch (stage) {
  PlanStage.words || PlanStage.repetition => PlanStageMarkKind.words,
  PlanStage.phrases || PlanStage.recall => PlanStageMarkKind.phrases,
  PlanStage.dialogue => PlanStageMarkKind.dialogue,
  PlanStage.listen => PlanStageMarkKind.listen,
  PlanStage.conversation => PlanStageMarkKind.talk,
  PlanStage.speak || PlanStage.unknown => PlanStageMarkKind.speak,
};
