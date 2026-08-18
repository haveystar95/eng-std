<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Write one whole row of the matrix — on/off, rotation position, and the admission threshold —
 * globally (`userId` null) or for one user. One call, one row: the dedicated matrix screen edits a
 * row at a time, unlike the toggles screen ({@see SetExerciseModes}) which replaces a whole ordered
 * set, and the old admission-only write ({@see SetModeAdmission}) which left enabled/position alone.
 */
final readonly class SetModeSettingsRow
{
    public function __construct(
        public ?UserId $userId,
        public string $mode,
        public bool $enabled,
        public int $position,
        public string $minAcquisition,
        public ?int $minLearningStep = null,
        public ?int $minSuccessfulReviews = null,
        public string $optionsPolicy = 'standard',
    ) {}
}
