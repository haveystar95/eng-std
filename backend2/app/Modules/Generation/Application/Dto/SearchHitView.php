<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One search result as the app shows it: the word, what it means, and where it already lives.
 *
 * `folders` is the field that turns the card's main button from a lie into the truth — a word
 * already in «Сохранённые» must not be offered a save that would do nothing.
 */
final readonly class SearchHitView
{
    /** @param list<array{id: string, title: string, is_default: bool}> $folders */
    public function __construct(
        public string $termId,
        public string $text,
        public string $type,
        public ?string $transcription,
        public ?string $translation,
        public ?string $description,
        public ?string $example,
        public ?string $exampleTranslation,
        public ?string $cefr,
        public array $folders = [],
    ) {}
}
