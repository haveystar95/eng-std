<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** The native-language recap plus the token usage of the (cheap) text call that produced it. */
final readonly class DialogSummaryResult
{
    public function __construct(
        public string $summary,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public string $model,
    ) {}
}
