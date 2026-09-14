<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * One question to the photo search (DAY-UI-3): the words, and which page of the answers to take — the
 * first photo for a description, a later one when several words of a day fall back to the same theme
 * and should not all show the same picture (nor the day's own plate, which is the theme's first).
 */
final readonly class ImageQuery
{
    public function __construct(
        public string $text,
        public int $page = 1,
    ) {}

    public function key(): string
    {
        return mb_strtolower(trim($this->text)).'#'.$this->page;
    }
}
