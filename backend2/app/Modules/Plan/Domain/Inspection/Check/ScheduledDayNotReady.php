<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ДЕНЬ ОТКРЫТ ПО РАСПИСАНИЮ, НО НЕ READY: its calendar day has come and the day before it is closed, yet its scene's lesson
 * is not `ready` — the learner is at the door and the day is not there. A failed lesson is {@see DayFailed}'s, not this.
 */
final class ScheduledDayNotReady implements PlanCheck
{
    public const CODE = 'scheduled_day_not_ready';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->days as $day) {
            if (! $day->scheduledOpen || $day->closed || $day->lessonStatus === null || in_array($day->lessonStatus, ['ready', 'failed'], true)) {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $day->number, 'day', (string) $day->number,
                "День {$day->number} открыт по расписанию, а урок — {$day->lessonStatus}",
                ['lesson_status' => $day->lessonStatus],
            );
        }

        return $out;
    }
}
