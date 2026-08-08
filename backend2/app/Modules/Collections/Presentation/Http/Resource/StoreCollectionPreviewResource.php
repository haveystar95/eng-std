<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Resource;

use App\Modules\Collections\Application\Dto\PreviewTerm;
use App\Modules\Collections\Application\Dto\StoreCollectionPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read StoreCollectionPreview $resource */
final class StoreCollectionPreviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'collection_id' => $this->resource->collectionId,
            'total' => $this->resource->total,
            'terms' => array_map(static fn (PreviewTerm $t): array => [
                'text' => $t->text,
                'translation' => $t->translation,
                'type' => $t->type,
                'cefr' => $t->cefr,
            ], $this->resource->terms),
        ];
    }
}
