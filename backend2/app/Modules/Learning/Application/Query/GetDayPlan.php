<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Simulate one user's study day on a target calendar date (their timezone): what the trainer would
 * serve if they opened it that day. A read-only dry run — see {@see GetDayPlanHandler}.
 */
final readonly class GetDayPlan
{
    public function __construct(
        public UserId $userId,
        public ?string $date = null,     // Y-m-d in the user's timezone; null = today
        public int $sessionSize = 100,   // upper bound on cards considered
    ) {}
}
