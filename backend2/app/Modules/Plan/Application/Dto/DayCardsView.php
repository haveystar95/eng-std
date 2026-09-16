<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * The whole day's cards, in walking order — the client holds the lot from the first open, each card in the registry's
 * envelope with its sounds and photos resolved (наряд SESSION-1a, D-03).
 */
final readonly class DayCardsView
{
    /** @param list<CardView> $cards */
    public function __construct(
        public string $planId,
        public string $dayId,
        public int $number,
        public string $status,
        public array $cards,
    ) {}
}
