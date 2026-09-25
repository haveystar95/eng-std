<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

/**
 * WHERE A RIGHT STANDS (наряд ACC-1 §2): `active`; `grace` — a store's renewal failed and the store still keeps the
 * subscriber in (the paid plan stays open); `expired` — run out, or taken back by `access:revoke`.
 */
enum EntitlementStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Grace = 'grace';

    /** Does a right in this status open the paid plan — active, or in the store's grace period. */
    public function grants(): bool
    {
        return $this !== self::Expired;
    }
}
