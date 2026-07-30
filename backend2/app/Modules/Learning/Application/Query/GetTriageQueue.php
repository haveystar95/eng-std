<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** The first-pass swipe queue for one collection: its not-yet-triaged, not-yet-studied terms. */
final readonly class GetTriageQueue
{
    public function __construct(
        public UserId $userId,
        public string $collectionId,
        public int $limit = 40,
    ) {}
}
