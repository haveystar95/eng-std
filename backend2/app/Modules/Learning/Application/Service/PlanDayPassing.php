<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanGenerationPolicy;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «ЭТОТ ДЕНЬ ПРОЙДЕН» — written onto the row, and the one event day n+1 is generated from.
 *
 * `passed` is a DERIVED fact: every word of the day has closed stage A. Deriving it is cheap and a
 * read could do it, but two things need it written down — the generation policy waits for a day to
 * be `done` before queueing the next one, and a queue cannot subscribe to a projection.
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
     * Write `done` onto every day of this plan whose words have all closed stage A.
     *
     * @param  list<PlanDay>  $days
     */
    public function mark(LearningPlan $plan, array $days, PlanProgressView $progress): void
    {
        $computed = $plan->computed();
        $introDays = is_int($computed['intro_days'] ?? null) ? $computed['intro_days'] : 1;

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
                $introDays,
            );
            if ($next !== null) {
                $this->dispatcher->dispatchDay($day->planId()->value, $next);

                return;
            }
        }
    }
}
