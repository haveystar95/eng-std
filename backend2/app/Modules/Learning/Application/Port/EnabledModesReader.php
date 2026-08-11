<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Which trainers are switched on. Reads the stored settings — the global default, overridden by a
 * per-user row when one exists — instead of a compile-time config, so a toggle takes effect on the
 * next request rather than the next deploy.
 *
 * Deliberately a READER, not an injected value: the answer depends on who is asking, and the old
 * `EnabledModes` singleton could not express that.
 */
interface EnabledModesReader
{
    /** The set this user trains with: their override if they have one, else the global default. */
    public function forUser(UserId $userId): EnabledModes;

    /** The product default — what a user with no override inherits. */
    public function globalDefault(): EnabledModes;

    /** The user's own override, or null when they inherit the global default. */
    public function overrideFor(UserId $userId): ?EnabledModes;
}
