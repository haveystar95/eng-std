<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\SessionCompositionReader;
use Illuminate\Support\Facades\DB;

final class EloquentSessionCompositionReader implements SessionCompositionReader
{
    public function compositionsByIds(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('study_sessions')->whereIn('id', $sessionIds)->get(['id', 'composition']) as $row) {
            $termIds = is_string($row->composition) ? json_decode($row->composition, true) : [];
            $set = [];
            if (is_array($termIds)) {
                foreach ($termIds as $termId) {
                    $set[(string) $termId] = true;
                }
            }
            $out[(string) $row->id] = $set;
        }

        return $out;
    }
}
