<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class SearchTerms
{
    public function __construct(
        public UserId $actorId,
        public string $query,
        public int $limit = 20,
    ) {}
}
