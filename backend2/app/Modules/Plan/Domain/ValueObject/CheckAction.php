<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** What actually happened when a check fired — the row the counters are keyed by. */
enum CheckAction: string
{
    case Counted = 'counted';
    case Dropped = 'dropped';
    case Gated = 'gated';

    public static function for(CheckMode $mode): self
    {
        return match ($mode) {
            CheckMode::Observe => self::Counted,
            CheckMode::Drop => self::Dropped,
            CheckMode::Gate => self::Gated,
        };
    }
}
