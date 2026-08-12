<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\CostByPurposeView;
use App\Modules\Admin\Application\Dto\CostBreakdown;
use App\Modules\Admin\Application\Dto\CostCategory;
use App\Modules\Admin\Application\Dto\PurposeCost;
use App\Modules\Admin\Application\Dto\UserCostBreakdown;
use App\Modules\Admin\Application\Port\AdminCostReader;
use App\Modules\Shared\Domain\Service\ModelCost;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sums the AI-spend ledgers. Reporting reads over financial rows (cross-cutting, like Observability);
 * imports no other module's classes.
 */
final class EloquentAdminCostReader implements AdminCostReader
{
    public function __construct(private readonly ModelCost $cost) {}

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

    public function collectionCost(string $collectionId): ?CostByPurposeView
    {
        if (DB::table('collections')->where('id', $collectionId)->doesntExist()) {
            return null;
        }

        // Terms live in many collections, so a shared term's enrichment is counted in each one that
        // holds it. Said out loud in `note` rather than quietly summed — the alternative (splitting
        // a term's cost N ways) makes every collection's number depend on unrelated collections.
        $items = fn (): Builder => DB::table('collection_items')
            ->where('collection_id', $collectionId)
            ->whereNull('deleted_at');

        $parts = [
            $this->ledgerPurpose('generation', DB::table('generation_requests')->where('collection_id', $collectionId)),
            $this->ledgerPurpose('realtime', DB::table('practice_dialogs')->where('collection_id', $collectionId)),
            $this->ledgerPurpose('enrichment', DB::table('term_enrichments')
                ->whereIn('term_id', $items()->select('term_id'))),
            $this->ledgerPurpose('example_regen', DB::table('example_regenerations')
                ->whereIn('term_id', $items()->select('term_id'))),
            $this->logPurpose('images', $collectionId),
            $this->logPurpose('recap', $collectionId),
        ];

        return CostByPurposeView::of(
            $parts,
            scopeId: $collectionId,
            note: 'enrichment and example_regen are attributed per term, so a term shared with '
                . 'another collection is counted in both; images and recap are priced from the '
                . 'request log (they have no spend ledger).',
        );
    }

    public function costByPurposeSince(?DateTimeImmutable $since, ?string $period): CostByPurposeView
    {
        $window = fn (Builder $q): Builder => $since === null ? $q : $q->where('created_at', '>=', $since);

        $parts = [
            $this->ledgerPurpose('generation', $window(DB::table('generation_requests'))),
            $this->ledgerPurpose('realtime', $window(DB::table('practice_dialogs'))),
            $this->ledgerPurpose('enrichment', $window(DB::table('term_enrichments'))),
            $this->ledgerPurpose('example_regen', $window(DB::table('example_regenerations'))),
            $this->logPurpose('images', null, $since),
            $this->logPurpose('recap', null, $since),
        ];

        return CostByPurposeView::of(
            $parts,
            period: $period,
            since: $since?->format(DATE_ATOM),
            note: 'images and recap are priced from the request log; every other purpose comes '
                . 'from its own spend ledger.',
        );
    }

    public function totalsForCollections(array $collectionIds): array
    {
        if ($collectionIds === []) {
            return [];
        }

        $totals = array_fill_keys($collectionIds, 0.0);

        // Only the two direct links matter for a list column: per-term attribution would mean a
        // join per row, and the detail view is where the full split belongs.
        foreach (['generation_requests', 'practice_dialogs'] as $table) {
            $rows = DB::table($table)
                ->whereIn('collection_id', $collectionIds)
                ->groupBy('collection_id')
                ->selectRaw('collection_id, COALESCE(SUM(cost_usd),0) AS c')
                ->get();
            foreach ($rows as $r) {
                $id = (string) $r->collection_id;
                $totals[$id] = round(($totals[$id] ?? 0.0) + (float) $r->c, 6);
            }
        }

        return $totals;
    }

    /** Sum a spend ledger (tokens + cost + row count) into one purpose bucket. */
    private function ledgerPurpose(string $purpose, Builder $query): PurposeCost
    {
        $row = $query
            ->selectRaw('COALESCE(SUM(tokens_in),0) AS ti, COALESCE(SUM(tokens_out),0) AS tout, COALESCE(SUM(cost_usd),0) AS c, COUNT(*) AS n')
            ->first();

        return new PurposeCost(
            purpose: $purpose,
            tokensIn: (int) ($row->ti ?? 0),
            tokensOut: (int) ($row->tout ?? 0),
            costUsd: round((float) ($row->c ?? 0), 6),
            calls: (int) ($row->n ?? 0),
        );
    }

    /**
     * Purposes with no ledger: sum the token counts out of the stored responses and price them with
     * the shared table. Pexels reports no usage block, so images come back as calls with zero cost —
     * which is the truth, not a gap.
     */
    private function logPurpose(string $purpose, ?string $collectionId, ?DateTimeImmutable $since = null): PurposeCost
    {
        $q = DB::table('api_request_logs')->where('purpose', $purpose);
        if ($collectionId !== null) {
            $q->where('collection_id', $collectionId);
        }
        if ($since !== null) {
            $q->where('occurred_at', '>=', $since);
        }

        $rows = $q->selectRaw(
            "COALESCE(response_body->>'model', request_body->>'model') AS model, "
            . "COALESCE(response_body->'usage'->>'prompt_tokens', response_body->'usage'->>'input_tokens') AS ti, "
            . "COALESCE(response_body->'usage'->>'completion_tokens', response_body->'usage'->>'output_tokens') AS tout"
        )->get();

        $tokensIn = 0;
        $tokensOut = 0;
        $cost = 0.0;
        foreach ($rows as $r) {
            $in = is_numeric($r->ti) ? (int) $r->ti : null;
            $out = is_numeric($r->tout) ? (int) $r->tout : null;
            $tokensIn += $in ?? 0;
            $tokensOut += $out ?? 0;

            $model = is_string($r->model) ? $r->model : null;
            $priced = $model !== null ? $this->cost->estimate($model, $in, $out) : null;
            $cost += $priced !== null ? (float) $priced : 0.0;
        }

        return new PurposeCost($purpose, $tokensIn, $tokensOut, round($cost, 6), $rows->count());
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
