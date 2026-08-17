<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * The user's (user, term) progress rows changed in (since, upper]; `since` null = all of them
 * (full snapshot). Ordered by (updated_at, term_id) for deterministic offset paging.
 */
interface ProgressSyncReader
{
    /** @return list<ProgressSyncRow> */
    public function changedProgress(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;
}
