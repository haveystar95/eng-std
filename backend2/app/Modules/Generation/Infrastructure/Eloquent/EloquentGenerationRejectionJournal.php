<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\RecordsGenerationRejections;
use App\Modules\Generation\Domain\ValueObject\RejectedItem;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentGenerationRejectionJournal implements RecordsGenerationRejections
{
    public function record(string $requestId, array $rejections): void
    {
        if ($rejections === []) {
            return;
        }

        DB::table('generation_rejections')->insert(array_map(
            static fn (RejectedItem $r): array => [
                'id' => Ulid::generate(),
                'request_id' => $requestId,
                'text' => $r->text,
                'field' => $r->field,
                'reason' => $r->reason,
                'attempts' => $r->attempts,
                'created_at' => now(),
            ],
            $rejections,
        ));
    }
}
