<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** An additional correct answer for a term, already validated by the caller. */
final readonly class AcceptedVariantInput
{
    public function __construct(
        public string $text,
        public ?string $note,
    ) {}
}
