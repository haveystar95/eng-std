<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * What came through the gate: the answer to store (repaired or as written) with the findings over it, or — no
 * answer — why the lesson failed. `cardsAsked` are the addresses P2R was asked for, in order; `gated` the fatal
 * findings of the answer as the model wrote it; `failedOn` the fatal findings left when the lesson failed; the
 * cost and time of the repairs.
 */
final readonly class LessonGateOutcome
{
    /**
     * @param  list<LessonViolation>  $findings
     * @param  list<string>  $cardsAsked
     * @param  list<LessonViolation>  $gated
     * @param  list<LessonViolation>  $failedOn
     */
    public function __construct(
        public ?Lesson $answer,
        public array $findings,
        public ?string $failReason,
        public array $cardsAsked,
        public array $gated,
        public array $failedOn,
        public string $repairCostUsd,
        public int $repairLatencyMs,
    ) {}
}
