<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Resource;

use App\Modules\Collections\Application\Dto\StoreCollectionView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read StoreCollectionView $resource */
final class StoreCollectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'topic' => $this->resource->topic,
            'source_lang' => $this->resource->sourceLang,
            'target_lang' => $this->resource->targetLang,
            'is_premium' => $this->resource->isPremium,
            'is_subscribed' => $this->resource->isSubscribed,
            'items_count' => $this->resource->itemsCount,
            'image_url' => $this->resource->imageUrl,
            'image_author' => $this->resource->imageAuthor,
            'image_author_url' => $this->resource->imageAuthorUrl,
        ];
    }
}
