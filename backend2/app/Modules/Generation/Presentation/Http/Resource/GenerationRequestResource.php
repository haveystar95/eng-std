<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Resource;

use App\Modules\Generation\Application\Dto\GenerationRequestView;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read GenerationRequestView $resource */
final class GenerationRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status,
            'prompt' => $this->resource->prompt,
            'requested' => $this->resource->requested,
            'delivered' => $this->resource->delivered,
            'collection_id' => $this->resource->collectionId,
            'error' => $this->resource->error,
            'model' => $this->resource->model,
            'tokens_in' => $this->resource->tokensIn,
            'tokens_out' => $this->resource->tokensOut,
            'cost_usd' => $this->resource->costUsd,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'finished_at' => $this->resource->finishedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
