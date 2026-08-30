<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

/** Take a day for generation, and get back everything needed to write it — or null. */
final readonly class ClaimPlanDay
{
    public function __construct(public string $planId, public int $dayIndex) {}
}
