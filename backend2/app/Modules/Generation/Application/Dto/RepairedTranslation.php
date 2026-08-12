<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * A second opinion on one item's learner-language fields, plus what it cost. `exampleTranslation`
 * is null when the brief carried no sentence — never a fabricated one.
 */
final readonly class RepairedTranslation
{
    public function __construct(
        public string $translation,
        public ?string $exampleTranslation,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
    ) {}
}
