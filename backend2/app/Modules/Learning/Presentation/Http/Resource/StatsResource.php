<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\StatsView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read StatsView $resource */
final class StatsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'total_terms' => $this->resource->totalTerms,
            'learned' => $this->resource->learned,
            'mastered' => $this->resource->mastered,
            'due_today' => $this->resource->dueToday,
            'reviews_today' => $this->resource->reviewsToday,
            'streak_days' => $this->resource->streakDays,
        ];
    }
}
