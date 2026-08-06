<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** The freshly generated example returned to the client so it can update the card in place. */
final readonly class RegeneratedExample
{
    public function __construct(
        public string $example,
        public ?string $exampleTranslation,
    ) {}
}
