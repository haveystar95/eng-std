<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The two facts about the learner the plan reads from Identity: what day it is for them (days
 * open one per CALENDAR day in their own zone), and which language they read in.
 */
interface LearnerCalendar
{
    public function timezoneFor(UserId $user): DateTimeZone;

    /** Today at 00:00 in the learner's zone. */
    public function todayFor(UserId $user, DateTimeImmutable $now): DateTimeImmutable;

    public function nativeLangFor(UserId $user): LanguageCode;
}
