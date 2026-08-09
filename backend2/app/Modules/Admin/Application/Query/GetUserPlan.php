<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Ask the day-simulator what a user's study day looks like on $date (their timezone; null = today). */
final readonly class GetUserPlan
{
    public function __construct(
        public UserId $userId,
        public ?string $date = null,
    ) {}
}
