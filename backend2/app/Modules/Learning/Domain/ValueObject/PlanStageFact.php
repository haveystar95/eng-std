<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ONE answer this learner has already given on one term of a plan, reduced to the three things the
 * stage ladder needs: which trainer, did it work, and on which of the learner's own days.
 *
 * The stage a word stands on is DERIVED from these and stored nowhere. That is the design decision
 * this type exists to make possible, and it is the same one the whole module already lives by:
 * reviews are an append-only log and everything about progress is a projection over it. A stored
 * `current_stage` column would be a second source of truth that a replayed offline batch, a
 * re-graded answer or a bug could put out of step with the log — and the symptom would be a word
 * dealt a dictation card the day it was introduced.
 *
 * The date is the LEARNER's local day (`profiles.timezone`), a string and not an instant, because
 * the rule it serves is «after a night», which is a fact about a calendar and not about elapsed
 * hours. An answer at 23:50 and one at 00:10 are two days apart to a person and twenty minutes
 * apart to a timestamp.
 */
final readonly class PlanStageFact
{
    public function __construct(
        public ExerciseMode $mode,
        public bool $correct,
        /** `Y-m-d` in the learner's own timezone. */
        public string $localDate,
    ) {}
}
