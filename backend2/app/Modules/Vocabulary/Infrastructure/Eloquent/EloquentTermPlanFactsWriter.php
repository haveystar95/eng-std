<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermPlanFactsWriter;
use Illuminate\Support\Facades\DB;

final readonly class EloquentTermPlanFactsWriter implements TermPlanFactsWriter
{
    public function write(TermId $termId, bool $isLine, ?int $difficultyScore): void
    {
        DB::table('terms')
            ->where('id', $termId->value)
            ->update([
                'is_line' => $isLine,
                'difficulty_score' => $difficultyScore,
                'updated_at' => now(),
            ]);
    }
}
