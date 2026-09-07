<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\PlanStagePassageRepository;
use App\Modules\Learning\Domain\Service\PlanDayPassage;
use App\Modules\Learning\Domain\Service\PlanGenerationPolicy;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «ЭТОТ ДЕНЬ ПРОЙДЕН» — written onto the row, and the one event day n+1 is generated from.
 *
 * `passed` is a DERIVED fact: все три этапа дня пройдены насквозь
 * ({@see \App\Modules\Learning\Domain\Service\PlanDayPassage}). Deriving it is cheap and a read
 * could do it, but two things need it written down — the generation policy waits for a day to be
 * `done` before queueing the next one, and a queue cannot subscribe to a projection.
 *
 * С наряда DAY-GATE-1 это ЕДИНСТВЕННОЕ событие, по которому пишется следующий день: короткий план
 * больше не пишется целиком на старте. Поэтому строчка ниже — «после отметки, вне транзакции» —
 * теперь не оптимизация, а вся цепочка плана.
 *
 * ## Why this is a service and not a private method any more
 *
 * It was private to {@see \App\Modules\Learning\Application\Command\BuildPlanSessionHandler}, which
 * meant a day became `done` only when the NEXT plan session was built. A learner who finished day 1
 * and went back to the home screen had a day the server still called `ready`: the plan card said
 * «День 1 из 5» over a day they had just walked, and day 2 was not queued until they opened a
 * session again. The verdict has two moments now — the end of a sitting, and the start of the next
 * one — and one implementation between them.
 *
 * Idempotent by construction: a day already `done` is not in the list to mark, and `markDone()` on
 * one that is not passed never happens because `passed` is what selects it.
 */
final readonly class PlanDayPassing
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
        private DispatchesPlanDay $dispatcher,
        private TransactionManager $tx,
        /**
         * ЖУРНАЛ ЭТАПОВ (наряд DAY-GATE-1, доработка). Этот сервис и есть «момент, когда этап мог
         * закрыться»: его зовут конец присеста, запись прогона, сборка следующей посадки и сверка —
         * ровно те четыре места. Поэтому событие пишется здесь, а не на пути чтения.
         */
        private PlanStagePassageRepository $stagePassages,
    ) {}

    /**
     * Re-judge the learner's ACTIVE plan, if they have one.
     *
     * The entry point for «a sitting just ended»: the caller there knows a session closed and does
     * not know whether it was a plan's. Asking for the active plan first is the cheap half of the
     * answer — a learner with no plan running pays one indexed lookup and nothing else, and a plan
     * that is paused or finished is not judged at all.
     */
    public function refreshActiveFor(UserId $userId): void
    {
        $plan = $this->plans->findActiveFor($userId);
        if ($plan === null) {
            return;
        }

        $days = $this->days->listForPlan($plan->id());
        $this->mark($plan, $days, $this->progress->forPlan($plan, $days));
    }

    /**
     * ЗАПИСАТЬ ЭТАПЫ, КОТОРЫЕ ТОЛЬКО ЧТО ЗАКРЫЛИСЬ (наряд DAY-GATE-1, доработка).
     *
     * Первым делом в {@see mark()}, до вердикта о дне: «день пройден» складывается из этапов, и
     * записывать его раньше, чем их, значило бы держать вывод, который завтра не из чего повторить.
     *
     * Идемпотентно на уровне базы, поэтому вызывать можно из каждого места, где этап мог закрыться,
     * не сверяясь предварительно.
     */
    private function recordClosedStages(LearningPlan $plan, PlanProgressView $progress): void
    {
        $passed = $this->stagePassages->forPlan($plan->id());

        $rows = [];
        foreach ($progress->days as $index => $view) {
            foreach (PlanDayPassage::justClosed($view->stages, $passed[$index] ?? []) as $stage) {
                $rows[] = ['day_index' => $index, 'stage' => $stage];
            }
        }

        // Дата ученика, а не сервера: этап закрылся в ЕГО день, и «сегодня» у него своё
        // ({@see PlanProgressView::$today}).
        $this->stagePassages->record($plan->id(), $rows, $progress->today);
    }

    /**
     * Write `done` onto every day of this plan whose words have all closed stage A.
     *
     * @param  list<PlanDay>  $days
     */
    public function mark(LearningPlan $plan, array $days, PlanProgressView $progress): void
    {
        $this->recordClosedStages($plan, $progress);

        $toMark = [];
        foreach ($days as $day) {
            $view = $progress->days[$day->dayIndex()] ?? null;
            if ($view !== null && $view->passed && $day->status() !== PlanDayStatus::Done) {
                $toMark[] = $day;
            }
        }
        if ($toMark === []) {
            return;
        }

        $this->tx->run(function () use ($toMark): void {
            foreach ($toMark as $day) {
                $day->markDone();
                $this->days->save($day);
            }
        });

        // A day just became DONE, which is the event the generation policy waits for: the next day
        // is queued now, and only now. Outside the transaction, because a worker can pick a job up
        // before the commit lands — and after the marking, because the policy reads the statuses
        // this loop just changed.
        $fresh = $this->days->listForPlan($toMark[0]->planId());
        foreach ($toMark as $day) {
            $next = PlanGenerationPolicy::nextAfterDone(
                $fresh,
                $day->dayIndex(),
                $progress->focusDayIndex,
            );
            if ($next !== null) {
                $this->dispatcher->dispatchDay($day->planId()->value, $next);

                return;
            }
        }
    }
}
