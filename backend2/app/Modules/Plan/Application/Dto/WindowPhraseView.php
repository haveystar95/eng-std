<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** A phrase line of the window. «Прослушать» reads it with the phone's voice: the server voices the role's lines only. */
final readonly class WindowPhraseView
{
    public function __construct(
        public string $ref,
        public string $text,
        public string $translation,
        public string $state,
    ) {}
}
