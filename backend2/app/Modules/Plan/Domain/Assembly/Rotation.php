<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use InvalidArgumentException;

/**
 * A ROTATION «ПО КРУГУ» (наряд SESSION-1a, разд. 2): the units of a stage walk a cycle of card kinds — the first unit
 * gets one, the next the one after it — starting at a place the seed picks, so every scene does not open the cycle
 * with the same kind and a day dealt again rotates the same way.
 */
final class Rotation
{
    /**
     * @template T
     *
     * @param  list<T>  $cycle
     * @return T
     */
    public static function pick(string $seed, int $index, array $cycle): mixed
    {
        $count = count($cycle);
        if ($count === 0) {
            throw new InvalidArgumentException('A rotation needs at least one value.');
        }
        $offset = (crc32($seed) & 0x7FFFFFFF) % $count;

        return $cycle[($offset + max(0, $index)) % $count];
    }
}
