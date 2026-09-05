<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Shared\Domain\Service\Clock;
use DateTimeImmutable;

/**
 * A clock that runs N days ahead of another one — the QA plan clock's whole mechanism.
 *
 * Bound for ONE request of a QA account behind the open door
 * ({@see \App\Modules\Learning\Presentation\Http\Middleware\ShiftQaPlanClock}); everything that
 * asks {@see Clock} in that request — the plan's «today», the session's start, the day's census —
 * sees the shifted day. Nothing is written with the shift that the request itself does not write.
 */
final readonly class ShiftedClock implements Clock
{
    public function __construct(
        private Clock $base,
        private int $days,
    ) {}

    public function now(): DateTimeImmutable
    {
        return self::shift($this->base->now(), $this->days);
    }

    /** The same shift, applied to a date the client sent — a review's `answered_at`. */
    public static function shift(DateTimeImmutable $at, int $days): DateTimeImmutable
    {
        if ($days === 0) {
            return $at;
        }

        return $at->modify(($days > 0 ? '+' : '') . $days . ' days');
    }
}
