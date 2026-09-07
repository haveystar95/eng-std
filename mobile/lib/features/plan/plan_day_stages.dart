/// ИЗ ЧЕГО СОСТОИТ ДЕНЬ — наряд DAY-GATE-1, Ч.2.1.
///
/// Экран дня раньше говорил про день одним словом («идёт», «материал пройден») и одной кнопкой, и
/// живой прогон 07.09 показал, чего этого не хватает: человек не видел, что день — это ЧЕТЫРЕ вещи,
/// не понимал, почему «Продолжить» ведёт то в слова, то в разговор, и почему день не считается
/// пройденным, когда «вроде всё сделал».
///
/// Всё, что здесь нарисовано, ПРИХОДИТ С СЕРВЕРА (`PlanDay.stages`): и состав, и порядок, и
/// состояние каждого этапа, и то, после какого этапа откроется запертый. Экран не выводит ничего
/// сам — ни «наверное, пройдено», ни «наверное, следующий»: нет поля — нет блока.
///
/// ЧИСЕЛ ЗДЕСЬ НЕТ. «N из M» на экранах плана не бывает намеренно (наряд, Ч.2.1): человеку важно
/// «что сейчас» и «что после чего», а не счёт карточек, которого он всё равно не контролирует.
library;

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/plan_models.dart';

/// Подпись этапа — клиентская, в двух языках, сервер везёт код (Д-19).
String planStageLabel(AppLocalizations l, PlanStageCode stage) => switch (stage) {
  PlanStageCode.material => l.planStageMaterial,
  PlanStageCode.conversation => l.planStageConversation,
  PlanStageCode.rehearsal => l.planStageRehearsal,
  PlanStageCode.retrain => l.planStageRetrain,
  // Этап, которого эта сборка не знает: показываем строку без имени, а не «» и не выдуманное имя.
  PlanStageCode.unknown => '—',
};

/// Состояние этапа СЛОВОМ. Запертый говорит не «закрыт», а после чего откроется: «закрыт» — это
/// тупик, а «после разговора» — это дорога.
String planStageStateWord(AppLocalizations l, PlanDayStage stage) => switch (stage.state) {
  PlanStageState.done => l.planStageStateDone,
  PlanStageState.current => l.planStageStateCurrent,
  PlanStageState.locked => stage.opensAfter == null
      // Запертый без «после чего» — такого сервер не шлёт, но если пришлёт, честнее промолчать о
      // причине, чем назвать неверную.
      ? ''
      : l.planStageStateAfter(planStageLabel(l, stage.opensAfter!)),
};

/// «до дня 3 — ещё разговор и скажи сам» / «день 3 открыт», или null.
///
/// Строка отвечает на вопрос, который живой прогон задал вслух: «я прошёл день, почему следующего
/// нет». План пишется по одному дню, и следующий встаёт в очередь по факту «день N пройден»
/// (решение 294) — значит человек имеет право знать, чего именно не хватает.
///
/// Null там, где сказать нечего: у дня без этапов (старый сервер) и у последнего дня плана, за
/// которым следующего нет.
String? planNextDayLine(AppLocalizations l, PlanDay day, {required bool hasNextDay}) {
  if (day.requiredStages.isEmpty || !hasNextDay) return null;
  final next = day.index + 1;
  if (day.allStagesDone) return l.planNextDayOpen(next);

  final left = day.stagesLeft.map((s) => planStageLabel(l, s.stage)).join(' · ');

  return l.planNextDayLeft(next, left);
}

/// Список этапов дня — по строке на этап, состояние словом справа.
class PlanDayStagesBlock extends StatelessWidget {
  const PlanDayStagesBlock({super.key, required this.stages});

  final List<PlanDayStage> stages;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final stage in stages)
          _StageRow(
            title: planStageLabel(l, stage.stage),
            state: planStageStateWord(l, stage),
            // «Повторить ошибки» стоит отдельной строкой и подписано необязательным: день от него
            // не зависит, и человек, который его пропустил, не должен думать, что не дошёл.
            note: stage.stage.isOptional ? l.planStageOptional : null,
            done: stage.state == PlanStageState.done,
            current: stage.state == PlanStageState.current,
            last: stage == stages.last,
          ),
      ],
    );
  }
}

class _StageRow extends StatelessWidget {
  const _StageRow({
    required this.title,
    required this.state,
    required this.done,
    required this.current,
    required this.last,
    this.note,
  });

  final String title;
  final String state;
  final String? note;
  final bool done;
  final bool current;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 11),
      decoration: last
          ? null
          : const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
            ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: AppText.termInList.copyWith(
                    fontSize: 16.5,
                    // Текущий этап — единственный, набранный чернилами: остальные тише, и глаз
                    // находит «что сейчас» без счёта и без цвета-светофора.
                    color: current ? AppColors.ink : AppColors.secondary,
                    fontWeight: current ? FontWeight.w600 : FontWeight.w400,
                  ),
                ),
                if (note != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    note!,
                    style: AppText.translation.copyWith(fontSize: 12, color: AppColors.tertiary),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 12),
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(
              state,
              style: AppText.translation.copyWith(
                fontSize: 13,
                color: done ? AppColors.brassInk : AppColors.tertiary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
