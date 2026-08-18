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

    /**
     * Every row of the matrix in this scope, each tagged with where it comes from — 'global' for a
     * row inherited from the product default, 'override' for one this user owns directly (or, when
     * `$userId` is null, every global row, which is definitionally its own scope). The matrix screen
     * needs this distinction to show which cells are already customized versus merely inherited; the
     * `ModeAdmission`/`ModeRule` VOs above don't carry it because "where did this rule come from" is
     * a reporting concern, not a ladder one.
     *
     * @return list<array{mode: string, enabled: bool, position: int, min_acquisition: string, min_learning_step: int|null, min_successful_reviews: int|null, options_policy: string, source: string, floor: string}>
     */
    public function rowsFor(?UserId $userId): array;
}
