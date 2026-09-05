<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

/**
 * THE SHIFT OF «TODAY» FOR THE CURRENT REQUEST — a QA account's, or nobody's (наряд DAY-FIX-2).
 *
 * One mutable number, held for the life of the request and read by {@see ShiftableClock} every
 * time anyone asks what time it is. It is a holder and not a re-binding of {@see Clock} because
 * services are resolved ONCE per container — a standings reader built by an earlier request keeps
 * the clock it was given, and a clock re-bound after that is a clock nobody holds. The holder is
 * what every clock already holds, so setting it moves every reader at once.
 *
 * Zero for everybody the door is shut for; the middleware sets it at the start of every plan
 * request and never leaves a previous request's number behind.
 */
final class QaClockShift
{
    private int $days = 0;

    public function set(int $days): void
    {
        $this->days = $days;
    }

    public function days(): int
    {
        return $this->days;
    }
}
