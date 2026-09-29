<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\ValueObject\Finding;

/** What the line repairs of a plan came to: the plan with its shortened lines, a finding per line, their money and time. */
final readonly class PlanLineRepairs
{
    /** @param list<Finding> $findings */
    public function __construct(
        public Blueprint $blueprint,
        public array $findings,
        public string $costUsd,
        public int $latencyMs,
    ) {}
}
