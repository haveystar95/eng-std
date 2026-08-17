<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * How many practice dialogs the user has started on the UTC day containing $day. Counts every
 * dialog created that day (any status): starting one consumes the allowance; a mint that failed
 * never persisted a row, so it never counted.
 */
interface PracticeQuota
{
    public function usedOn(UserId $userId, DateTimeImmutable $day): int;
}
