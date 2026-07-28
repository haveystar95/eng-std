<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class ListUserCollections
{
    public function __construct(
        public UserId $userId,
        public ?string $cursor = null,
        public int $limit = 30,
    ) {}
}
