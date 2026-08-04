<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Query\PendingCollectionImageReader;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Support\Facades\DB;

final class EloquentPendingCollectionImageReader implements PendingCollectionImageReader
{
    public function pendingFor(CollectionId $collectionId): ?string
    {
        $row = DB::table('collections')
            ->where('id', $collectionId->value)
            ->whereNull('image_url')
            ->whereNotNull('image_api_prompt')
            ->whereRaw("btrim(image_api_prompt) <> ''")
            ->value('image_api_prompt');

        return is_string($row) ? $row : null;
    }
}
