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
        ?string $filler = null,
        ?string $speakingKey = null,
        ?string $shelf = null,
        ?string $tier = null,
        ?string $skillRef = null,
        ?string $numberValue = null,
    ): void {
        DB::table('terms')
            ->where('id', $termId->value)
            ->update([
                'is_line' => $isLine,
                'difficulty_score' => $difficultyScore,
                'kind' => $kind,
                // The shelf and the tier travel together and are written even when null: a term
                // re-imported by a plan day is answering these questions for the first time, and a
                // term this day did not put on a shelf must not keep yesterday's answer.
                'shelf' => $shelf,
                'tier' => $tier,
                'skill_ref' => $skillRef,
                'number_value' => $numberValue,
                // A formula («Nice to meet you») has no slot, and an empty string would read as
                // «a frame whose hole is at the start» to every regex downstream. Same for the
                // filler: «nothing stands in the hole» and «there is no hole» are one state here.
                'frame' => $frame === '' ? null : $frame,
                'filler' => $filler === '' ? null : $filler,
                // Null means «ask for the whole line», which is a real answer and not an absence —
                // but an empty string is not it, for the same reason as above.
                'speaking_key' => $speakingKey === '' ? null : $speakingKey,
                'speaker' => $speaker,
                'updated_at' => now(),
            ]);
    }
}
