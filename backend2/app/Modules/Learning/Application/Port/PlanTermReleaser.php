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
}
