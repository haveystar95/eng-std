<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** The queued half of creating (or extending) a plan: ask the model, check, write. */
final readonly class BuildPlan
{
    public function __construct(
        public PlanId $planId,
        /** > 0 on an extension: how many scenes to add after the existing ones. */
        public int $scenesToAdd = 0,
    ) {}
}
