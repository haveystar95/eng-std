<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/** One term of a plan day, as much of it as the ORDER needs to know. */
final readonly class PlanDayCard
{
    public function __construct(
        public string $termId,
        public bool $isLine,
        /** {@see \App\Modules\Shared\Domain\Service\DifficultyScorer}; null = never scored. */
        public ?int $difficultyScore,
    ) {}
}
