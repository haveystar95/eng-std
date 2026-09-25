<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

use DateTimeImmutable;

/**
 * ONE RIGHT TO THE PAID PLAN, as the table `entitlements` keeps it (наряд ACC-1 §2): where it came from, what it is, where
 * it stands, from when and until when. One row per learner and source — a grant by hand and a store's subscription are
 * two rows, and the learner's access is read over all of them ({@see \App\Modules\Identity\Domain\Service\AccessRule}).
 *
 * `expires_at` is null for a right with no end (`lifetime`); a right taken back keeps the moment it ended.
 */
final readonly class Entitlement
{
    public function __construct(
        public EntitlementSource $source,
        public EntitlementProduct $product,
        public EntitlementStatus $status,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $expiresAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /**
     * IS THIS RIGHT IN FORCE NOW — the order's rule word for word: status `active` or `grace`, and `expires_at` in the
     * future or none at all. A right past its date is out whatever its status says: a store that has not told us of the
     * end yet does not keep a learner in for free.
     */
    public function isActiveAt(DateTimeImmutable $now): bool
    {
        return $this->status->grants() && ($this->expiresAt === null || $this->expiresAt > $now);
    }
}
