<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

/**
 * The generator is done with a day: ready with a collection, or failed with a reason.
 *
 * @param  list<string>  $termIds  the day's terms, for the strict enrolment. Empty on failure.
 * @param  list<string>  $failViolations  the verdict as ADDRESSES, one line per failed check, for
 *         the next attempt to be told about. `failReason` is the same verdict as prose, for a
 *         person; both are kept because they have different readers, and only one of them is ever
 *         allowed to carry what the model wrote.
 * @param  int  $repairCalls  P2R calls this run made — 0, or 1 when a repair was spent
 *         ({@see \App\Modules\Generation\Application\Service\PlanDayRepairer}). Charged into the
 *         day's own `repair_calls`, on the ready path and the failed one alike: an ATTEMPT is a day
 *         call, and folding the repair into the attempt counter made identical spending read as one
 *         number or two depending on the outcome (Д-18).
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
        public int $repairCalls = 0,
        /**
         * The first fatal violation's CODE, for the client's own wording of what went wrong (Д-19).
         * Null on a failure that has no verdict — a vendor error, a write that did not land.
         */
        public ?string $failCode = null,
        /**
         * THE ORDER THE SCENE IS SPOKEN IN — `[{turn, term_id}, …]`, already resolved against the
         * terms this same run imported (наряд DAY-2, P2 v0.5). Empty on a failure, and empty on a
         * day whose prompt never wrote a chain: the day keeps whatever it had rather than losing it.
         *
         * @var list<array{turn: string, term_id: string}>
         */
        public array $dialogue = [],
    ) {}
}
