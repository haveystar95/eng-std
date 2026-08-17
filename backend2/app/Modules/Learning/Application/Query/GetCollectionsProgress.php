<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** Per-collection learning progress for a user's own collections. */
final readonly class GetCollectionsProgress
{
    public function __construct(
        public UserId $userId,
        public DateTimeImmutable $now,
    ) {}
}
