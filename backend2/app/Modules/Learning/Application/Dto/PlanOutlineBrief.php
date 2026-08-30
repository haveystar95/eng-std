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
        // The plan and its owner: P1 is a PAID call and its ledger row has to say whose it was and
        // which plan it belongs to. A brief that carried only the content would make the writer
        // re-derive both, which is how a row ends up attached to the wrong plan.
        public string $planId,
        public string $userId,
        public string $goalText,
        public string $supportLang,
        public string $targetLang,
        public string $level,
        public int $days,
        public int $minutesPerDay,
    ) {}
}
