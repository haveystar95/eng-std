<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** One word of «Далось труднее всего»: what it is, and how often it was missed in the last run. */
final readonly class HomeHardTermView
{
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        public int $errors,
    ) {}
}
