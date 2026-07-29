<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\CollectionProgressView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read CollectionProgressView $resource */
final class CollectionProgressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'collection_id' => $this->resource->collectionId,
            'total' => $this->resource->total,
            'learned' => $this->resource->learned,
            'mastered' => $this->resource->mastered,
            'due' => $this->resource->due,
        ];
    }
}
