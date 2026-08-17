<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** An estimated realtime spend plus the (derived) text-token counts it was computed from. */
final readonly class CostEstimate
{
    public function __construct(
        public ?string $costUsd,
        public int $tokensIn,
        public int $tokensOut,
    ) {}
}
