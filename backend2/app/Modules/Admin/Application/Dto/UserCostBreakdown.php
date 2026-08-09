<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One user's AI spend by category. Enrichment is fleet-only (the enrichment ledger has no user_id),
 * so a user breakdown covers the categories that are attributable to them.
 */
final readonly class UserCostBreakdown
{
    public function __construct(
        public CostCategory $generation,
        public CostCategory $practice,
        public CostCategory $exampleRegen,
        public float $totalUsd,
    ) {}
}
