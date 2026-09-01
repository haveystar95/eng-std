<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The reads and the one write the ENDED-PLAN SWEEP needs, over this module's own tables.
 *
 * A port this wide for a one-off maintenance command looks like ceremony, and it is not: the
 * sweep has to combine a fact only Collections can answer (which terms stand on a day's collection)
 * with facts only Learning holds (which plans ended, which pairs are enrolled and why). The place
 * where those two meet is `Application` — it is the only layer allowed to hold both — and Application
 * does not touch the database. So the Learning half comes through here.
 *
 * The alternative was one query joining `learning_plans` to `collection_items`, which is the shortcut
 * the module boundary exists to refuse, and which the invariant review caught on the first draft.
 *
 * @see \App\Modules\Learning\Application\Command\ArchiveEndedPlanTermsHandler
 */
interface PlanTermSweepStore
{
    /**
     * @param  list<string>  $statuses
     * @return list<array{user_id: string, plan_id: string, title: string, status: string}>
     *         oldest first, so a report reads in the order the learner lived it
     */
    public function plansByStatus(array $statuses): array;

    /**
     * The collections this plan's days own. A day whose generation never landed has none.
     *
     * @return list<string>
     */
    public function dayCollectionIds(string $planId): array;

    /**
     * Everyone who has a pair in the pool carrying a `plan:` marker.
     *
     * Read separately from the plan table, and that is the whole point: a learner whose plan ROW has
     * been deleted owns no plan and would never appear in a list derived from `learning_plans` — but
     * fourteen of their words still sit in the queue under that plan's name. Found this way, they
     * are swept like everybody else.
     *
     * @return list<string>
     */
    public function learnersWithPlanMarker(): array;

    /**
     * Every pair of this learner that is IN THE POOL and carries at least one `plan:` marker, with
     * all of its reasons.
     *
     * The reasons come back whole because the rule is about the WHOLE list: a pair whose only reason
     * is a plan marker leaves the pool, and a pair that has another reason keeps its place and loses
     * only the marker. Deciding that needs the list, not a predicate over it.
     *
     * @return list<array{term_id: string, sources: list<string>}>
     */
    public function pooledPairsWithPlanMarker(UserId $userId): array;

    /**
     * The same, for pairs that carry NO marker at all but stand on a term the plan taught.
     *
     * This is the half that finds words whose provenance was erased — the old release removed the
     * marker on the way out, and before PLAN-FIX-3 the scheduler wiped the whole list on a word's
     * first answer. Rows already returned by {@see pooledPairsWithPlanMarker()} are not repeated.
     *
     * @param  list<string>  $termIds  what an ended plan's days stand on, read through Collections
     * @return list<array{term_id: string, sources: list<string>}>
     */
    public function pooledPairsWithoutMarker(UserId $userId, array $termIds): array;

    /**
     * Take these markers off these pairs and leave them in the pool.
     *
     * The other half of the one rule: the plan's reason is over, another reason of the learner's own
     * is not, and the pair stays exactly where it was minus the reason that ended.
     *
     * @param  list<string>  $termIds
     * @param  list<string>  $sources  the markers to remove
     * @return int how many rows were written
     */
    public function stripSources(UserId $userId, array $termIds, array $sources): int;

    /**
     * Take these pairs out of the pool: `enrolled_at` to null, reasons cleared, nothing else.
     *
     * The rung, the schedule and the whole review log stay, so a word added back to «Учить» later
     * resumes rather than restarts.
     *
     * @param  list<string>  $termIds
     * @return int how many rows were written
     */
    public function unenroll(UserId $userId, array $termIds): int;
}
