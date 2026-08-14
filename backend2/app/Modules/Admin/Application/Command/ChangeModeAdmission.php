<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * An admin moving one trainer's place on the acquisition ladder. `userId` null = the product
 * default. One mode per call: the panel edits a row, and a whole-matrix write would silently
 * revert a threshold someone else had just moved.
 */
final readonly class ChangeModeAdmission
{
    public function __construct(
        public string $adminId,
        public ?UserId $userId,
        public string $mode,
        public string $minAcquisition,
        public ?int $minLearningStep = null,
        public ?int $minReps = null,
        public string $optionsPolicy = 'standard',
    ) {}
}
