<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Flips the trainer toggles. The admin panel is the only caller; the app never writes these. */
interface EnabledModesWriter
{
    /** Replace the product default. Order is preserved — it drives the practice rotation. */
    public function setGlobalDefault(EnabledModes $modes): void;

    /**
     * Give this user their own set, or pass null to DROP the override so they inherit the global
     * default again. "Inherit" is the absence of a row, never an empty set.
     */
    public function setOverrideFor(UserId $userId, ?EnabledModes $modes): void;
}
