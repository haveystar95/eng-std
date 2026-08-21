<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Resource;

use App\Modules\Collections\Application\Dto\CollectionView;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read CollectionView $resource */
final class CollectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'source' => $this->resource->source,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'source_lang' => $this->resource->sourceLang,
            'target_lang' => $this->resource->targetLang,
            'visibility' => $this->resource->visibility,
            'items_count' => $this->resource->itemsCount,
            'is_default' => $this->resource->isDefault,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'items' => CollectionItemResource::collection($this->resource->items)->resolve($request),
        ];
    }
}
