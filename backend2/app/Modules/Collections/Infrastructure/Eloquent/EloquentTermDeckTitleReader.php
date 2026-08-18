<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Query\TermDeckTitleReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermDeckTitleReader implements TermDeckTitleReader
{
    public function titlesFor(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $out = [];
        DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->whereIn('ci.term_id', $termIds)
            // Live on both sides: a term that has left every deck is not a question anyone is being
            // asked, and a deleted deck asks nothing either.
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->orderBy('c.title')
            ->get(['ci.term_id', 'c.title'])
            ->each(function (object $r) use (&$out): void {
                $out[(string) $r->term_id][] = (string) $r->title;
            });

        return array_map(static fn (array $t): array => array_values(array_unique($t)), $out);
    }
}
