<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * «В разговоре» (23-0e, DAY-UI-3): the line of the day a word is said in, where the word stands in it
 * — `offset`/`length` in characters (Unicode code points) of `text` — and the line's own voice.
 */
final readonly class WindowUsageView
{
    public function __construct(
        public string $text,
        public string $translation,
        public int $offset,
        public int $length,
        public ?string $audioId,
    ) {}
}
