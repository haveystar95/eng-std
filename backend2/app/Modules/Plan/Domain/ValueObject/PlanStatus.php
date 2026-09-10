<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Plan\Domain\Entity\Plan;

/**
 * Where a plan is in its life.
 *
 * `building` → the model is being asked; `unclear` → the model refused the goal (a legitimate
 * answer, shown to the learner); `failed` → two attempts did not produce a plan; `ready` → scenes
 * exist and day 1 is being written, the learner sees the preview and may press «Начать»;
 * `active` → started; `finished` → closed by the learner; `overdue` → the event date passed with
 * the plan still open (derived at read time, see {@see Plan::effectiveStatus()});
 * `deleted` → removed from the menu.
 */
enum PlanStatus: string
{
    case Building = 'building';
    case Unclear = 'unclear';
    case Failed = 'failed';
    case Ready = 'ready';
    case Active = 'active';
    case Finished = 'finished';
    case Overdue = 'overdue';
    case Deleted = 'deleted';

    /** A plan the learner still works with — the one the tab shows first. */
    public function isLive(): bool
    {
        return $this === self::Active || $this === self::Overdue;
    }

    public function isBuilt(): bool
    {
        return in_array($this, [self::Ready, self::Active, self::Finished, self::Overdue], true);
    }
}
