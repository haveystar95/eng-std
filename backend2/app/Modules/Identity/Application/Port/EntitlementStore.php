<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Domain\ValueObject\Entitlement;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * THE LEARNER'S RIGHTS TO THE PAID PLAN, as `entitlements` keeps them (наряд ACC-1 §2) — one row per learner and source.
 * No purchase comes through here yet: the owner's grants by hand (`access:grant`, `access:revoke`); the store sync of
 * PAY-1 will write its own rows through the same door.
 */
interface EntitlementStore
{
    /** @return list<Entitlement> every right the learner has, whatever its status, oldest source first */
    public function forUser(UserId $userId): array;

    /** Writes the learner's right of this source — the row there is rewritten, active from now. */
    public function put(UserId $userId, Entitlement $entitlement): void;

    /**
     * Takes back every right of the learner still in force: `expired`, ending now (an end already past is kept).
     *
     * @return int how many rights were in force
     */
    public function revokeAll(UserId $userId, DateTimeImmutable $now): int;
}
