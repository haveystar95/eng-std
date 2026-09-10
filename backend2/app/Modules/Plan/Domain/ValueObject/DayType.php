<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** What a calendar day of the plan is: a scene (one lesson), a review of what came before, or the rehearsal. */
enum DayType: string
{
    case Scene = 'scene';
    case Review = 'review';
    case Rehearsal = 'rehearsal';
}
