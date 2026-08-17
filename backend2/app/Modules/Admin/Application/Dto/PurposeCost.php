<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * Spend attributable to one purpose: what it cost, how many tokens it burned, and how many calls
 * it took. `calls` matters on its own — Pexels costs nothing but a runaway image loop is still
 * something you want to see.
 */
final readonly class PurposeCost
{
    public function __construct(
        public string $purpose,     // generation | images | enrichment | realtime | recap | example_regen
        public int $tokensIn,
        public int $tokensOut,
        public float $costUsd,
        public int $calls,
    ) {}

    public static function empty(string $purpose): self
    {
        return new self($purpose, 0, 0, 0.0, 0);
    }
}
