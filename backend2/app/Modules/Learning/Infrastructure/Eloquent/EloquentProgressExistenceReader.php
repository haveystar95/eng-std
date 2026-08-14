<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentProgressExistenceReader implements ProgressExistenceReader
{
    public function existingTermIds(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        // "Started" is a question about the ACQUISITION ladder, not about the scheduler: a pair
        // that has been shown its intro card has started, even though the scheduler has never seen
        // it and its `state` is still `new`. Conversely an `acquisition = 'new'` row counts as
        // not-started, same as no row at all — which is what keeps a term returned from `known`
        // reappearing as a new card.
        $rows = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->where('acquisition', '<>', Acquisition::New->value)
            ->whereIn('term_id', $termIds)
            ->pluck('term_id');

        $set = [];
        foreach ($rows as $termId) {
            $set[(string) $termId] = true;
        }

        return $set;
    }
}
