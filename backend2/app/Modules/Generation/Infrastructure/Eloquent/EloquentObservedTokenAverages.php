<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\ObservedTokenAverages;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Support\Facades\DB;

/**
 * Averages over the two places a real call's tokens are recorded: `term_enrichments` (the станок's
 * financial ledger) and `bakeoff_calls` (the sandbox). Both are read, because the interesting model
 * is often one production has not run yet but the bake-off has — which is exactly the case when a
 * cut-over is being priced.
 */
final readonly class EloquentObservedTokenAverages implements ObservedTokenAverages
{
    public function perCall(string $model, ?string $shape = null): ?array
    {
        $base = ModelCost::baseModel($model);

        $rows = [];
        foreach ($this->bakeoff($base, $shape) as $row) {
            $rows[] = $row;
        }
        // The станок's ledger has no shape column — it only ever recorded one kind of call — so a
        // shape-narrowed question is answered by the sandbox alone rather than by mixing the two.
        if ($shape === null) {
            foreach ($this->enrichments($base) as $row) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return null;
        }

        $in = 0;
        $out = 0;
        foreach ($rows as [$tokensIn, $tokensOut]) {
            $in += $tokensIn;
            $out += $tokensOut;
        }

        return [(int) round($in / count($rows)), (int) round($out / count($rows))];
    }

    /** @return list<array{0: int, 1: int}> */
    private function bakeoff(string $baseModel, ?string $shape): array
    {
        $query = DB::table('bakeoff_calls')
            ->where('ok', true)
            ->whereNotNull('tokens_in')
            ->whereNotNull('tokens_out');

        if ($shape !== null) {
            $query->where('shape', $shape);
        }

        $out = [];
        foreach ($query->get(['model', 'tokens_in', 'tokens_out']) as $row) {
            if (ModelCost::baseModel((string) $row->model) !== $baseModel) {
                continue;
            }
            $out[] = [(int) $row->tokens_in, (int) $row->tokens_out];
        }

        return $out;
    }

    /** @return list<array{0: int, 1: int}> */
    private function enrichments(string $baseModel): array
    {
        $out = [];
        foreach (DB::table('term_enrichments')
            ->whereNotNull('tokens_in')
            ->whereNotNull('tokens_out')
            ->get(['model', 'tokens_in', 'tokens_out']) as $row) {
            if (ModelCost::baseModel((string) $row->model) !== $baseModel) {
                continue;
            }
            $out[] = [(int) $row->tokens_in, (int) $row->tokens_out];
        }

        return $out;
    }
}
