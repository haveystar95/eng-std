<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** Tones and sized copies for the scene photos stored before PLAN-UI-3 — one plan, or all. */
final readonly class BackfillSceneImages
{
    public function __construct(
        public ?PlanId $planId = null,
    ) {}
}
