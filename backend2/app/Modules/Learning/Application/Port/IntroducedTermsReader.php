<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * How many terms the user was introduced to (answered for the first time) on a given day.
 * Read from the daily stats projection so the session can cap new-term introductions.
 */
interface IntroducedTermsReader
{
    public function countForDay(UserId $userId, DateTimeImmutable $day): int;
}
