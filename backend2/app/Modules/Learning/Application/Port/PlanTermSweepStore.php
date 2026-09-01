<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The reads and the one write the ENDED-PLAN SWEEP needs, over this module's own tables.
 *
 * A port with four methods for a one-off maintenance command looks like ceremony, and it is not: the
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
     * Pairs of this learner that are IN THE POOL, have no reason of the learner's own, and belong to
     * the plan — either because they still carry its enrolment marker, or because they stand on one
     * of the terms it taught.
     *
     * Both halves are needed and neither contains the other: the marker is erased on some rows (the
     * old release removed it, and the scheduler used to wipe it on a word's first answer), and the
     * term list misses a word that has since been taken off the day's collection.
     *
     * @param  string  $enrollmentSource  the plan's marker, spelled by
     *         {@see \App\Modules\Learning\Domain\ValueObject\EnrollmentSources::forPlan()}
     * @param  list<string>  $termIds  what the plan's days stand on, read through Collections
     * @return list<string>
     */
    public function archivableTerms(UserId $userId, string $enrollmentSource, array $termIds): array;

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
