<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermLanguageReader implements TermLanguageReader
{
    public function langsFor(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('terms')
            ->whereIn('id', array_map(static fn (TermId $id): string => $id->value, $termIds))
            ->whereNull('deleted_at')
            ->get(['id', 'lang']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->id] = (string) $row->lang;
        }

        return $out;
    }
}
