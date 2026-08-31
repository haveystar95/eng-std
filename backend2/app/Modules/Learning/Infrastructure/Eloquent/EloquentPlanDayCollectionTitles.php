<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanDayCollectionTitles;
use Illuminate\Support\Facades\DB;

/**
 * One join: the day's collection, the day, the plan's title.
 *
 * Not access-checked, deliberately. The caller has already established that these collections are
 * the learner's own ({@see \App\Modules\Collections\Application\Port\CollectionPairReader::collectionByTerm()}
 * applies the access rule), and a plan's title adds no information about anybody else — it is the
 * name of a shelf the caller has already been told this learner may read.
 */
final readonly class EloquentPlanDayCollectionTitles implements PlanDayCollectionTitles
{
    public function titlesByDayCollection(array $collectionIds): array
    {
        if ($collectionIds === []) {
            return [];
        }

        $rows = DB::table('learning_plan_days as d')
            ->join('learning_plans as p', 'p.id', '=', 'd.plan_id')
            ->whereIn('d.collection_id', $collectionIds)
            ->get(['d.collection_id', 'p.title']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->collection_id] ??= (string) $row->title;
        }

        return $out;
    }
}
