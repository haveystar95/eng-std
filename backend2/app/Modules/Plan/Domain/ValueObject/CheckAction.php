<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * What actually happened when a check fired — the row the counters are keyed by. A lesson code is
 * `counted` whenever it is found; a fatal one is also `gated` when it held the day for a repair and
 * `failed` when it was still there as the day failed.
 */
enum CheckAction: string
{
    case Counted = 'counted';
    case Dropped = 'dropped';
    case Gated = 'gated';
    case Failed = 'failed';

    public static function for(CheckMode $mode): self
    {
        return match ($mode) {
            CheckMode::Observe => self::Counted,
            CheckMode::Drop => self::Dropped,
            CheckMode::Gate => self::Gated,
        };
    }
}
