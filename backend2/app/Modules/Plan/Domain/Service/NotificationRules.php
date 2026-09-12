<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use DateTimeImmutable;

/**
 * WHICH FACTS BECOME LETTERS, AND WHEN A LETTER IS STILL WORTH SENDING.
 *
 * - `plan_ready` → «План готов».
 * - `day_ready` → «День N собран», but only for N ≥ 2: day 1's lesson is written right after the
 *   plan, before «Начать» — its readiness is part of the plan being ready, and a second letter a
 *   minute after «План готов» would say the same thing twice.
 * - `days_skipped_rebuilt` → «Маршрут пересобран».
 * - `event_today` → «Сегодня …».
 * - `day_passed`, `event_passed` → no letter; the learner was there, or it is too late to help.
 *
 * The daily reminder is not an event: the tick decides it ({@see reminderDue()}).
 */
final class NotificationRules
{
    /** The reminder is sent in [usual time, usual time + 15 min) — one tick of the 15-minute schedule. */
    public const REMINDER_WINDOW_MINUTES = 15;

    public static function forEvent(PlanEventKind $kind, ?int $dayNumber): ?NotificationKind
    {
        return match ($kind) {
            PlanEventKind::PlanReady => NotificationKind::PlanReady,
            PlanEventKind::DayReady => $dayNumber !== null && $dayNumber >= 2 ? NotificationKind::DayReady : null,
            PlanEventKind::DaysSkippedRebuilt => NotificationKind::DaysSkippedRebuilt,
            PlanEventKind::EventToday => NotificationKind::EventToday,
            PlanEventKind::DayPassed, PlanEventKind::EventPassed => null,
        };
    }

    /**
     * Is the plan still one this letter is about, at the moment of sending? «План готов» is for a
     * plan that is ready or already started; everything else is for a live plan (active/overdue).
     * A deleted, finished or preview plan gets no letter — a rebuild the learner made in the
     * preview is their own tap, not news.
     */
    public static function allows(NotificationKind $kind, PlanStatus $effectiveStatus): bool
    {
        if ($kind === NotificationKind::PlanReady) {
            return in_array($effectiveStatus, [PlanStatus::Ready, PlanStatus::Active, PlanStatus::Overdue], true);
        }

        return $effectiveStatus->isLive();
    }

    /**
     * Is it reminder time? `$localNow` is in the learner's zone; `$usualMinutes` is their usual time
     * of day. The window wraps past midnight (23:50 + 15 min reaches 00:05) — with the usual time
     * rounded to a quarter hour it never does, but the rule does not lean on that.
     */
    public static function reminderDue(DateTimeImmutable $localNow, int $usualMinutes, bool $dayWaiting, bool $remindedToday): bool
    {
        if (! $dayWaiting || $remindedToday) {
            return false;
        }
        $now = (int) $localNow->format('G') * 60 + (int) $localNow->format('i');
        $since = ($now - $usualMinutes + 24 * 60) % (24 * 60);

        return $since < self::REMINDER_WINDOW_MINUTES;
    }
}
