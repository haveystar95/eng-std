<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;

/**
 * «ВЕРНЁТСЯ В ДЕНЬ N» — THE DAY A UNIT FAILED TWICE COMES BACK ON (DAY-UI-3; наряд SESSION-1a, разд. 3).
 *
 * The next day, whatever its type — a unit that failed twice comes back once, on the nearest following day (SESSION-1a,
 * хвост); none when there is no next day. One rule for the day window's word sheet and the reply to an answer, so
 * «вернётся завтра» and the day it names never disagree.
 */
final class ReturnDay
{
    public static function of(Plan $plan, PlanDay $day): ?int
    {
        foreach ($plan->days() as $next) {
            if ($next->number() === $day->number() + 1) {
                return $next->number();
            }
        }

        return null;
    }
}
