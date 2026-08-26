<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * The home screen's day for one learner, at one moment. Read-only: producing it writes nothing, so
 * opening the app never costs a word its quota or a session its composition.
 */
final readonly class GetHomePlan
{
    public function __construct(
        public UserId $userId,
        public DateTimeImmutable $now,
    ) {}
}
