<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** Cards due for review now, ready to render (content hydrated from Vocabulary). */
final readonly class GetStudyCards
{
    public function __construct(
        public UserId $userId,
        public DateTimeImmutable $now,
        public int $sessionSize = 20,
        public int $newTermsPerDay = 10,
    ) {}
}
