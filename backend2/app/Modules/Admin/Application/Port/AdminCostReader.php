<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\CostBreakdown;
use App\Modules\Admin\Application\Dto\UserCostBreakdown;
use DateTimeImmutable;

/**
 * Aggregates the AI-spend ledgers (generation_requests, practice_dialogs, term_enrichments,
 * example_regenerations). A reporting read over financial rows; never mutates them.
 */
interface AdminCostReader
{
    /** Fleet spend by category. $since null = all time. */
    public function breakdownSince(?DateTimeImmutable $since): CostBreakdown;

    /** One user's spend by category. $since null = all time. */
    public function userBreakdownSince(string $userId, ?DateTimeImmutable $since): UserCostBreakdown;
}
