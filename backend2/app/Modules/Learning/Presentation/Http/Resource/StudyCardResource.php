<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\StudyCardView;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read StudyCardView $resource */
final class StudyCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'term_id' => $this->resource->termId,
            'state' => $this->resource->state,
            'interval_days' => $this->resource->intervalDays,
            'due_at' => $this->resource->dueAt?->format(DateTimeInterface::ATOM),
            'text' => $this->resource->text,
            'type' => $this->resource->type,
            'transcription' => $this->resource->transcription,
            'translation' => $this->resource->translation,
            'example' => $this->resource->example,
            'example_translation' => $this->resource->exampleTranslation,
        ];
    }
}
