<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** A freshly generated example (+ its translation) with the usage for the spend record. */
final readonly class ExampleRegenResult
{
    public function __construct(
        public string $example,
        public ?string $exampleTranslation,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
    ) {}
}
