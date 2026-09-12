<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\PushTarget;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The learner's push addresses — Identity owns devices; the plan reads and prunes them through its Application. */
interface LearnerDevices
{
    /** @return list<PushTarget> */
    public function targetsFor(UserId $user): array;

    /** APNs said this address is dead: forget it, whoever holds it. */
    public function forget(PushTarget $target): void;
}
