<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\EnrichableTermView;
use App\Modules\Vocabulary\Application\Query\EnrichableTermReader;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Illuminate\Support\Facades\DB;

final class EloquentEnrichableTermReader implements EnrichableTermReader
{
    public function find(TermId $termId): ?EnrichableTermView
    {
        $term = DB::table('terms')
            ->where('id', $termId->value)
            ->where('source', 'user')
            ->whereNotExists(function ($q) use ($termId): void {
                $q->select(DB::raw(1))
                    ->from('term_translations')
                    ->whereColumn('term_translations.term_id', 'terms.id')
                    ->where('term_translations.term_id', $termId->value);
            })
            ->first(['id', 'text', 'type', 'lang']);

        if ($term === null) {
            return null;
        }

        return new EnrichableTermView(
            id: (string) $term->id,
            text: (string) $term->text,
            type: (string) $term->type,
            lang: (string) $term->lang,
        );
    }
}
