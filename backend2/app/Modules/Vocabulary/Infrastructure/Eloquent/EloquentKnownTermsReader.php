<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Query\KnownTermsReader;
use Illuminate\Support\Facades\DB;

/**
 * Reads across to `learning_plan_days` and `collection_items` — a reporting projection, the same
 * shape Admin uses, and no other module's Application is called to get it. The alternative would be
 * three cross-module round trips to answer one question that is one join.
 */
final readonly class EloquentKnownTermsReader implements KnownTermsReader
{
    public function metInPlan(string $planId, string $targetLang, string $supportLang): array
    {
        $rows = DB::table('learning_plan_days as d')
            ->join('collection_items as ci', 'ci.collection_id', '=', 'd.collection_id')
            ->join('terms as t', 't.id', '=', 'ci.term_id')
            ->where('d.plan_id', $planId)
            ->whereNotNull('d.collection_id')
            ->where('t.lang', $targetLang)
            ->whereNull('t.deleted_at')
            ->distinct()
            ->get(['t.id', 't.text']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->id] = (string) $row->text;
        }

        return $out;
    }
}
