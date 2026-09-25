<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Service;

use App\Modules\Identity\Domain\ValueObject\Access;
use App\Modules\Identity\Domain\ValueObject\AccessPlan;
use App\Modules\Identity\Domain\ValueObject\Entitlement;
use DateTimeImmutable;

/**
 * THE LEARNER'S ACCESS, READ OVER EVERY RIGHT THEY HAVE (наряд ACC-1 §2).
 *
 * The paid plan is open while ANY right is in force ({@see Entitlement::isActiveAt()}); none — the free plan. When
 * several are in force (the owner's grant and, later, a store's subscription), the one that lasts longest is the one
 * named: no end outlasts any end, of two ends the later one. That is the answer to «until when», and the source the
 * admin page shows beside it.
 */
final class AccessRule
{
    /** @param list<Entitlement> $entitlements */
    public static function of(array $entitlements, DateTimeImmutable $now): Access
    {
        $best = null;
        foreach ($entitlements as $entitlement) {
            if ($entitlement->isActiveAt($now) && ($best === null || self::outlasts($entitlement, $best))) {
                $best = $entitlement;
            }
        }

        return $best === null ? Access::free() : new Access(AccessPlan::Premium, $best->expiresAt, $best->source);
    }

    private static function outlasts(Entitlement $a, Entitlement $b): bool
    {
        if ($b->expiresAt === null) {
            return false;
        }

        return $a->expiresAt === null || $a->expiresAt > $b->expiresAt;
    }
}
