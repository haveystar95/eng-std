<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\CostByPurposeView;
use App\Modules\Admin\Application\Dto\CostBreakdown;
use App\Modules\Admin\Application\Dto\UserCostBreakdown;
use DateTimeImmutable;

/**
 * Aggregates the AI-spend ledgers (generation_requests, practice_dialogs, term_enrichments,
 * example_regenerations). A reporting read over financial rows; never mutates them.
 *
 * The LEDGERS are the money, not the request log: they carry every row ever written, and the log
 * can be pruned. The log covers only the two purposes that have no ledger of their own — images
 * (free, but worth counting) and recap (whose spend nobody records) — priced from the token counts
 * in the response we stored.
 */
interface AdminCostReader
{
    /** Fleet spend by category. $since null = all time. */
    public function breakdownSince(?DateTimeImmutable $since): CostBreakdown;

    /** One user's spend by category. $since null = all time. */
    public function userBreakdownSince(string $userId, ?DateTimeImmutable $since): UserCostBreakdown;

    /** What one collection has cost so far, split by purpose. Null when there is no such collection. */
    public function collectionCost(string $collectionId): ?CostByPurposeView;

    /**
     * Fleet spend by purpose over a window. $since null = all time.
     *
     * @param  ?string  $period  the label to echo back (day|week|month|all)
     */
    public function costByPurposeSince(?DateTimeImmutable $since, ?string $period): CostByPurposeView;

    /**
     * Per-collection totals for a set of collections — the "cost" column of the collections list,
     * in one query instead of N.
     *
     * @param  list<string>  $collectionIds
     * @return array<string, float>  collection id → USD
     */
    public function totalsForCollections(array $collectionIds): array;
}
