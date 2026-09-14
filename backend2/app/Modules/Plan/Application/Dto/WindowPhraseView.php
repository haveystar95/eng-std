<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** A phrase line of the window, with the server's voice for «прослушать» when it has one. */
final readonly class WindowPhraseView
{
    public function __construct(
        public string $ref,
        public string $text,
        public string $translation,
        public ?string $audioId,
        public string $state,
    ) {}
}
