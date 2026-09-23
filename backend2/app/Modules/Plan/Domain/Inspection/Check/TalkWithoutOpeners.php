<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * РОЛЬ НЕ ОТКРЫЛА НИ ОДНОЙ КОНСТРУКЦИИ (наряд FIX-3 §7): the role's lines are there to open the door to the day's
 * constructions (`opens_target`). A talk in which the role spoke and opened none gave the learner nothing to say. Only
 * talks begun once the openings were being recorded are judged (наряд ADM-1, доработка): an older talk has no
 * `opens_target` on any line because the column did not exist — that is a grey mark on the page, not a finding.
 */
final class TalkWithoutOpeners implements PlanCheck
{
    public const CODE = 'talk_without_openers';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->talks as $talk) {
            if (! $talk->openersRecorded || $talk->roleLines === 0 || $talk->openers > 0) {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::WARNING, $talk->day, 'talk', $talk->id,
                "Разговор дня {$talk->day}: роль сказала {$talk->roleLines} реплик и не открыла ни одной конструкции",
                ['conversation_id' => $talk->id, 'role_lines' => $talk->roleLines],
            );
        }

        return $out;
    }
}
