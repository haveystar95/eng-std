<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\DayType;

/**
 * «ВЕРНЁТСЯ В ДЕНЬ N» — THE DAY A UNIT FAILED TWICE COMES BACK ON (DAY-UI-3; наряд SESSION-1a, разд. 3).
 *
 * The next day, when it is a scene or a review day — both deal the returns of the scene days before them; none when
 * the next day is the rehearsal (it deals only what is said aloud over the whole plan) or there is no next day. One
 * rule for the day window's word sheet and the reply to an answer, so «вернётся завтра» and the day it names never
 * disagree.
 */
final class ReturnDay
{
    public static function of(Plan $plan, PlanDay $day): ?int
    {
        foreach ($plan->days() as $next) {
            if ($next->number() === $day->number() + 1) {
                return $next->type() === DayType::Rehearsal ? null : $next->number();
            }
        }

        return null;
    }
}
