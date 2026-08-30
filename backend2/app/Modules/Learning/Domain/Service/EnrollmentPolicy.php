<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Exception\PlanHeldCollection;
use App\Modules\Learning\Domain\Exception\PlanHeldTerm;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * A6 — STRICTNESS. What a running plan will not let the learner take apart.
 *
 * The whole class is two questions and both have the same shape: is this thing load-bearing for a
 * plan that is still running? A plan is a promise with a date; a promise you can quietly dismantle
 * is a suggestion. So while a plan is `active` or `paused`, its words stay in the pool and its days
 * keep their collections.
 *
 * And the escape is never «force»: it is to END the plan. Pausing or abandoning releases every hold
 * in one act, so the learner is never stuck — they are asked to make the decision at the level it
 * actually belongs to, instead of taking one word out of a mechanism and finding out at the
 * conversation.
 *
 * A `paused` plan holds too, and that is deliberate rather than an oversight: pause means «I will
 * come back to this», and coming back to a plan whose words were let go one at a time is coming
 * back to a different plan.
 *
 * Pure. It is handed the facts — the sources on the pair, which plans are holding — and decides. It
 * never asks the database anything, which is what lets the same rule be checked in a unit test and
 * enforced at three different doors.
 */
final class EnrollmentPolicy
{
    /**
     * May this pair leave the pool?
     *
     * @param  list<string>  $holdingPlanIds  plans in a holding state ({@see
     *         \App\Modules\Learning\Domain\ValueObject\PlanStatus::holdsTerms()}) — the caller
     *         resolves the set; this decides.
     *
     * @throws PlanHeldTerm
     */
    public function assertMayUnenroll(TermId $termId, EnrollmentSources $sources, array $holdingPlanIds): void
    {
        $held = array_values(array_intersect($sources->planIds(), $holdingPlanIds));

        if ($held !== []) {
            throw PlanHeldTerm::make($termId, $held);
        }
    }

    /**
     * The same question about a day's collection.
     *
     * @throws PlanHeldCollection
     */
    public function assertMayDeleteCollection(string $collectionId, ?string $holdingPlanId): void
    {
        if ($holdingPlanId !== null) {
            throw PlanHeldCollection::make($collectionId, $holdingPlanId);
        }
    }

    /**
     * Ending a plan RELEASES its hold: the source comes off the pair and the word stays in the pool
     * as an ordinary word.
     *
     * Note what does NOT happen — the pair is not unenrolled. The learner spent days on these words
     * and they have a rung, a schedule and a history; a plan finishing is not a reason to stop
     * studying them, it is a reason to stop refusing to. A pair whose ONLY reason was the plan
     * keeps studying too, and the learner can now remove it if they want to, which is the whole
     * point of releasing rather than deleting.
     */
    public function release(EnrollmentSources $sources, string $planId): EnrollmentSources
    {
        return $sources->without(EnrollmentSources::forPlan($planId));
    }
}
