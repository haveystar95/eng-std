<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

/**
 * THE SPACING OF A STAGE (наряд SESSION-1a, разд. 2): three cards per unit — meet it, practise it, check it — dealt
 * so that no unit's three cards stand side by side. At step i the stage deals the i-th unit's first card, then the
 * previous unit's second, then the one before that's third: word 1 met, word 2 met, word 1 said, word 3 met, word 2
 * said, word 1 checked… What a list does not have at a step is skipped — past its end, or a unit that has no such card
 * (null in its place), so the units around it keep their places.
 */
final class Spacing
{
    /**
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
}
