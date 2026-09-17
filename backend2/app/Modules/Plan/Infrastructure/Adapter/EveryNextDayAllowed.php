<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\NextDayAccess;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;

/** Before PAY-1: every learner may have every next day — the body PAY-1 replaces with the paywall after day 1. */
final class EveryNextDayAllowed implements NextDayAccess
{
    public function nextDayAllowed(Plan $plan, PlanDay $next): bool
    {
        return true;
    }
}
