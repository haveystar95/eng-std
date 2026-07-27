<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReviewState */
class ReviewStateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'stability' => round($this->stability, 4),
            'difficulty' => round($this->difficulty, 4),
            'reps' => $this->reps,
            'due_at' => $this->due_at,
            'last_rating' => $this->last_rating,
        ];
    }
}
