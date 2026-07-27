<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * Spreads review intervals by ±5% so cards reviewed together don't clump on the same
 * future day. Injected into the scheduler so tests can disable it — a scheduling test
 * that flakes once a month because of randomness protects nothing.
 */
final class Fuzz
{
    private const SPREAD = 0.05;

    /** Intervals shorter than this stay exact; fuzzing 1–2 day steps is noise. */
    private const MIN_FUZZABLE_DAYS = 4;

    private function __construct(private readonly bool $enabled) {}

    public static function none(): self
    {
        return new self(false);
    }

    public static function random(): self
    {
        return new self(true);
    }

    public function apply(int $intervalDays): int
    {
        if (! $this->enabled || $intervalDays < self::MIN_FUZZABLE_DAYS) {
            return $intervalDays;
        }

        $spread = (int) max(1, (int) round($intervalDays * self::SPREAD));

        return max(1, $intervalDays + random_int(-$spread, $spread));
    }
}
