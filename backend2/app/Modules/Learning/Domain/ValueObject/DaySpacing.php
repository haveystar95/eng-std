<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * How the introduction days sit on the calendar.
 *
 * Not a preference — a consequence. When there is at least as much slack as there is teaching
 * ({@see \App\Modules\Learning\Domain\Service\PlanScheduler}), the introduction days are spread out
 * with a free day between them, because a day off between two teaching days is what turns them
 * into two memories instead of one blur. When there is not, they run back to back, and the plan
 * says so rather than inventing room it does not have.
 */
enum DaySpacing: string
{
    case Daily = 'daily';
    case EveryOtherDay = 'every_other_day';
}
