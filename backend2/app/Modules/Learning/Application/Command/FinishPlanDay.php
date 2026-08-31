<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

/**
 * The generator is done with a day: ready with a collection, or failed with a reason.
 *
 * @param  list<string>  $termIds  the day's terms, for the strict enrolment. Empty on failure.
 * @param  list<string>  $failViolations  the verdict as DATA, one line per failed check, for the
 *         next attempt to be told about. `failReason` is the same verdict as prose, for a person;
 *         both are kept because they have different readers.
 */
final readonly class FinishPlanDay
{
    /**
     * @param  list<string>  $termIds
     * @param  list<string>  $failViolations
     */
    public function __construct(
        public string $planId,
        public int $dayIndex,
        public ?string $collectionId,
        public array $termIds = [],
        public ?string $failReason = null,
        public array $failViolations = [],
    ) {}
}
