<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The one profile field Learning is allowed to WRITE — the learner's calendar zone.
 *
 * It is Identity's column, and it stays Identity's: this port is implemented by an adapter that
 * goes through Identity's Application, never its tables. Learning needs the write because the zone
 * is what its day-scale scheduling and its whole notion of «сегодня» are expressed in, and the one
 * request that every device makes on every launch is the sync — so a learner who MOVED and never
 * logged in again would otherwise study on the zone of the country they left.
 */
interface LearnerTimezoneWriter
{
    /** @param string $ianaZone a validated IANA identifier, e.g. "Europe/Kyiv" */
    public function updateTimezone(UserId $user, string $ianaZone): void;
}
