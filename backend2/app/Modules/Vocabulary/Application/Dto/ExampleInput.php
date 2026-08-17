<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** A usage example as supplied by another module — plain primitives only. */
final readonly class ExampleInput
{
    public function __construct(
        public string $sentence,
        public ?string $sentenceTranslation = null,
    ) {}
}
