<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\SpeakableLine;
use App\Modules\Vocabulary\Application\Query\SpeakableLineReader;
use Illuminate\Support\Facades\DB;

final class EloquentSpeakableLineReader implements SpeakableLineReader
{
    public function linesFor(array $termIds, array $shelves): array
    {
        if ($termIds === [] || $shelves === []) {
            return [];
        }

        $rows = DB::table('terms')
            ->whereIn('id', $termIds)
            ->whereIn('shelf', $shelves)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'text', 'lang']);

        return array_values($rows->map(fn ($r): SpeakableLine => new SpeakableLine(
            termId: (string) $r->id,
            text: (string) $r->text,
            lang: (string) $r->lang,
        ))->all());
    }
}
