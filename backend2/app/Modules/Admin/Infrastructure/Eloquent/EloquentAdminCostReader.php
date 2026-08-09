<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\CostBreakdown;
use App\Modules\Admin\Application\Dto\CostCategory;
use App\Modules\Admin\Application\Dto\UserCostBreakdown;
use App\Modules\Admin\Application\Port\AdminCostReader;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sums the AI-spend ledgers. Reporting reads over financial rows (cross-cutting, like Observability);
 * imports no other module's classes.
 */
final class EloquentAdminCostReader implements AdminCostReader
{
    public function breakdownSince(?DateTimeImmutable $since): CostBreakdown
    {
        $generation = $this->sumCost('generation_requests', $since);
        $practice = $this->sumCost('practice_dialogs', $since);
        $enrichment = $this->sumCost('term_enrichments', $since);
        $exampleRegen = $this->sumCost('example_regenerations', $since);

        return new CostBreakdown(
            generation: $generation,
            practice: $practice,
            enrichment: $enrichment,
            exampleRegen: $exampleRegen,
            total: round($generation + $practice + $enrichment + $exampleRegen, 6),
        );
    }

    public function userBreakdownSince(string $userId, ?DateTimeImmutable $since): UserCostBreakdown
    {
        $generation = $this->category('generation_requests', $userId, $since);
        $practice = $this->category('practice_dialogs', $userId, $since);
        $exampleRegen = $this->category('example_regenerations', $userId, $since);

        return new UserCostBreakdown(
            generation: $generation,
            practice: $practice,
            exampleRegen: $exampleRegen,
            totalUsd: round($generation->costUsd + $practice->costUsd + $exampleRegen->costUsd, 6),
        );
    }

    private function sumCost(string $table, ?DateTimeImmutable $since): float
    {
        return round((float) DB::table($table)
            ->when($since !== null, fn (Builder $q): Builder => $q->where('created_at', '>=', $since))
            ->sum('cost_usd'), 6);
    }

    private function category(string $table, string $userId, ?DateTimeImmutable $since): CostCategory
    {
        $row = DB::table($table)
            ->where('user_id', $userId)
            ->when($since !== null, fn (Builder $q): Builder => $q->where('created_at', '>=', $since))
            ->selectRaw('COALESCE(SUM(tokens_in),0) AS ti, COALESCE(SUM(tokens_out),0) AS toO, COALESCE(SUM(cost_usd),0) AS c, COUNT(*) AS n')
            ->first();

        return new CostCategory(
            tokensIn: (int) ($row->ti ?? 0),
            tokensOut: (int) ($row->toO ?? 0),
            costUsd: round((float) ($row->c ?? 0), 6),
            count: (int) ($row->n ?? 0),
        );
    }
}
