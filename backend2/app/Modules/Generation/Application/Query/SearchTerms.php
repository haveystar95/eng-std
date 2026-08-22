<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class SearchTerms
{
    public function __construct(
        public UserId $actorId,
        public string $query,
        /** The pair the pill is set to. Null falls back to the learner's profile pair. */
        public ?string $source = null,
        public ?string $target = null,
        public int $limit = 20,
    ) {}
}
