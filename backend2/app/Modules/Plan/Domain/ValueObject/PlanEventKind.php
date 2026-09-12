<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * What happened to a plan, as the journal (`plan_events`) records it.
 *
 * `plan_ready` — the build finished `ready`; `day_ready` — a scene's lesson was written (the day
 * it stands on is known); `day_passed` — a day was closed; `days_skipped_rebuilt` — the route was
 * rebuilt shorter (today: only `PATCH …/schedule`; the server does not detect skipped days);
 * `event_today` — the learner's calendar reached the event date; `event_passed` — it went past it.
 */
enum PlanEventKind: string
{
    case PlanReady = 'plan_ready';
    case DayReady = 'day_ready';
    case DayPassed = 'day_passed';
    case DaysSkippedRebuilt = 'days_skipped_rebuilt';
    case EventToday = 'event_today';
    case EventPassed = 'event_passed';

    /** Happens at most once in a plan's life — the schema holds it too (a partial unique index). */
    public function isOncePerPlan(): bool
    {
        return in_array($this, [self::PlanReady, self::EventToday, self::EventPassed], true);
    }

    /** Belongs to one day of the route and carries its number. */
    public function isAboutADay(): bool
    {
        return $this === self::DayReady || $this === self::DayPassed;
    }
}
