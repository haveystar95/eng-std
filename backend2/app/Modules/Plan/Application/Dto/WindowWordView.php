<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** A word or a chunk card of the window: its photo when it has one, and always the tone its slot is painted with. */
final readonly class WindowWordView
{
    /** @param array{url: string, author: string|null, author_url: string|null, tone: string|null}|null $image */
    public function __construct(
        public string $ref,
        public string $term,
        public string $translation,
        public ?array $image,
        public string $imageTone,
        public string $state,
    ) {}
}
