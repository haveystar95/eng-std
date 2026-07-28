<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Resource;

use App\Modules\Collections\Application\Dto\CollectionItemView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read CollectionItemView $resource */
final class CollectionItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'term_id' => $this->resource->termId,
            'position' => $this->resource->position,
            'note' => $this->resource->note,
        ];
    }
}
