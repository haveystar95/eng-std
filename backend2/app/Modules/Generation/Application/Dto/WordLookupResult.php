<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One word, as the model returned it — already shaped, not yet screened and not yet stored.
 *
 * The DESCRIPTION is the field this whole product exists for and the one with the strictest rule:
 * it is written in the language being LEARNED (the trainer shows it as the question), it is one or
 * two simple sentences at A2–B1, and it must not contain the word it describes — a definition that
 * uses its own headword answers the card for free.
 */
final readonly class WordLookupResult
{
    public function __construct(
        public string $text,
        public string $type,           // word | phrase
        public string $translation,
        public string $description,
        public ?string $example,
        public ?string $exampleTranslation,
        public ?string $cefr,          // A1..C2, or null when the model would not commit
        public ?string $transcription, // IPA
        /**
         * The model's own image-search query for this word — English, concrete, and EMPTY when the
         * word is genuinely un-illustratable. Null/empty means «no photo», never «guess one»: the
         * pending-image reader treats a blank query as a deliberate refusal.
         */
        public ?string $imageApiPrompt,
        public string $model,
        public string $promptVersion,
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
        public ?string $costUsd = null,
    ) {}

    /** @return array<string, mixed> the cacheable half — everything except what the call cost */
    public function toPayload(): array
    {
        return [
            'text' => $this->text,
            'type' => $this->type,
            'translation' => $this->translation,
            'description' => $this->description,
            'example' => $this->example,
            'example_translation' => $this->exampleTranslation,
            'cefr' => $this->cefr,
            'transcription' => $this->transcription,
            'image_api_prompt' => $this->imageApiPrompt,
        ];
    }
}
