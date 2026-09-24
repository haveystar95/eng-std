<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\Service\SpokenWordBoundary;

/**
 * HOW MUCH OF A LINE A MOVE HAS ALREADY SAID (наряд BACK-TAILS-2 §9) — the reading the role's echo guard measures by
 * ({@see RoleLines}): the share of the line's key words (the pack's `unstressed_words` and `articles` aside; a line of
 * nothing but those is read whole) the heard move holds, by their bases ({@see WordBases}), in any order, each heard
 * word spent once. 1 when every key word of the line was in the move — «It started three days ago.» after «His lower
 * back hurts, and three days ago it started.» — however much more the move said.
 *
 * A measure of words for a guard, not the judge of the talk's constructions: that one reads a coherent phrase
 * ({@see FrameJudge}, наряд FIX-4 §2).
 */
final readonly class LineShare
{
    public function __construct(
        private SpeechMatch $speech = new SpeechMatch,
        private SpokenWordBoundary $boundary = new SpokenWordBoundary,
    ) {}

    /**
     * With `$swapPersons` the move is read with its first and second person swapped (the pack's `person_swap`): «My son
     * has a fever» heard as «your son has a fever».
     */
    public function share(string $line, string $heard, LanguagePack $pack, bool $swapPersons = false): float
    {
        $speech = $pack->speech();
        if ($swapPersons && $pack->has('person_swap')) {
            $swap = $pack->map('person_swap');
            $heard = implode(' ', array_map(
                static fn (string $w): string => is_string($swap[$w] ?? null) ? $swap[$w] : $w,
                $this->speech->words($heard, $speech),
            ));
        }
        $all = $this->speech->words($line, $speech);
        $drop = [...$speech->unstressed, ...$speech->articles];
        $wanted = self::without($all, $drop);
        // A line of nothing but unstressed words is read whole — and then nothing is dropped from what was heard.
        $spare = $wanted === [] ? [] : $drop;
        $wanted = $wanted === [] ? $all : $wanted;
        if ($wanted === []) {
            return 0.0;
        }
        $heardWords = self::without($this->boundary->align($this->speech->words($heard, $speech), $all), $spare);

        return self::matches($wanted, $heardWords, $pack) / count($wanted);
    }

    /**
     * How many of `$wanted` are among `$available` by their bases, each available word spent once.
     *
     * @param  list<string>  $wanted
     * @param  list<string>  $available
     */
    private static function matches(array $wanted, array $available, LanguagePack $pack): int
    {
        $pool = array_map(static fn (string $w): array => WordBases::of($w, $pack), $available);
        $found = 0;
        foreach ($wanted as $word) {
            $mine = WordBases::of($word, $pack);
            foreach ($pool as $i => $theirs) {
                if (array_intersect($mine, $theirs) !== []) {
                    unset($pool[$i]);
                    $found++;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $drop
     * @return list<string>
     */
    private static function without(array $words, array $drop): array
    {
        if ($drop === []) {
            return $words;
        }
        $index = array_fill_keys($drop, true);

        return array_values(array_filter($words, static fn (string $w): bool => ! isset($index[$w])));
    }
}
