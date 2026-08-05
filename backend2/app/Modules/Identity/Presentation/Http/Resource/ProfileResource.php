<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Resource;

use App\Modules\Identity\Application\Dto\ProfileView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ProfileView $resource */
final class ProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'native_language' => $this->resource->nativeLanguage,
            'target_language' => $this->resource->targetLanguage,
            'cefr_level' => $this->resource->cefrLevel,
            'daily_goal' => $this->resource->dailyGoal,
            'tier' => $this->resource->tier,
        ];
    }
}
