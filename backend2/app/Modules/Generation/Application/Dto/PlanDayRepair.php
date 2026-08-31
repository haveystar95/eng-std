<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * What a P2R call came back with — the day with its broken cards replaced, and whatever the ANSWER
 * itself got wrong.
 *
 * The distinction the two fields carry is the one the pipeline turns on. A repair that was never
 * attempted (more than half the day broken, or a violation with no card behind it) is `null` at the
 * call site and sends the day back WHOLE; a repair that WAS attempted is this object, whether it
 * worked or not, because either way the money is spent and the day has no third answer coming.
 *
 * {@see $violations} is about the repair answer rather than about the day: a card returned at an
 * address nobody asked about, a card missing, one card too many. They are added to the verdict on
 * the merged day, so a repair that edited the wrong card fails loudly instead of quietly writing
 * one.
 */
final readonly class PlanDayRepair
{
    /**
     * @param  list<PlanDayItem>  $items  the WHOLE day: the repaired cards merged in at their own
     *         `(array, index)`, every other card byte for byte as it was
     * @param  list<PlanViolation>  $violations  what the repair ANSWER got wrong, if anything
     */
    public function __construct(
        public array $items,
        public array $violations = [],
    ) {}
}
