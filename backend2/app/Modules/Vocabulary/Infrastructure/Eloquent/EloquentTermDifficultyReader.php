<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermDifficultyView;
use App\Modules\Vocabulary\Application\Query\TermDifficultyReader;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use Illuminate\Support\Facades\DB;

final class EloquentTermDifficultyReader implements TermDifficultyReader
{
    public function byIds(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        $out = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get(['id', 'cefr', 'type']) as $row) {
            $id = (string) $row->id;
            $out[$id] = new TermDifficultyView(
                termId: $id,
                cefr: $row->cefr !== null ? (string) $row->cefr : null,
                isPhrase: TermType::from((string) $row->type)->isPhraseLike(),
            );
        }

        return $out;
    }
}
