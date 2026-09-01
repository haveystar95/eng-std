<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * What the ended-plan sweep found, and what it did about it.
 *
 * The two numbers are the two halves of the one rule, and they are reported apart because they are
 * different events for the learner: `unenrolled` pairs LEFT the study queue (the plan was their only
 * reason to be in it), `stripped` pairs stayed and merely lost a marker (they had a reason of the
 * learner's own beside it). On a dry run both are what WOULD happen.
 */
final readonly class PlanArchiveSweepView
{
    /** @param list<array{user_id: string, title: string, status: string, pairs: int}> $rows */
    public function __construct(
        public array $rows,
        public int $unenrolled,
        public int $stripped,
    ) {}
}
