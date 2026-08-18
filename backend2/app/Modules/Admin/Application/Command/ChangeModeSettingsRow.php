<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * An admin saving one row of the «Матрица режимов» screen — on/off, rotation position and the
 * admission threshold together. `userId` null = the product default. One row per call, same reason
 * as the toggles and the admission-only write: a whole-matrix save would silently revert a cell
 * someone else had just moved.
 */
final readonly class ChangeModeSettingsRow
{
    public function __construct(
        public string $adminId,
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
