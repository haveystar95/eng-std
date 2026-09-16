<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Service\Shuffle;

/**
 * THE PAYLOAD OF A CARD DEALT AGAIN (наряд SESSION-1a, D-06): a choice failed the first time comes back at the end of
 * its stage with its options and tiles in another order — seeded by the original card, so a replayed answer deals the
 * same copy. The ids of the options travel with them, so `correct` still names the right one; nothing else changes.
 */
final class Retry
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function payload(array $payload, string $seed): array
    {
        foreach (['options', 'tiles'] as $key) {
            if (is_array($payload[$key] ?? null) && array_is_list($payload[$key])) {
                $payload[$key] = Shuffle::seeded($seed, $payload[$key]);
            }
        }

        return $payload;
    }
}
