<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermReadingTargetView;
use App\Modules\Vocabulary\Application\Query\TermReadingTargetReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermReadingTargetReader implements TermReadingTargetReader
{
    public function find(TermId $termId, string $supportLang): ?TermReadingTargetView
    {
        $term = DB::table('terms')
            ->where('id', $termId->value)
            ->whereNotExists(function ($q) use ($supportLang): void {
                $q->select(DB::raw(1))
                    ->from('term_transliterations')
                    ->whereColumn('term_transliterations.term_id', 'terms.id')
                    ->where('term_transliterations.lang', $supportLang);
            })
            ->first(['id', 'text', 'lang']);

        if ($term === null) {
            return null;
        }

        return new TermReadingTargetView(
            id: (string) $term->id,
            text: (string) $term->text,
            lang: (string) $term->lang,
        );
    }
}
