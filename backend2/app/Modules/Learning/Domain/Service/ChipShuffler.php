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
 *
 * A phrasal verb additionally gets 1–2 decoy particle chips (from a fixed common-particle set,
 * never the real one) mixed into the bank, so assembling "give up" is a real choice — not just
 * re-ordering the only two tiles on screen.
 */
final class ChipShuffler
{
    /** Common phrasal-verb particles used as decoys. Never includes the answer's own particle. */
    private const PARTICLES = ['up', 'on', 'in', 'off', 'out', 'down', 'over', 'away', 'back'];

    public function __construct(private readonly Randomizer $rng = new Randomizer()) {}

    /**
     * @param  bool  $phrasalVerb  add 1–2 decoy particle chips (only affects multi-word answers)
     * @return list<string>
     */
    public function chips(string $text, bool $phrasalVerb = false): array
    {
        $tokens = $this->tokenize($text);

        if ($phrasalVerb && count($tokens) >= 2) {
            $tokens = [...$tokens, ...$this->particleDecoys($tokens)];
        }

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

    /** How many whitespace-separated words the answer has — drives word_bank eligibility. */
    public function wordCount(string $text): int
    {
        return count($this->words($text));
    }

    /**
     * Pick 1–2 particle decoys that aren't already in the answer (case-insensitive).
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function particleDecoys(array $tokens): array
    {
        $present = array_map(static fn (string $t): string => mb_strtolower($t), $tokens);
        $candidates = array_values(array_filter(
            self::PARTICLES,
            static fn (string $p): bool => ! in_array($p, $present, true),
        ));
        if ($candidates === []) {
            return [];
        }

        /** @var list<string> $shuffled */
        $shuffled = $this->rng->shuffleArray($candidates);
        $count = min($this->rng->getInt(1, 2), count($shuffled));

        return array_slice($shuffled, 0, $count);
    }

    /** @return list<string> */
    private function tokenize(string $text): array
    {
        $words = $this->words($text);

        // A phrase scrambles by word; a single word scrambles by letter.
        if (count($words) > 1) {
            return $words;
        }

        return mb_str_split(trim($text));
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $split = preg_split('/\s+/u', trim($text)) ?: [];

        return array_values(array_filter($split, static fn (string $w): bool => $w !== ''));
    }
}
