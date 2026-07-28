<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** Renderable term content for the mobile client — the card's front, back and example. */
final readonly class TermContentView
{
    public function __construct(
        public string $id,
        public string $lang,
        public string $text,
        public string $type,             // word | phrase
        public ?string $transcription,   // IPA
        public ?string $translation,     // primary translation (source language)
        public ?string $example,
        public ?string $exampleTranslation,
    ) {}
}
