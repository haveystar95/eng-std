<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

/**
 * The generator is done with a day: ready with a collection, or failed with a reason.
 *
 * @param  list<string>  $termIds  the day's terms, for the strict enrolment. Empty on failure.
 */
final readonly class FinishPlanDay
{
    /** @param list<string> $termIds */
    public function __construct(
        public string $planId,
        public int $dayIndex,
        public ?string $collectionId,
        public array $termIds = [],
        public ?string $failReason = null,
    ) {}
}
