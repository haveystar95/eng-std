<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** A triage card: just enough to render term, translation and (for phrases) an example. */
final readonly class TriageCardView
{
    public function __construct(
        public string $termId,
        public ?string $text,
        public ?string $type,
        public ?string $transcription,
        public ?string $translation,
        public ?string $example,
        public ?string $exampleTranslation,
    ) {}
}
