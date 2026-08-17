<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** Tokens and USD spent in one AI category (for a single user's spend breakdown). */
final readonly class CostCategory
{
    public function __construct(
        public int $tokensIn,
        public int $tokensOut,
        public float $costUsd,
        public int $count,
    ) {}
}
