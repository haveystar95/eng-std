<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

final readonly class GetUserStats
{
    public function __construct(
        public UserId $userId,
        public DateTimeImmutable $now,
    ) {}
}
