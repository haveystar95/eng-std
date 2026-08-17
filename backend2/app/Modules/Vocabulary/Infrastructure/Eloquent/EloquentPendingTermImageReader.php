<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\PendingTermImage;
use App\Modules\Vocabulary\Application\Query\PendingTermImageReader;
use Illuminate\Support\Facades\DB;

final class EloquentPendingTermImageReader implements PendingTermImageReader
{
    public function pendingFor(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('terms')
            ->whereIn('id', $termIds)
            ->whereNull('image_url')                       // not imaged yet (never overwrite)
            ->whereNotNull('image_api_prompt')
            ->whereRaw("btrim(image_api_prompt) <> ''")    // model left it blank → un-illustratable
            ->get(['id', 'image_api_prompt']);

        return array_values($rows->map(fn ($r): PendingTermImage => new PendingTermImage(
            termId: (string) $r->id,
            query: (string) $r->image_api_prompt,
        ))->all());
    }
}
