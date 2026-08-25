<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** A term that still needs a reading hint: its text and the language it is written in. */
final readonly class TermReadingTargetView
{
    public function __construct(
        public string $id,
        public string $text,
        public string $lang,
    ) {}
}
