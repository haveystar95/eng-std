<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Word */
class WordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'term' => $this->term,
            'translation' => $this->translation,
            'transcription' => $this->transcription,
            'example' => $this->example,
            'cefr_level' => $this->cefr_level,
        ];
    }
}
