<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Collection */
class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'emoji' => $this->emoji,
            'topic' => $this->topic,
            'source' => $this->source,
            'words_count' => $this->words_count ?? $this->words()->count(),
            'created_at' => $this->created_at,
            'words' => WordResource::collection($this->whenLoaded('words')),
        ];
    }
}
