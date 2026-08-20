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
