<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * A deterministic shuffle. A day's cards are fixed before the day starts and a rebuilt day must
 * deal the same tiles in the same order, so «вперемешку» is seeded by the card's own address —
 * never by the process's random source.
 */
final class Shuffle
{
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public static function seeded(string $seed, array $items): array
    {
        $state = crc32($seed) & 0x7FFFFFFF;
        $out = $items;
        for ($i = count($out) - 1; $i > 0; $i--) {
            // A small linear congruential generator: enough to spread tiles, and reproducible.
            $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
            $j = $state % ($i + 1);
            [$out[$i], $out[$j]] = [$out[$j], $out[$i]];
        }

        return array_values($out);
    }
}
