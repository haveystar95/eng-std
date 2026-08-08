<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/** One term in a store preview: the learned form + its native translation, for the showcase list. */
final readonly class PreviewTerm
{
    public function __construct(
        public string $text,
        public ?string $translation,
        public string $type,
        public ?string $cefr,
    ) {}
}
