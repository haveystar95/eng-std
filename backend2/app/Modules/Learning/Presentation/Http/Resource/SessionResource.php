<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\SessionCardView;
use App\Modules\Learning\Application\Dto\SessionView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read SessionView $resource */
final class SessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->resource->sessionId,
            'cards' => array_map(static fn (SessionCardView $card): array => [
                'term_id' => $card->termId,
                'exercise_mode' => $card->exerciseMode,
                'type' => $card->type,
                'prompt' => $card->prompt,
                'answer' => $card->answer,
                'transcription' => $card->transcription,
                'example' => $card->example,
                'example_translation' => $card->exampleTranslation,
                'options' => $card->options,
                'chips' => $card->chips,
                'accepted_variants' => $card->acceptedVariants,
                'option_feedback' => $card->optionFeedback,
            ], $this->resource->cards),
        ];
    }
}
