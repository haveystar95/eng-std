<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\SyncCursor;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** A delta-sync request: `since` null + cursor null = full snapshot; cursor resumes pagination. */
final readonly class GetSyncDelta
{
    public function __construct(
        public UserId $userId,
        public ?DateTimeImmutable $since,
        public ?SyncCursor $cursor,
        public int $limit,
    ) {}
}
