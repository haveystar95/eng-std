<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermPlanFactsWriter;
use Illuminate\Support\Facades\DB;

final readonly class EloquentTermPlanFactsWriter implements TermPlanFactsWriter
{
    public function write(
        TermId $termId,
        bool $isLine,
        ?int $difficultyScore,
        ?string $kind = null,
        ?string $frame = null,
        ?string $speaker = null,
    ): void {
        DB::table('terms')
            ->where('id', $termId->value)
            ->update([
                'is_line' => $isLine,
                'difficulty_score' => $difficultyScore,
                'kind' => $kind,
                // A formula («Nice to meet you») has no slot, and an empty string would read as
                // «a frame whose hole is at the start» to every regex downstream.
                'frame' => $frame === '' ? null : $frame,
                'speaker' => $speaker,
                'updated_at' => now(),
            ]);
    }
}
