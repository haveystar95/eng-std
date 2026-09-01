<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\PlanArchiveSweepView;
use App\Modules\Learning\Application\Port\PlanTermSweepStore;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * ONE-OFF SWEEP: the words of plans that ended BEFORE an ending started taking its words with it.
 *
 * From 01.09 an ending archives its plan — the plan's reason comes off every pair it claimed and a
 * pair with no other reason leaves the pool ({@see \App\Modules\Learning\Application\Port\PlanTermArchiver}).
 * Every plan that ended before that ruling did the opposite: it released its words into the ordinary
 * queue and left them there. On the owner's own base that is «Отдых в Италии», abandoned, whose
 * words have been arriving in «Повторить» ever since — and one of which turned up as a card inside
 * another plan's lesson (DECISIONS пп. 212, 214).
 *
 * ## «Origin is an ended plan» is asked TWO ways, and both are needed
 *
 * The obvious way is the pair's own `enrollment_sources`. For many of these rows it is empty: the
 * old release removed the `plan:` marker on the way out, and before PLAN-FIX-3 the scheduler wiped
 * the whole list on a word's first answer. So the second way is the ARCHIVE, which nothing has ever
 * written over — the plan's days, each owning a collection, read through Collections.
 *
 * Neither is a superset of the other. The owner's «Отдых в Италии» words are found only
 * structurally; six rows of another learner are found only by their marker, because their word had
 * since been taken off the day's collection. Reading only the archive left those six behind on the
 * first live run.
 *
 * ## What it refuses to touch
 *
 *  * a pair carrying `manual` or `triage`: the learner has their own reason for it, and that reason
 *    did not end with anybody's plan;
 *  * a pair any of that learner's still-running plans stands on — the same word can be in two plans,
 *    and one of them is not over;
 *  * anything but `enrolled_at` and the reasons beside it. No review is deleted, no plan row is
 *    touched, no term leaves any collection. The word stops being in the QUEUE; everything that ever
 *    happened to it stays readable, so adding it back to «Учить» resumes rather than restarts.
 */
final readonly class ArchiveEndedPlanTermsHandler
{
    /** A plan day holds a day's worth of cards; this is far above any of them. */
    private const TERMS_PER_COLLECTION = 500;

    private const ENDED = ['completed', 'abandoned'];

    private const RUNNING = ['draft', 'active', 'paused'];

    public function __construct(
        private PlanTermSweepStore $store,
        private UserCollectionTermsReader $collectionTerms,
    ) {}

    public function __invoke(ArchiveEndedPlanTerms $command): PlanArchiveSweepView
    {
        $ended = $this->store->plansByStatus(self::ENDED);
        if ($ended === []) {
            return new PlanArchiveSweepView([], 0, 0);
        }

        // Read once per learner, not once per plan: a word held by ANY running plan of theirs is a
        // word one of their plans is still standing on, whichever ended plan also taught it.
        $held = [];
        foreach ($this->store->plansByStatus(self::RUNNING) as $plan) {
            foreach ($this->termsOf($plan['user_id'], $plan['plan_id']) as $termId) {
                $held[$plan['user_id']][$termId] = true;
            }
        }

        $rows = [];
        /** @var array<string, array<string, true>> $pairs user id => set of term ids */
        $pairs = [];

        foreach ($ended as $plan) {
            $userId = $plan['user_id'];
            $taken = array_values(array_filter(
                $this->store->archivableTerms(
                    UserId::fromString($userId),
                    EnrollmentSources::forPlan($plan['plan_id']),
                    $this->termsOf($userId, $plan['plan_id']),
                ),
                static fn (string $termId): bool => ! isset($held[$userId][$termId]),
            ));

            if ($taken === []) {
                continue;
            }

            $rows[] = ['user_id' => $userId, 'title' => $plan['title'], 'status' => $plan['status'], 'pairs' => count($taken)];
            foreach ($taken as $termId) {
                $pairs[$userId][$termId] = true;
            }
        }

        $distinct = array_sum(array_map('count', $pairs));

        if (! $command->apply) {
            return new PlanArchiveSweepView($rows, $distinct, 0);
        }

        $affected = 0;
        foreach ($pairs as $userId => $terms) {
            $affected += $this->store->unenroll(UserId::fromString($userId), array_keys($terms));
        }

        return new PlanArchiveSweepView($rows, $distinct, $affected);
    }

    /**
     * Every term the days of one plan stand on — through Collections, never by joining its tables.
     *
     * @return list<string>
     */
    private function termsOf(string $userId, string $planId): array
    {
        $actor = UserId::fromString($userId);
        $ids = [];

        foreach ($this->store->dayCollectionIds($planId) as $collectionId) {
            foreach ($this->collectionTerms->termIdsForCollection($actor, $collectionId, self::TERMS_PER_COLLECTION) as $termId) {
                $ids[$termId] = true;
            }
        }

        return array_keys($ids);
    }
}
