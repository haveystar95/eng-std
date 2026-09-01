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
 * ## Three shapes, not one
 *
 * A plan can be `completed`, `abandoned` — or GONE, with no row in `learning_plans` and therefore no
 * status to be found by. The third shape is real: 23 pairs on the owner's base carried a marker
 * naming a plan that no longer exists, none of them ever answered, sitting in the ordinary queue
 * with a reason that points nowhere. They leave the pool for the same reason the other two do, and
 * they are found by {@see PlanTermSweepStore::orphanMarkerPairs()} rather than by a status.
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

    /** The report's two shapes: everything left the pool, or some of it only lost the marker. */
    private const GONE = 'снято с лестницы';

    private const MIXED = 'снято + метка снята';

    public function __construct(
        private PlanTermSweepStore $store,
        private UserCollectionTermsReader $collectionTerms,
    ) {}

    public function __invoke(ArchiveEndedPlanTerms $command): PlanArchiveSweepView
    {
        $plans = $this->store->plansByStatus([...self::ENDED, ...self::RUNNING]);
        $names = [];
        $over = [];
        foreach ($plans as $plan) {
            $names[$plan['plan_id']] = $plan['title'];
            if (in_array($plan['status'], self::ENDED, true)) {
                $over[$plan['plan_id']] = $plan['status'];
            }
        }

        $rows = [];
        $unenrolled = 0;
        $stripped = 0;

        foreach ($this->learners($plans) as $userId) {
            $actor = UserId::fromString($userId);

            // What a still-running plan of theirs is standing on. A word can be in two plans, and
            // one of them is not over.
            $held = [];
            foreach ($plans as $plan) {
                if ($plan['user_id'] === $userId && ! isset($over[$plan['plan_id']])) {
                    foreach ($this->termsOf($userId, $plan['plan_id']) as $termId) {
                        $held[$termId] = true;
                    }
                }
            }

            // …and what an ENDED plan of theirs taught, for the rows whose marker was erased.
            $taught = [];
            foreach ($plans as $plan) {
                if ($plan['user_id'] === $userId && isset($over[$plan['plan_id']])) {
                    foreach ($this->termsOf($userId, $plan['plan_id']) as $termId) {
                        $taught[$termId] = $plan['plan_id'];
                    }
                }
            }

            $leaving = [];
            $keeping = [];

            foreach ($this->store->pooledPairsWithPlanMarker($actor) as $pair) {
                if (isset($held[$pair['term_id']])) {
                    continue;
                }
                $planIds = EnrollmentSources::fromArray($pair['sources'])->planIds();
                // A marker naming a plan that is neither running nor even present is DEAD: the plan
                // is over or gone, and either way it will never deal this word again.
                //
                // Kept as MARKERS and not as plan ids, because that is what comes off the row.
                // `planIds()` strips the prefix and `forPlan()` puts it back — carrying the naked id
                // around and remembering to re-dress it at the write is how the first draft
                // subtracted a string that was not in the array and reported a success.
                $dead = array_values(array_map(
                    static fn (string $planId): string => EnrollmentSources::forPlan($planId),
                    array_filter(
                        $planIds,
                        static fn (string $planId): bool => ! isset($names[$planId]) || isset($over[$planId]),
                    ),
                ));
                if ($dead === []) {
                    continue;
                }

                // THE ONE RULE. Was the plan its only reason to be in the queue? Then it leaves the
                // queue. Was there another — saved by hand, or swiped «не знаю» — then that reason is
                // the learner's own, it did not end with anybody's plan, and only the marker goes.
                $other = count($pair['sources']) - count($planIds);
                $live = count($planIds) - count($dead);

                if ($other > 0 || $live > 0) {
                    $keeping[$pair['term_id']] = $dead;
                } else {
                    $leaving[$pair['term_id']] = $dead;
                }
            }

            // The rows with NO marker at all, found by what the ended plan taught. No marker means no
            // recorded reason of any kind, so there is nothing to keep them in the queue for.
            foreach ($this->store->pooledPairsWithoutMarker($actor, array_keys($taught)) as $pair) {
                if (isset($held[$pair['term_id']]) || $pair['sources'] !== []) {
                    continue;
                }
                $leaving[$pair['term_id']] = [EnrollmentSources::forPlan($taught[$pair['term_id']])];
            }

            if ($leaving === [] && $keeping === []) {
                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'title' => $this->naming($names, $leaving, $keeping),
                'status' => count($keeping) > 0 ? self::MIXED : self::GONE,
                'pairs' => count($leaving),
            ];

            if (! $command->apply) {
                $unenrolled += count($leaving);
                $stripped += count($keeping);

                continue;
            }

            $unenrolled += $this->store->unenroll($actor, array_keys($leaving));
            foreach ($this->groupByMarkers($keeping) as $markers => $termIds) {
                $stripped += $this->store->stripSources($actor, $termIds, explode("\n", (string) $markers));
            }
        }

        return new PlanArchiveSweepView($rows, $unenrolled, $stripped);
    }

    /**
     * The plans named in one learner's report line — a handful of titles, or the markers themselves
     * when the plan row is gone and there is no title left to print.
     *
     * @param  array<string, string>  $names
     * @param  array<string, list<string>>  $leaving
     * @param  array<string, list<string>>  $keeping
     */
    private function naming(array $names, array $leaving, array $keeping): string
    {
        $titles = [];
        foreach ([...array_values($leaving), ...array_values($keeping)] as $markers) {
            foreach ($markers as $marker) {
                $planId = EnrollmentSources::fromArray([$marker])->planIds()[0] ?? $marker;
                $titles[$names[$planId] ?? $marker] = true;
            }
        }

        return implode(', ', array_keys($titles));
    }

    /**
     * @param  array<string, list<string>>  $keeping  term id => the markers to take off it
     * @return array<string, list<string>>  markers (newline-joined) => term ids sharing exactly them
     */
    private function groupByMarkers(array $keeping): array
    {
        $out = [];
        foreach ($keeping as $termId => $markers) {
            sort($markers);
            $out[implode("\n", $markers)][] = (string) $termId;
        }

        return $out;
    }

    /**
     * Everyone the sweep has to look at: owners of a plan, PLUS anyone holding a plan marker.
     *
     * The second half is not redundant. A learner whose plan row has been deleted owns no plan and
     * is invisible to the first half — and fourteen of their words were still in the queue under
     * that plan's name, which is exactly the population this sweep exists for.
     *
     * @param  list<array{user_id: string, plan_id: string, title: string, status: string}>  $plans
     * @return list<string>
     */
    private function learners(array $plans): array
    {
        $ids = [];
        foreach ($plans as $plan) {
            $ids[$plan['user_id']] = true;
        }
        foreach ($this->store->learnersWithPlanMarker() as $userId) {
            $ids[$userId] = true;
        }

        return array_keys($ids);
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
