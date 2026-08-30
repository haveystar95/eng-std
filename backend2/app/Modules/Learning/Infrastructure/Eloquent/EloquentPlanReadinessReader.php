<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanReadinessReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

/**
 * What share of the plan's terms this learner has got past the recognition rungs.
 *
 * `acquisition = 'graduated'` is today's stand-in for «ступень C», which arrives with the ladder in
 * 1b. The two are not the same thing and the difference is deliberately in the LOW direction: a
 * graduated pair has left the recognition rungs, a pair on rung C has done more than that, so this
 * reader can only ever report a smaller share than the real formula will. Readiness that is too
 * modest is a number that gets revised upwards when the ladder lands; readiness that flattered
 * would have to be taken back from a learner who had already read it.
 */
final readonly class EloquentPlanReadinessReader implements PlanReadinessReader
{
    public function acquiredShare(UserId $userId, array $collectionIds): float
    {
        if ($collectionIds === []) {
            return 0.0;
        }

        $termIds = DB::table('collection_items')
            ->whereIn('collection_id', $collectionIds)
            ->distinct()
            ->pluck('term_id');

        $total = $termIds->count();
        if ($total === 0) {
            return 0.0;
        }

        $acquired = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->where('acquisition', Acquisition::Graduated->value)
            ->count();

        return $acquired / $total;
    }
}
