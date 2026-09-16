<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * HOW MANY SLOT-JUDGE CALLS A LEARNER HAS LEFT TODAY (наряд SESSION-1a, разд. 4): the judge is a paid model call inside
 * an HTTP request, so it is capped per learner per LOCAL day — the day ends at the learner's midnight, not the
 * server's. Past the cap the verdict is the code's, and nothing is bought.
 */
interface SlotJudgeQuota
{
    /**
     * Take one call of today's quota: true when it was there to take (the call may be made), false when `$cap` calls
     * were already taken today in `$zone`. A refused take counts nothing.
     */
    public function take(UserId $user, DateTimeImmutable $now, DateTimeZone $zone, int $cap): bool;
}
