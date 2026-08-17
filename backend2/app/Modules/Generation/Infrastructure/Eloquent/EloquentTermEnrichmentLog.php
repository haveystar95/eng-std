<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\RecordsTermEnrichment;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentTermEnrichmentLog implements RecordsTermEnrichment
{
    public function record(
        TermId $termId,
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        DateTimeImmutable $at,
    ): void {
        DB::table('term_enrichments')->insert([
            'id' => Ulid::generate(),
            'term_id' => $termId->value,
            'model' => $model,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_usd' => $costUsd,
            'created_at' => $at,
        ]);
    }
}
