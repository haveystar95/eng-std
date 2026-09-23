<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ДЕНЬ ПРОЙДЕН БЕЗ ИТОГА: closing a day writes its `day_passed` line in the plan's journal in the same transaction
 * (`CloseDayHandler`, Plan README) — the one stored record that the day was passed. A closed day without it was closed some
 * other way.
 */
final class PassedWithoutSummary implements PlanCheck
{
    public const CODE = 'passed_without_summary';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->days as $day) {
            if (! $day->closed || $day->hasPassedEvent) {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::WARNING, $day->number, 'day', (string) $day->number,
                "День {$day->number} закрыт, а записи итога (day_passed) нет",
            );
        }

        return $out;
    }
}
