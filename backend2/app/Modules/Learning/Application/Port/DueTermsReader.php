<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Reads the selectable-cards projection (`user_term_progress`) for session assembly. Backs the
 * hot query that runs at every session start, so implementations must use the partial indexes
 * added with the acquisition ladder.
 */
interface DueTermsReader
{
    /**
     * Every pair the trainer may show right now, within a set of term ids (the user's current
     * collection terms). There are TWO reasons a pair qualifies, and they are different in kind:
     *
     *  * ON THE LADDER (`acquisition <> 'graduated'`) — unfinished. It has no `due_at`, because the
     *    recognition rungs never schedule; it is selectable because it is half-taught, and it is
     *    the most urgent thing the trainer has.
     *  * SCHEDULABLE (`acquisition = 'graduated'`) — either due (`due_at <= now`) or never yet
     *    scheduled (`due_at IS NULL`): a pair that has just graduated off the ladder is owed its
     *    first SRS review, and so is a pair returned from `known` to `new`.
     *
     * Ordered `due_at NULLS FIRST`, which puts the unfinished ahead of the overdue and both ahead
     * of the merely due, then soonest first within them.
     *
     * @param  list<string>  $termIds
     * @return list<DueTermView>
     */
    public function selectableAmong(UserId $userId, DateTimeImmutable $now, array $termIds, int $limit): array;

    /**
     * Every progress row the user has among a set of term ids — ANY state, ignoring `due_at`.
     * Backs free practice, which drills whatever is in the scope on demand, so it deliberately
     * skips the due/state filters (a studied-but-not-due term is exactly what practice is for).
     * Terms with no row are absent from the result — the caller fills them in as new (reps 0).
     *
     * @param  list<string>  $termIds
     * @return list<DueTermView>
     */
    public function allAmong(UserId $userId, array $termIds, int $limit): array;
}
