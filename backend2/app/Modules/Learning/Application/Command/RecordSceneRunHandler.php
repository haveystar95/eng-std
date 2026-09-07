<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Service\PlanDayPassing;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\PlanSceneRunRepository;
use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\ValueObject\PlanSceneRun;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * ЗАПИСЬ ЗАВЕРШЁННОГО ПРОГОНА СЦЕНЫ — наряд SCENE-RUN, Ч.2.6.
 *
 * Две записи в одной транзакции, и они говорят о разном:
 *
 *   `learning_plan_scene_runs`      что БЫЛО в этот раз — событие, не редактируется никогда;
 *   `learning_plan_term_stages`     что реплика ДОКАЗАЛА за всё время — лучшим результатом.
 *
 * Разделение не косметическое. Итог прогона показывают человеку («Прошёл сам 2 из 4 · сразу 1»), и
 * он обязан описывать ЭТОТ прогон; зрелость сцены отвечает на другой вопрос — «умеешь ли ты это», —
 * и второй прогон, в котором человек устал и половину пропустил, не отменяет того, что он это
 * говорил. Одна таблица на оба вопроса врала бы на одном из них.
 *
 * ## Ответы в журнал пишет не этот обработчик
 *
 * Каждый ход прогона уже уехал в `reviews` обычной партией — сказал это `speaking/good`, пропустил
 * `speaking/again` (наряд Ч.2.4, та же семантика, что у говорения фраз). Дублировать их здесь
 * значило бы записать один ответ дважды в append-only журнал.
 *
 * ## …А ВОТ ВЕРДИКТ ДНЯ ПИШЕТ ИМЕННО ОН (наряд DAY-GATE-1, Ч.1.1)
 *
 * Прогон — третий и последний этап дня («Скажи сам»), и запись прогона это ровно тот момент, когда
 * день может стать пройденным. Раньше вердикт дописывался на входе в СЛЕДУЮЩУЮ посадку, и этого
 * хватало: прогон в «день пройден» не входил. Теперь входит — а человек, закончивший прогон, чаще
 * всего закрывает приложение, и следующей посадки в этот вечер уже нет. День остался бы «идёт» до
 * завтра, и следующий день не встал бы в очередь.
 */
final readonly class RecordSceneRunHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanSceneRunRepository $runs,
        private PlanTermStageRepository $stages,
        private TransactionManager $tx,
        /** «День пройден» — тем же кодом, что и конец посадки ({@see PlanDayPassing}). */
        private PlanDayPassing $passing,
    ) {}

    public function __invoke(RecordSceneRun $command): PlanSceneRun
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
            throw PlanNotFound::withId($command->planId->value);
        }

        $said = 0;
        $fast = 0;
        $skipped = 0;
        $rescued = 0;
        foreach ($command->turns as $turn) {
            match (true) {
                $turn->outcome->isSaid() => $said++,
                $turn->outcome === \App\Modules\Learning\Domain\ValueObject\SceneRunOutcome::Skipped => $skipped++,
                default => $rescued++,
            };
            if ($turn->outcome->isFast()) {
                $fast++;
            }
        }

        $run = new PlanSceneRun(
            planId: $plan->id()->value,
            sceneIndex: $command->sceneIndex,
            dayIndex: $command->dayIndex,
            total: count($command->turns),
            said: $said,
            saidFast: $fast,
            skipped: $skipped,
            rescued: $rescued,
        );

        $written = $this->tx->run(function () use ($plan, $command, $run): PlanSceneRun {
            $this->runs->add($run);

            $stages = $this->stages->forPlan($plan->id());
            foreach ($command->turns as $turn) {
                $stage = $stages[$turn->termId] ?? new PlanTermStage($plan->id()->value, $turn->termId);
                $this->stages->save($stage->afterRun($turn->outcome->isSaid(), $turn->outcome->isFast()));
            }

            return $run;
        });

        // ВНЕ ТРАНЗАКЦИИ, как и во всех остальных местах: отметка дня ставит в очередь следующий, а
        // воркер может взять задачу раньше, чем ляжет коммит.
        $this->passing->refreshActiveFor($plan->userId());

        return $written;
    }
}
