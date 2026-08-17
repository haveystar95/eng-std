<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\TriageCardView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read TriageCardView $resource */
final class TriageCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'term_id' => $this->resource->termId,
            'text' => $this->resource->text,
            'type' => $this->resource->type,
            'transcription' => $this->resource->transcription,
            'translation' => $this->resource->translation,
            'example' => $this->resource->example,
            'example_translation' => $this->resource->exampleTranslation,
        ];
    }
}
