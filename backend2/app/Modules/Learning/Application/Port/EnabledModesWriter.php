<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeRule;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Flips the trainer toggles. The admin panel is the only caller; the app never writes these. */
interface EnabledModesWriter
{
    /**
     * Move one trainer's place on the acquisition ladder — globally (`null` user) or for one user.
     *
     * Deliberately separate from the on/off write: whether a trainer is switched on and how early
     * it opens are independent decisions, and a single «save the modes» call that carried both
     * would reset one every time the other was edited.
     */
    public function setRule(?UserId $userId, ExerciseMode $mode, ModeRule $rule): void;

    /** Replace the product default. Order is preserved — it drives the practice rotation. */
    public function setGlobalDefault(EnabledModes $modes): void;

    /**
     * Give this user their own set, or pass null to DROP the override so they inherit the global
     * default again. "Inherit" is the absence of a row, never an empty set.
     */
    public function setOverrideFor(UserId $userId, ?EnabledModes $modes): void;
}
