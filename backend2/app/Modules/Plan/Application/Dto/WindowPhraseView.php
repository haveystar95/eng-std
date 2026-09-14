<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** A phrase line of the window: the phrase, how it reads, its translation, its state and its voice — the learner's (DAY-UI-3). */
final readonly class WindowPhraseView
{
    public function __construct(
        public string $ref,
        public string $text,
        public string $translation,
        public string $state,
        public ?string $pronunciation = null,
        public ?string $audioId = null,
    ) {}
}
