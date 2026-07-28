<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** Reads how many generations a user has started on a given UTC day (for the daily cap). */
interface GenerationQuota
{
    public function usedOn(UserId $userId, DateTimeImmutable $day): int;
}
