<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * What to study now for one user: due cards, then new cards to fill the session up to
 * the remaining daily new-term quota.
 */
final readonly class GetDueTerms
{
    public function __construct(
        public UserId $userId,
        public DateTimeImmutable $now,
        public int $sessionSize = 20,
        public int $newTermsRemaining = 10,
    ) {}
}
