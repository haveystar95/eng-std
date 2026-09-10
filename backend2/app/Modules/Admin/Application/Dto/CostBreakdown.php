<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * AI spend split by what produced it, in USD. Sums the recorded `cost_usd` of each spend ledger
 * (collection generation, learning plan, realtime practice, term enrichment, example regeneration)
 * over a window.
 *
 * `generation` and `plan` are reported apart on purpose: they are different products with
 * different budgets, and a single line that quietly contained both would be the mislabel this
 * split was made to avoid. The plan's number is the sum of the plan call (`plans.cost_usd_plan`)
 * and every lesson call (`plan_scenes.cost_usd_lesson`). The total contains both, because money
 * is money.
 */
final readonly class CostBreakdown
{
    public function __construct(
        public float $generation,
        public float $plan,
        public float $practice,
        public float $enrichment,
        public float $exampleRegen,
        public float $total,
    ) {}
}
