<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** Learning progress cut by the language of the term (DECISIONS п. 139). */
final readonly class GetProgressByLanguage
{
    public function __construct(
        public UserId $userId,
        public DateTimeImmutable $now,
    ) {}
}
