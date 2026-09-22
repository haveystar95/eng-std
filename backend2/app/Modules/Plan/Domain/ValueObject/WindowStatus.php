<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE DAY WINDOW'S ONE WORD FOR THE DAY (DAY-UI-2): not started, being walked, passed.
 *
 * `locked` is not a state the window draws — a locked day never opens it — it is what the day
 * says when something asks for the window of a day that may not be walked, so the client refuses
 * honestly instead of drawing a start button over it. `building` — the day is next in line and its lesson is still being
 * written (наряд GEN-3 §11): no button, the client asks again.
 */
enum WindowStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Locked = 'locked';
    case Building = 'building';

    /**
     * From the day's EFFECTIVE status. Day one of a plan that is built and not started reads as not
     * started: its «Начать» starts the plan first (13.09, «Начать» в кабинете неначатого плана).
     */
    public static function of(DayStatus $effective, PlanStatus $plan, int $number, bool $building): self
    {
        return match (true) {
            $building => self::Building,
            $effective === DayStatus::Closed => self::Passed,
            $effective === DayStatus::InProgress => self::InProgress,
            $effective === DayStatus::Open => self::NotStarted,
            $plan === PlanStatus::Ready && $number === 1 => self::NotStarted,
            default => self::Locked,
        };
    }

    /** What the one button does; a passed day has none — «Ещё раз» is each stage's (наряд FIX-3 §8). */
    public function action(): ?WindowAction
    {
        return match ($this) {
            self::NotStarted => WindowAction::Start,
            self::InProgress => WindowAction::Continue,
            self::Passed, self::Locked, self::Building => null,
        };
    }
}
