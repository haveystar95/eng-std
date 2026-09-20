<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\SpeechPack;

/**
 * The whole day's cards, in walking order — the client holds the lot from the first open, each card in the registry's
 * envelope with its sounds and photos resolved (наряд SESSION-1a, D-03) — and, ONCE for the whole day, what a
 * comparison of speech may read of the target language.
 *
 * The speech rules ride with the day and not with each card because they belong to the plan's target language, not to
 * a trainer: the phone judges a spoken attempt by the same lists the server does ({@see SpeechPack}) instead of
 * keeping its own copy of English in Dart (наряд FIX-2, п. 2).
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
        public SpeechPack $speech = new SpeechPack,
        public int $repeatMisses = 0,
    ) {}

    /** @return array<string, mixed> the `speech` block of the reply: the language's lists and the loosening handle */
    public function speechRules(): array
    {
        return [...$this->speech->toArray(), 'repeat_misses' => $this->repeatMisses];
    }
}
