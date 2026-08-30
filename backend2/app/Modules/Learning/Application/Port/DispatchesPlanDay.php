<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

/**
 * Queue the generation of one day. Fulfilled by Generation's queued job.
 *
 * ONE day at a time, and the next is queued when the previous is `ready` — never a fan-out over
 * the whole plan. Three reasons, in the order they bite: day 2 is generated with day 1's terms in
 * its KNOWN block and cannot be written before day 1 exists; a fan-out spends the whole plan's
 * money before the learner has looked at a single day of it; and a plan whose day 1 came back
 * broken should stop, not produce four more broken days.
 */
interface DispatchesPlanDay
{
    public function dispatchDay(string $planId, int $dayIndex): void;
}
