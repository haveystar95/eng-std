<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * The model's enrichment of a term: a translation, IPA, one example (+ its translation), and an
 * image-search query for Pexels ("" when un-illustratable). Carries the usage so the caller can
 * record the spend.
 */
final readonly class TermEnrichmentResult
{
    public function __construct(
        public string $translation,
        public ?string $transcription,
        public ?string $example,
        public ?string $exampleTranslation,
        public ?string $imageApiPrompt,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
    ) {}
}
