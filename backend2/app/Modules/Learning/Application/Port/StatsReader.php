<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\StatsView;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Reads the user's aggregate learning stats: term counts over progress, plus activity (active days
 * + streak) computed from the append-only review log in the user's calendar timezone.
 */
interface StatsReader
{
    public function read(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz): StatsView;
}
