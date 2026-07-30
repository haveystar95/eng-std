<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentProgressExistenceReader implements ProgressExistenceReader
{
    public function existingTermIds(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        // A `new` row counts as not-started (same as no row), so a term returned from `known`
        // reappears as a new card.
        $rows = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->where('state', '<>', LearningState::New->value)
            ->whereIn('term_id', $termIds)
            ->pluck('term_id');

        $set = [];
        foreach ($rows as $termId) {
            $set[(string) $termId] = true;
        }

        return $set;
    }
}
