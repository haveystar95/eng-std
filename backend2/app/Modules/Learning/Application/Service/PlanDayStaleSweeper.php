<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * DAYS WHOSE WORKER NEVER ANSWERED, taken back by whoever reads the plan next.
 *
 * There is no cron here on purpose: a day is only ever «собирается» to a person looking at it,
 * and the two places a person looks — the client's poll of `POST …/days/{n}/generate` and the plan
 * payload — are where the sweep runs. A day claimed longer ago than
 * `learning.plan.generation_stale_minutes` is re-queued once (its spent attempt stays spent), and
 * failed with a reason the second time ({@see PlanDay::reclaimStale()}). Anything not stale passes
 * through untouched, so calling this on every read costs nothing but the comparison.
 */
final readonly class PlanDayStaleSweeper
{
    public function __construct(
        private PlanDayRepository $days,
        private DispatchesPlanDay $dispatcher,
        private Clock $clock,
        /** `learning.plan.generation_stale_minutes` — the window a worker has before it is presumed dead. */
        private int $staleMinutes = 10,
    ) {}

    /**
     * Re-queue or fail every stale day of the list; return the list with the changed days replaced.
     *
     * @param  list<PlanDay>  $days
     * @return list<PlanDay>
     */
    public function sweep(string $planId, array $days): array
    {
        $now = $this->clock->now();
        $out = [];
        foreach ($days as $day) {
            $outcome = $day->reclaimStale($now, $this->staleMinutes);
            if ($outcome === null) {
                $out[] = $day;

                continue;
            }

            $this->days->save($day);
            if ($outcome === PlanDayStatus::Pending) {
                // The SECOND attempt, bought the way the first was: through the queue, so a dead
                // worker's day is written by a live one and the reader is not kept waiting for it.
                $this->dispatcher->dispatchDay($planId, $day->dayIndex());
                $fresh = $this->days->findByIndex($day->planId(), $day->dayIndex());
                $out[] = $fresh ?? $day;

                continue;
            }
            $out[] = $day;
        }

        return $out;
    }
}
