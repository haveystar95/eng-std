<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReviewState */
class ReviewCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'word' => new WordResource($this->word),
            'state' => new ReviewStateResource($this->resource),
        ];
    }
}
