<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Query\StaleCoreReader;
use Illuminate\Support\Facades\DB;

final class EloquentStaleCoreReader implements StaleCoreReader
{
    public function idsByPromptVersion(array $promptVersions, ?string $afterId = null, int $limit = 500): array
    {
        if ($promptVersions === []) {
            return [];
        }

        $query = DB::table('terms')
            ->whereNull('deleted_at')
            ->whereIn('prompt_version', $promptVersions)
            ->orderBy('id')
            ->limit(max(1, $limit));

        if ($afterId !== null && $afterId !== '') {
            $query->where('id', '>', $afterId);
        }

        return array_values(array_map(static fn (mixed $id): string => (string) $id, $query->pluck('id')->all()));
    }

    public function idsNotWrittenBy(array $termIds, string $promptVersion): array
    {
        if ($termIds === []) {
            return [];
        }

        // `prompt_version` cannot be NULL on a stored row — the provenance migration stamped every
        // pre-existing one `legacy` precisely so that a NULL later means "a writer created content
        // without stamping it". Such a row IS stale by this question's standards, so it is included
        // rather than silently skipped by a `<>` comparison that Postgres would answer NULL to.
        $ids = DB::table('terms')
            ->whereNull('deleted_at')
            ->whereIn('id', $termIds)
            ->where(function ($query) use ($promptVersion): void {
                $query->where('prompt_version', '<>', $promptVersion)->orWhereNull('prompt_version');
            })
            ->pluck('id')
            ->all();

        return array_values(array_map(static fn (mixed $id): string => (string) $id, $ids));
    }

    public function countByPromptVersion(array $promptVersions): int
    {
        if ($promptVersions === []) {
            return 0;
        }

        return DB::table('terms')
            ->whereNull('deleted_at')
            ->whereIn('prompt_version', $promptVersions)
            ->count();
    }
}
