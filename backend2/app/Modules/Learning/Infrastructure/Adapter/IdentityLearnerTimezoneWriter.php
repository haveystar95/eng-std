<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Adapter;

use App\Modules\Identity\Application\Dto\ProfileInput;
use App\Modules\Identity\Application\Port\ProfileUpdater;
use App\Modules\Learning\Application\Port\LearnerTimezoneWriter;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Writes the learner's zone through Identity's own profile updater — the same door the login and
 * the profile screen use, so the column has exactly one writer's worth of behaviour.
 */
final readonly class IdentityLearnerTimezoneWriter implements LearnerTimezoneWriter
{
    public function __construct(private ProfileUpdater $profiles) {}

    public function updateTimezone(UserId $user, string $ianaZone): void
    {
        $this->profiles->update($user, new ProfileInput(timezone: $ianaZone));
    }
}
