<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * A subscriber asking for one plan too many (наряд ACC-1 §2): at most three plans in work at once — every plan not
 * finished and not deleted, built or still being built. Finish or delete one first. Answered only while the paywall is
 * switched on.
 */
final class PlanActiveLimit extends PlanProblem
{
    public static function at(int $limit, int $open): self
    {
        return new self("{$open} plans in work, the limit is {$limit}.", ['limit' => $limit, 'plans_in_work' => $open]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_active_limit';
    }

    public function problemTitle(): string
    {
        return 'Too many plans in work';
    }
}
