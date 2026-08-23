<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\LearnerTimezoneWriter;

/**
 * Writes the device's zone onto the profile when — and only when — it actually changed.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: reschedule. Cards already scheduled keep the `due_at` they
 * were given, so a learner who flies to another continent finds the same words ripening at the same
 * instants; only answers given FROM NOW ON are planned on the new calendar. The alternative —
 * re-flooring every due date into the new zone — would silently move a whole pool's worth of dates
 * on a trip, and there is no honest answer to «which day did the learner mean» for a card scheduled
 * before the move. See SyncTimezoneTest, which pins this.
 */
final readonly class RememberDeviceTimezoneHandler
{
    public function __construct(
        private LearnerProfileReader $profile,
        private LearnerTimezoneWriter $writer,
    ) {}

    public function __invoke(RememberDeviceTimezone $command): void
    {
        if ($this->profile->timezoneFor($command->actorId)->getName() === $command->ianaZone) {
            return;
        }

        $this->writer->updateTimezone($command->actorId, $command->ianaZone);
    }
}
