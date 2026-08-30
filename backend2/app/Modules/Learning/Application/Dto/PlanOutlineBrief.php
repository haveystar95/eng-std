<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * Everything P1 is told, in primitives.
 *
 * `days` is here and is the SERVER's number — days from today to the event, inclusive. The model is
 * handed the count and asked to fill it; it is never asked to work it out. See
 * {@see \App\Modules\Learning\Domain\Service\PlanScheduler}.
 */
final readonly class PlanOutlineBrief
{
    public function __construct(
        public string $goalText,
        public string $supportLang,
        public string $targetLang,
        public string $level,
        public int $days,
        public int $minutesPerDay,
    ) {}
}
