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
                // ADDITIVE (SYN-1): what else counts as right on THIS card because it means the
                // same thing. Its own field and not part of `accepted_variants`, which keeps
                // exactly the meaning it always had — see SessionCardView.
                'synonyms' => $card->synonyms,
                'option_feedback' => $card->optionFeedback,
                // The rung this card was dealt at. The client echoes it back with the answer —
                // the pair's rung moves as that answer is folded, so it is the only thing that can
                // still say what the card asked.
                'ladder_step' => $card->ladderStep,
                // Forward-recognition only: which term each option's translation belongs to. That
                // card is answered by TAPPING, so the client uploads the tapped id and `answer`
                // above is this card's own term id.
                'option_ids' => $card->optionIds,
            ], $this->resource->cards),
        ];
    }
}
