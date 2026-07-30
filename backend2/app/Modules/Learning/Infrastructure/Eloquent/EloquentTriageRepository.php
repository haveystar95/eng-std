<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\Triage;
use App\Modules\Learning\Domain\Repository\TriageRepository;
use Illuminate\Support\Facades\DB;

final class EloquentTriageRepository implements TriageRepository
{
    public function insertIgnore(Triage $triage): bool
    {
        $inserted = DB::table('term_triages')->insertOrIgnore([
            'id' => $triage->id->value,
            'user_id' => $triage->userId->value,
            'term_id' => $triage->termId->value,
            'verdict' => $triage->verdict->value,
            'collection_id' => $triage->collectionId?->value,
            'latency_ms' => $triage->latencyMs,
            'decided_at' => $triage->decidedAt,
            'created_at' => now(),
        ]);

        return $inserted === 1;
    }
}
