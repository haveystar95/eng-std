<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ONE ability the plan promises — «ты сможешь: сказать, где именно болит» — with the checkpoint
 * that proves it and the price of teaching it.
 *
 * The unit the SERVER schedules with, and the reason it exists as a type at all. The model answers
 * in DAYS, already split; the server has to be able to re-split them, because the learner may
 * change the minutes or the plan may meet a nearer deadline than the outline was written for, and
 * a re-split needs something smaller than a day to move around. An ability is that thing: it is
 * what the learner is buying, it is what the conversation checks, and it does not divide further.
 *
 * `estTerms` is the day's `term_budget` shared out over the day's outcome lines — the only per-
 * ability number that exists anywhere, because P1 prices a DAY and not an ability. Shared out with
 * the remainder spread over the first lines rather than by rounding each one up: rounding up three
 * abilities out of a 16-term day says the day needs 18, which is an artefact of division and not a
 * fact about the plan. See {@see PlanOutlineDay::skills()}.
 */
final readonly class PlanSkill
{
    public function __construct(
        /** The ability, in the learner's own language, as P1 wrote it. */
        public string $outcome,
        /** How many terms this ability costs. Always ≥ 1. */
        public int $estTerms,
        /** What must be heard for it to count, or null when the day has no interlocutor. */
        public ?string $checkpoint,
        /** Which outline day it came from — the source of its role, its topics and its title. */
        public int $sourceDayIndex,
    ) {}
}
