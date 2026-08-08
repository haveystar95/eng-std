<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Reads the due-cards projection (`user_term_progress`) for session assembly. Backs the
 * hot query that runs at every session start, so implementations must use the
 * `(user_id, due_at) WHERE state <> 'new'` index.
 */
interface DueTermsReader
{
    /**
     * Terms whose interval has elapsed (`state <> 'new'`, `due_at <= now`), soonest first,
     * restricted to a set of term ids (the user's current collection terms).
     *
     * @param  list<string>  $termIds
     * @return list<DueTermView>
     */
    public function dueAmong(UserId $userId, DateTimeImmutable $now, array $termIds, int $limit): array;

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
