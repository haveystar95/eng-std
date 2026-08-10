<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\SessionContext;
use App\Modules\Learning\Application\Port\SessionContextReader;
use Illuminate\Support\Facades\DB;

final class EloquentSessionContextReader implements SessionContextReader
{
    public function byIds(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $out = [];
        $rows = DB::table('study_sessions')
            ->whereIn('id', $sessionIds)
            ->get(['id', 'user_id', 'is_practice', 'composition']);

        foreach ($rows as $row) {
            $termIds = is_string($row->composition) ? json_decode($row->composition, true) : [];
            $set = [];
            if (is_array($termIds)) {
                foreach ($termIds as $termId) {
                    $set[(string) $termId] = true;
                }
            }
            $out[(string) $row->id] = new SessionContext(
                userId: (string) $row->user_id,
                isPractice: (bool) $row->is_practice,
                composition: $set,
            );
        }

        return $out;
    }
}
