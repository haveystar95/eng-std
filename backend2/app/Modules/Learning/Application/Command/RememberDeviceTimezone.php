<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «This device is now in this zone.» Sent alongside a sync, because the sync is the one call every
 * device makes on every launch — a learner who moves abroad without re-logging in would otherwise
 * keep studying on the calendar of the country they left.
 *
 * `ianaZone` is already validated by the caller (Presentation, the `timezone` rule): the zone is an
 * optional passenger on someone else's request, so an unrecognisable one is dropped there rather
 * than failing the sync.
 */
final readonly class RememberDeviceTimezone
{
    public function __construct(
        public UserId $actorId,
        public string $ianaZone,
    ) {}
}
