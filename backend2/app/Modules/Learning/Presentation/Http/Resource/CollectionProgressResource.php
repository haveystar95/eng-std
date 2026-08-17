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
            'terms_total' => $this->resource->total,
            'new_count' => $this->resource->newCount,
            'due_count' => $this->resource->due,
            'mastered_count' => $this->resource->mastered,
            // Breakdown of mastered for the three-segment progress bar.
            'confirmed_count' => $this->resource->confirmed,
            'familiar_count' => $this->resource->familiar,
            'in_progress_count' => $this->resource->inProgress,
        ];
    }
}
