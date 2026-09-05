<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Shared\Domain\Service\Clock;
use DateTimeImmutable;

/**
 * THE CLOCK EVERY SERVICE HOLDS — the system's, moved by the request's QA shift when there is one.
 *
 * Decorates the base {@see Clock} once, at binding time; the shift itself lives in
 * {@see QaClockShift} and is read on every `now()`, so a service resolved before the middleware ran
 * still sees the shifted day. With no shift set this is the system clock and costs one method call.
 */
final readonly class ShiftableClock implements Clock
{
    public function __construct(
        private Clock $base,
        private QaClockShift $shift,
    ) {}

    public function now(): DateTimeImmutable
    {
        return ShiftedClock::shift($this->base->now(), $this->shift->days());
    }
}
