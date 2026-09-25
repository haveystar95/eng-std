<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

use DateTimeImmutable;

/** WHAT WAS GRANTED OR BOUGHT (наряд ACC-1 §2): a month, a year, or for good. */
enum EntitlementProduct: string
{
    case Month = 'month';
    case Year = 'year';
    case Lifetime = 'lifetime';

    /** When a right of this product that starts at `$start` runs out — never, for `lifetime`. */
    public function expiresAfter(DateTimeImmutable $start): ?DateTimeImmutable
    {
        return match ($this) {
            self::Month => $start->modify('+1 month'),
            self::Year => $start->modify('+1 year'),
            self::Lifetime => null,
        };
    }
}
