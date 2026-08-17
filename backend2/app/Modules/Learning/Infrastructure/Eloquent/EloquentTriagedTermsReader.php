<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\TriagedTermsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentTriagedTermsReader implements TriagedTermsReader
{
    public function triagedTermIds(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('term_triages')
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->distinct()
            ->pluck('term_id');

        $set = [];
        foreach ($rows as $termId) {
            $set[(string) $termId] = true;
        }

        return $set;
    }
}
