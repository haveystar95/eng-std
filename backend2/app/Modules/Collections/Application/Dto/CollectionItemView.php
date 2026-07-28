<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/** A collection item with its term content hydrated from Vocabulary (for the detail screen). */
final readonly class CollectionItemView
{
    public function __construct(
        public string $termId,
        public int $position,
        public ?string $note,
        public ?string $text,
        public ?string $type,
        public ?string $transcription,
        public ?string $translation,
        public ?string $example,
        public ?string $exampleTranslation,
    ) {}
}
