<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\TriageSyncRow;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Reads the governing triage verdict per term for the delta feed. The governing verdict is the
 * row with the greatest client_seq (monotonic per user, so also the latest received) — the same
 * rule the projection uses. A term is included when that governing row was received in
 * (since, upper]; `since` null yields every currently-triaged term (full snapshot). Ordered by
 * (received time, term_id) so the concatenated sync stream pages deterministically.
 */
interface TriageSyncReader
{
    /** @return list<TriageSyncRow> */
    public function changedTriages(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;
}
