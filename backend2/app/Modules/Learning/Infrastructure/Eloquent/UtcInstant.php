<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Bind an instant AS AN INSTANT.
 *
 * The query builder turns a DateTimeInterface into a bare `Y-m-d H:i:s` string IN THE OBJECT'S OWN
 * ZONE (Connection::prepareBindings) — no offset — and Postgres then reads that string in the
 * session zone, UTC. So anything that reaches a binding carrying a non-UTC offset silently moves:
 * a due date computed as midnight in Europe/Bucharest landed in the column as midnight UTC and came
 * back to the phone as 03:00 (QA-BUG-1), and an answer timestamped 23:30+03:00 by the device landed
 * as 23:30Z — 02:30 of the NEXT Bucharest day, which is a different day for the streak and the
 * activity calendar (QAF-3).
 *
 * Converting first makes the string say what the instant means. Every write of a client-supplied
 * timestamp, and every comparison against one, goes through here. Already-UTC values (the clock's)
 * pass through unchanged, so applying it is never wrong — only sometimes redundant.
 */
final class UtcInstant
{
    public static function bind(?DateTimeImmutable $value): ?DateTimeImmutable
    {
        return $value?->setTimezone(new DateTimeZone('UTC'));
    }
}
