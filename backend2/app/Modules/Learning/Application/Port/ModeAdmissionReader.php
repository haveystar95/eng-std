<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Loads the admission matrix — which rung of the acquisition ladder opens which trainer, and where
 * that trainer's options come from.
 *
 * A reader, not an injected value, for the same reason the toggles are: the answer depends on who
 * is asking. It is stored in `learning_mode_settings` beside the on/off flag, so it moves with one
 * admin action rather than with a deploy.
 */
interface ModeAdmissionReader
{
    /** The matrix this user is dealt under: their per-mode overrides over the product default. */
    public function matrixFor(UserId $userId): ModeAdmission;

    /** The product default — what a user with no overrides inherits. */
    public function globalMatrix(): ModeAdmission;
}
