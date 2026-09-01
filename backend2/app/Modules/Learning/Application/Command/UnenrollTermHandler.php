<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Service\EnrollmentPolicy;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * «Убрать из изучения»: clear `enrolled_at` and change nothing else.
 *
 * There is no branch here for «and also reset the ladder» or «and also drop the due date», and that
 * absence is the feature. The word stops being dealt; everything the learner earned on it stands.
 *
 * A pair with no row at all is not an error — it was never in the pool, so the request is already
 * satisfied. Returns whether this call was the one that changed anything, so the client can tell a
 * real removal from a replayed one.
 *
 * THE ONE THING IT REFUSES is a word an active or paused plan is standing on
 * ({@see EnrollmentPolicy}). The check is here rather than in the entity because it needs a fact
 * the entity does not have — which of this learner's plans are still holding — and because this is
 * the door the learner comes through. The plan's own release
 * ({@see \App\Modules\Learning\Application\Port\PlanTermArchiver}) does not come through here at
 * all: it takes away the REASON and never the enrolment.
 */
final readonly class UnenrollTermHandler
{
    public function __construct(
        private TermProgressRepository $progress,
        private PlanRepository $plans,
        private EnrollmentPolicy $policy,
        private TransactionManager $tx,
    ) {}

    /** @throws \App\Modules\Learning\Domain\Exception\PlanHeldTerm */
    public function __invoke(UnenrollTerm $command): bool
    {
        return $this->tx->run(function () use ($command): bool {
            $existing = $this->progress->findForUpdate($command->actorId, $command->termId);
            if ($existing === null || ! $existing->isEnrolled()) {
                return false;
            }

            // The plans query runs only for a pair that actually names one. Removing an ordinary
            // word is the overwhelming majority of calls and must not pay for a question about
            // plans it has no stake in.
            if ($existing->enrollmentSources()->planIds() !== []) {
                $this->policy->assertMayUnenroll(
                    $command->termId,
                    $existing->enrollmentSources(),
                    $this->plans->holdingPlanIdsFor($command->actorId),
                );
            }

            $this->progress->save($existing->unenroll());

            return true;
        });
    }
}
