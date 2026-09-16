<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

/**
 * THE SPACING OF A STAGE (наряд SESSION-1a, разд. 2; SESSION-1d).
 *
 * Words — {@see interleave()}: three cards per word — meet it, practise it, check it — so that no word's three stand
 * side by side.
 *
 * Phrases — {@see apart()}: a frame has more cards than three (its intro, two or three recognitions, its production),
 * and between two cards of one frame stand at least {@see GAP} cards of other frames.
 */
final class Spacing
{
    /** How many cards of other units stand between two cards of one unit in {@see apart()} — whenever the stage allows it. */
    public const GAP = 2;

    /** How many placements {@see apart()} may try before it deals the waves greedily. */
    public const BUDGET = 20000;

    /**
     * At step i the stage deals the i-th unit's first card, then the previous unit's second, then the one before that's
     * third: word 1 met, word 2 met, word 1 said, word 3 met, word 2 said, word 1 checked… What a list does not have at a
     * step is skipped — past its end, or a unit that has no such card (null in its place), so the units around it keep
     * their places.
     *
     * @param  list<CardDraft|null>  $a  the first card of every unit, in unit order; null — the unit has none
     * @param  list<CardDraft|null>  $b  the second
     * @param  list<CardDraft|null>  $c  the third
     * @return list<CardDraft>
     */
    public static function interleave(array $a, array $b, array $c): array
    {
        $out = [];
        $steps = max(count($a), count($b) + 1, count($c) + 2);
        for ($i = 0; $i < $steps; $i++) {
            foreach ([$a[$i] ?? null, $b[$i - 1] ?? null, $c[$i - 2] ?? null] as $draft) {
                if ($draft !== null) {
                    $out[] = $draft;
                }
            }
        }

        return $out;
    }

    /**
     * UNITS OF ANY LENGTH, SPACED APART (SESSION-1d): the cards of one unit never stand closer than {@see GAP} cards of
     * other units, whenever the stage can be dealt so.
     *
     * The order the stage WANTS is the waves: wave w deals the w-th unit's first card (its intro), then the previous
     * unit's second, the one before that's third, and so on — the interleave of any number of cards. What is dealt is
     * the first arrangement, in the waves' order, in which (1) the cards of a unit keep their own order, (2) no intro
     * is overtaken by a card of its own wave or a later one — every intro opens its wave — (3) `$last` (the day's
     * `phrase_combine`, a card of one of the units) is the last card, and (4) between two cards of one unit stand at
     * least {@see GAP} cards of other units. A stage that cannot hold (4) everywhere — too few units for their cards —
     * is dealt as the first arrangement with the FEWEST places where two cards of a unit stand closer; a search longer
     * than {@see BUDGET} placements settles for the waves dealt greedily (a card waits for its turn while it would stand
     * too close, the one waiting longest goes when nothing else can). Deterministic: the same units deal the same
     * stage.
     *
     * @param  list<list<CardDraft>>  $units  every unit's cards in their own order — its intro first
     * @return list<CardDraft>
     */
    public static function apart(array $units, ?CardDraft $last = null): array
    {
        $units = array_values(array_filter($units, static fn (array $cards): bool => $cards !== []));
        $plain = [];
        $index = [];
        $waves = 0;
        foreach ($units as $u => $cards) {
            $waves = max($waves, $u + count($cards));
        }
        for ($w = 0; $w < $waves; $w++) {
            for ($c = 0; $c <= $w; $c++) {
                $u = $w - $c;
                if (isset($units[$u][$c])) {
                    $index[$u][$c] = count($plain);
                    $plain[] = [$u, $c];
                }
            }
        }
        $lastUnit = null;
        foreach ($units as $u => $cards) {
            if ($last !== null && $lastUnit === null && $cards[0]->unitRef === $last->unitRef) {
                $lastUnit = $u;
            }
        }

        $search = new SpacingSearch($units, $index, $last !== null, $lastUnit, self::GAP, self::BUDGET);
        $order = $search->run() ?? self::greedy($units, $plain, $last !== null);

        $out = [];
        foreach ($order as $place) {
            $out[] = $place === null ? $last : $units[$place[0]][$place[1]];
        }

        return array_values(array_filter($out, static fn (?CardDraft $d): bool => $d !== null));
    }

    /**
     * The waves dealt greedily: every intro opens its wave; the rest of a wave waits in line with what waited before
     * it, and the first in line whose unit stands far enough goes, as long as one does; past the last wave nothing
     * waits any longer — the card whose unit has waited longest goes first. `null` in the order is `$last`.
     *
     * @param  list<list<CardDraft>>  $units
     * @param  list<array{0: int, 1: int}>  $plain
     * @return list<array{0: int, 1: int}|null>
     */
    private static function greedy(array $units, array $plain, bool $hasLast): array
    {
        $out = [];
        $lastAt = [];
        $next = array_fill(0, count($units), 0);
        $pending = [];
        $byWave = [];
        foreach ($plain as [$u, $c]) {
            $byWave[$u + $c][] = [$u, $c];
        }
        ksort($byWave);
        $place = static function (int $u, int $c) use (&$out, &$lastAt, &$next): void {
            $lastAt[$u] = count($out);
            $next[$u] = $c + 1;
            $out[] = [$u, $c];
        };
        $far = static function (int $u) use (&$out, &$lastAt): int {
            return isset($lastAt[$u]) ? count($out) - $lastAt[$u] - 1 : PHP_INT_MAX;
        };

        foreach ($byWave as $cards) {
            foreach ($cards as [$u, $c]) {
                if ($c === 0) {
                    $place($u, 0);
                } else {
                    $pending[] = [$u, $c];
                }
            }
            do {
                $placed = false;
                foreach ($pending as $k => [$u, $c]) {
                    if ($next[$u] === $c && $far($u) >= self::GAP) {
                        $place($u, $c);
                        unset($pending[$k]);
                        $placed = true;
                        break;
                    }
                }
            } while ($placed);
        }
        while ($pending !== []) {
            $best = null;
            foreach ($pending as $k => [$u, $c]) {
                if ($next[$u] === $c && ($best === null || $far($u) > $far($pending[$best][0]))) {
                    $best = $k;
                }
            }
            if ($best === null) {
                break;
            }
            $place($pending[$best][0], $pending[$best][1]);
            unset($pending[$best]);
        }
        if ($hasLast) {
            $out[] = null;
        }

        return $out;
    }
}
