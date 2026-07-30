<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermAnswerKeyView;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermAnswerKeyReader implements TermAnswerKeyReader
{
    public function byIds(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        $out = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get(['id', 'text', 'type']) as $row) {
            $id = (string) $row->id;
            // One accepted form today (the term text); alternative spellings/forms join this set
            // later via a term_forms table, without changing the reader's shape.
            $out[$id] = new TermAnswerKeyView(
                termId: $id,
                accepted: [(string) $row->text],
                isPhrase: (string) $row->type === 'phrase',
            );
        }

        return $out;
    }
}
