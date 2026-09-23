<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ДЕНЬ FAILED: the day's lesson did not get past the gate (a fatal code left after the repairs) or its build failed.
 * The learner cannot have this day until it is rebuilt by hand (DECISIONS п. 334 — a failed lesson is not retried by itself).
 */
final class DayFailed implements PlanCheck
{
    public const CODE = 'day_failed';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->days as $day) {
            if ($day->lessonStatus !== 'failed') {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $day->number, 'day', (string) $day->number,
                "День {$day->number}: урок не собран".($day->failReason === null ? '' : " — {$day->failReason}"),
                ['fail_reason' => $day->failReason],
            );
        }

        return $out;
    }
}
