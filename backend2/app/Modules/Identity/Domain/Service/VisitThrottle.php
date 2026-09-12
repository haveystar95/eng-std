<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Service;

use DateTimeImmutable;

/**
 * ONE VISIT PER HALF HOUR. The app says «I'm here» every time it comes to the foreground, and a
 * learner flipping between apps for ten minutes is one visit, not twelve — twelve would drag the
 * median of the usual visit time ({@see UsualVisitTime}) towards whatever minute they fidgeted in.
 *
 * Measured from the last RECORDED visit, not from the last attempt: a learner who keeps the app
 * open and pings every 20 minutes still gets a row every 40.
 */
final class VisitThrottle
{
    public const MINUTES = 30;

    public static function shouldRecord(?DateTimeImmutable $lastRecorded, DateTimeImmutable $now): bool
    {
        if ($lastRecorded === null) {
            return true;
        }

        return $now->getTimestamp() - $lastRecorded->getTimestamp() >= self::MINUTES * 60;
    }
}
