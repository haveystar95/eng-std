<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\TermChangeRef;
use App\Modules\Vocabulary\Application\Query\TermChangeReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentTermChangeReader implements TermChangeReader
{
    public function changedTermIds(array $termIds, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        if ($termIds === []) {
            return [];
        }

        $q = DB::table('terms')
            ->whereIn('id', $termIds)
            ->where('updated_at', '<=', $upper);
        if ($since !== null) {
            $q->where('updated_at', '>=', $since);
        }

        return array_values($q->orderBy('updated_at')->orderBy('id')
            ->get(['id', 'updated_at'])
            ->map(fn ($r): TermChangeRef => new TermChangeRef(
                id: (string) $r->id,
                updatedAt: new DateTimeImmutable((string) $r->updated_at),
            ))->all());
    }
}
