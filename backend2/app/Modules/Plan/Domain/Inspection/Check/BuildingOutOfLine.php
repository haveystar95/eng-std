<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * BUILDING НЕ У СЛЕДУЮЩЕГО В ОЧЕРЕДИ (наряд GEN-3 §11): a lesson is written for the next day in line only — day 1 at the
 * start, day N+1 when day N closes. A scene being written (`building`, or `illustrating` — the wire's `building`) for any
 * other day is a build nobody should have asked for.
 */
final class BuildingOutOfLine implements PlanCheck
{
    public const CODE = 'building_out_of_line';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->days as $day) {
            if ($day->nextInLine || ! in_array($day->lessonStatus, ['building', 'illustrating'], true)) {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::WARNING, $day->number, 'day', (string) $day->number,
                "День {$day->number}: урок в сборке ({$day->lessonStatus}), а день не следующий в очереди",
                ['lesson_status' => $day->lessonStatus],
            );
        }

        return $out;
    }
}
