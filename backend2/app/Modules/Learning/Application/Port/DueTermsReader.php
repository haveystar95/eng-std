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
}
