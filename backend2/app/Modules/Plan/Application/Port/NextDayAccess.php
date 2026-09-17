<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;

/**
 * MAY THE LEARNER HAVE THE NEXT DAY (наряд GEN-3 §11) — asked in ONE place, right before the lesson of the next day is asked
 * for (when the day before it closes). Until PAY-1 the answer is always yes; the paywall after day 1 and the build on payment
 * are PAY-1's, and its adapter is the only thing it changes.
 */
interface NextDayAccess
{
    public function nextDayAllowed(Plan $plan, PlanDay $next): bool;
}
