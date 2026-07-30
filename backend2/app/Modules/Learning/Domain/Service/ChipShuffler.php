<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use Random\Randomizer;

/**
 * Breaks a target answer into word-bank chips and shuffles them. A phrase (more than one word)
 * becomes word chips in scrambled order; a single word becomes letter chips, so word_bank never
 * degenerates into a one-chip card. The shuffle is guaranteed to differ from the answer order
 * (for two chips a plain shuffle is the original half the time). Randomness is injected so the
 * shuffle is deterministic under test.
 */
final class ChipShuffler
{
    public function __construct(private readonly Randomizer $rng = new Randomizer()) {}

    /** @return list<string> */
    public function chips(string $text): array
    {
        $tokens = $this->tokenize($text);
        if (count($tokens) < 2) {
            return $tokens;
        }

        $shuffled = $tokens;
        for ($attempt = 0; $attempt < 10 && $shuffled === $tokens; $attempt++) {
            /** @var list<string> $shuffled */
            $shuffled = $this->rng->shuffleArray($tokens);
        }

        return $shuffled;
    }

    /** @return list<string> */
    private function tokenize(string $text): array
    {
        $trimmed = trim($text);
        $words = preg_split('/\s+/u', $trimmed) ?: [];
        $words = array_values(array_filter($words, static fn (string $w): bool => $w !== ''));

        // A phrase scrambles by word; a single word scrambles by letter.
        if (count($words) > 1) {
            return $words;
        }

        return mb_str_split($trimmed);
    }
}
