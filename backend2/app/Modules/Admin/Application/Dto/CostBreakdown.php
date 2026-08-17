<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * AI spend split by what produced it, in USD. Sums the recorded `cost_usd` of each spend ledger
 * (collection generation, realtime practice, term enrichment, example regeneration) over a window.
 */
final readonly class CostBreakdown
{
    public function __construct(
        public float $generation,
        public float $practice,
        public float $enrichment,
        public float $exampleRegen,
        public float $total,
    ) {}
}
