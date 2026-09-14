<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** Photos for the cover and the scenes that still lack one — the route's pictures. Queued, idempotent, best effort. */
final readonly class AttachPlanImages
{
    public function __construct(public PlanId $planId) {}
}
