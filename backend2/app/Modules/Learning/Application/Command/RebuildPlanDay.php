<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «СОБРАТЬ ЗАНОВО» — the one move a learner has when a day has burned.
 *
 * A day gets two paid calls ({@see \App\Modules\Learning\Domain\Entity\PlanDay::MAX_ATTEMPTS}) and
 * then it is `failed`, which until now meant the plan was over: the only button on the screen was
 * «Собрать план заново», which throws away every day the learner has already walked and buys a new
 * skeleton. The owner's live plan of 02.09 stopped exactly there — day 1 passed, day 2 refused by
 * `card.translation_missing_key`, and no way forward that did not discard day 1.
 *
 * So: one more attempt, on request, for the one day that failed. It is the operator action
 * {@see \App\Modules\Learning\Domain\Entity\PlanDay::reopenForRetry()} was written for, wired to a
 * button — deliberate, per day, and never automatic, because the two-attempt cap exists so that a
 * broken prompt cannot spend a plan's budget on one day, and a retry the learner did not ask for
 * would be that cap with a hole in it.
 */
final readonly class RebuildPlanDay
{
    public function __construct(
        public UserId $actorId,
        public string $planId,
        public int $dayIndex,
    ) {}
}
