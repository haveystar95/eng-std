<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Profile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'native_language' => $this->native_language,
            'target_language' => $this->target_language,
            'cefr_level' => $this->cefr_level,
            'daily_goal' => $this->daily_goal,
        ];
    }
}
