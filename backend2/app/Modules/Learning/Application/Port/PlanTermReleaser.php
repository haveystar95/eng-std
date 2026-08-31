<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Take one plan's claim off every pair it holds, in one statement.
 *
 * A port rather than a loop over the repository, because the honest implementation is a single
 * `UPDATE … SET enrollment_sources = enrollment_sources - 'plan:…'` over the learner's rows, and
 * loading three hundred progress entities to remove one string from each would be the same work
 * done three hundred times. The rule itself
 * ({@see \App\Modules\Learning\Domain\Service\EnrollmentPolicy::release()}) is still stated in the
 * Domain and unit-tested there; this is how it is applied in bulk.
 *
 * @return int how many pairs were released — the number the caller logs, and the number a test
 *             asserts on
 */
interface PlanTermReleaser
{
    public function releasePlan(UserId $userId, string $planId): int;

    /**
     * TAKE BACK OUT OF THE POOL what the plan put in and the learner never touched.
     *
     * {@see releasePlan()} deliberately keeps `enrolled_at`: the learner spent days on those words
     * and a plan ending is not a reason to stop studying them. That argument is about words they
     * WORKED ON. A plan also enrols fourteen cards a day the moment a day is written, and a plan
     * abandoned on day one leaves every one of them in the pool for ever — words from a
     * conversation that never happened, which the learner has not seen once.
     *
     * «Never touched» is exactly «no review row for this pair»: a review is written for every
     * answer of every kind, so a pair with none has never been dealt, let alone answered.
     *
     * Only pairs whose ONLY reason was this plan. A word the learner had also saved by hand keeps
     * its own reason and stays — that reason is theirs, and it did not go anywhere.
     *
     * MUST run before {@see releasePlan()}, which is what removes the marker this reads.
     *
     * @return int how many pairs left the pool
     */
    public function unenrolUntouched(UserId $userId, string $planId): int;
}
