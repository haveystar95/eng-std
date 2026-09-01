<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * AN ENDED PLAN TAKES ITS WORDS WITH IT.
 *
 * This port used to be `PlanTermReleaser`, and it did the opposite: finishing or abandoning a plan
 * took the plan's claim off every pair and LEFT them in the pool as ordinary words — «18 слов ушли в
 * общее повторение». The reasoning was that the learner had spent days on them.
 *
 * The owner's ruling (01.09) is that this is not what a plan is. A plan is a course with a subject
 * and an end date: its words were learned FOR the interview, the doctor's appointment, the flat.
 * When it ends it becomes an ARCHIVE — the days, the cards and the results are all kept and can be
 * read back — and its vocabulary does not silently become the learner's daily queue. Fourteen words
 * a day for a week is a hundred cards arriving in «Повторить» under no heading anybody chose. What
 * a learner wants out of a finished plan they add to «Учить» themselves, deliberately, off the
 * archive screen (not in this наряд).
 *
 * So «archive» here means exactly one thing about the POOL: take this plan's reason off every pair
 * it claimed, and take out of the pool every pair that then has no reason left to be there. A word
 * the learner had ALSO saved by hand keeps its own reason and stays — that reason is theirs and it
 * did not end with the plan.
 *
 * Nothing else moves. `enrolled_at` going null is the same «пауза» the pool has always had
 * ({@see \App\Modules\Learning\Domain\Entity\TermProgress::unenroll()}): the rung, the schedule and
 * the whole review log stay where they are, so a word added back later resumes rather than restarts.
 *
 * A port rather than a loop over the repository, because the honest implementation is a single
 * statement over the learner's rows, and loading three hundred progress entities to remove one
 * string from each would be the same work done three hundred times.
 */
interface PlanTermArchiver
{
    /**
     * @return int how many pairs left the pool — the number the caller logs and a test asserts on
     */
    public function archivePlan(UserId $userId, string $planId): int;
}
