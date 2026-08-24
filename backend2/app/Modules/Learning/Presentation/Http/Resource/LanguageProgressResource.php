<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\LanguageProgressView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the language cut of `GET /study/progress` — the same counter names as
 * {@see CollectionProgressResource}, so a client can render either list with one widget.
 *
 * @property-read LanguageProgressView $resource
 */
final class LanguageProgressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'lang' => $this->resource->lang,
            'terms_total' => $this->resource->total,
            'new_count' => $this->resource->newCount,
            'due_count' => $this->resource->due,
            'mastered_count' => $this->resource->mastered,
            'confirmed_count' => $this->resource->confirmed,
            'familiar_count' => $this->resource->familiar,
            'in_progress_count' => $this->resource->inProgress,
        ];
    }
}
