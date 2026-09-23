<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * РАЗГОВОР КОНЧИЛСЯ ПО ЛИМИТУ: a talk should end with a goodbye (`natural`); `limit` — it ran out of turns, minutes or
 * money before it got there.
 */
final class TalkEndedByLimit implements PlanCheck
{
    public const CODE = 'talk_ended_by_limit';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->talks as $talk) {
            if ($talk->endedReason !== 'limit') {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::WARNING, $talk->day, 'talk', $talk->id,
                "Разговор дня {$talk->day} закончился по лимиту, а не прощанием",
                ['conversation_id' => $talk->id],
            );
        }

        return $out;
    }
}
