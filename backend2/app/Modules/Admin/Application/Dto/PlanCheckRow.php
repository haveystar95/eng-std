<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One counter of the plan's checks: how many times a check fired under a prompt version, and what it did. */
final readonly class PlanCheckRow
{
    public function __construct(
        public string $promptVersion,
        public string $check,
        /** `counted` (observe) | `dropped` | `gated` */
        public string $action,
        public int $hits,
    ) {}
}
