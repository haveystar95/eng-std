<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Move one trainer's place on the acquisition ladder — globally (`userId` null) or for one user.
 *
 * Takes strings because its callers live outside Learning (the admin panel), and turns them into
 * Domain values in the handler, which is also where an unknown mode or acquisition is rejected.
 */
final readonly class SetModeAdmission
{
    public function __construct(
        public ?UserId $userId,
        public string $mode,
        public string $minAcquisition,
        public ?int $minLearningStep = null,
        public ?int $minReps = null,
        public string $optionsPolicy = 'standard',
    ) {}
}
