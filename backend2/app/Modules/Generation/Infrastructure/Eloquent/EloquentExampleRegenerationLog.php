<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\RecordsExampleRegeneration;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentExampleRegenerationLog implements RecordsExampleRegeneration
{
    public function record(
        UserId $userId,
        TermId $termId,
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        DateTimeImmutable $at,
    ): void {
        DB::table('example_regenerations')->insert([
            'id' => Ulid::generate(),
            'user_id' => $userId->value,
            'term_id' => $termId->value,
            'model' => $model,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_usd' => $costUsd,
            'created_at' => $at,
        ]);
    }
}
