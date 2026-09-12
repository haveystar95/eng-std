<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** The append-only log of the learner's visits (`user_visits`). No update, no delete — the user FK cascade is the only way out. */
interface VisitLog
{
    /**
     * The last recorded visit, read with the user's row locked for the transaction — so two
     * foreground pings arriving together cannot both pass the throttle.
     */
    public function lastVisitAtForUpdate(UserId $user): ?DateTimeImmutable;

    public function append(UserId $user, DateTimeImmutable $visitedAt): void;

    /** @return list<DateTimeImmutable> newest first, UTC */
    public function latest(UserId $user, int $limit): array;
}
