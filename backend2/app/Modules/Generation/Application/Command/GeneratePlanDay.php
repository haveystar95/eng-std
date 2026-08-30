<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

/** Write one day of a plan. Idempotent by (plan, day) — the claim decides who pays. */
final readonly class GeneratePlanDay
{
    public function __construct(public string $planId, public int $dayIndex) {}
}
