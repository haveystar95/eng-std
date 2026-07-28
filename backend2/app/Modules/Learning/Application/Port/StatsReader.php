<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\StatsView;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** Reads the user's aggregate learning stats (counts over progress + daily projection). */
interface StatsReader
{
    public function read(UserId $userId, DateTimeImmutable $now): StatsView;
}
