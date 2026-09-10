<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** Photos for the cover, the scenes and the terms that still lack one. Queued, idempotent, best effort. */
final readonly class AttachPlanImages
{
    public function __construct(public PlanId $planId) {}
}
