<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * СТРОКА БЕЗ ЗВУКА (DECISIONS п. 309): the server voices everything a day says. A line with no file in its cast's voice is
 * read by the phone's synthesiser — the account refused (402), the fuse or the cap stopped the run, or the run has not come.
 * A language whose pack has no voice at all is reported the same way, with that reason. A line bought only in its speaker's
 * other-gender voice is phone-read too, but it is {@see VoiceGenderMismatch}'s finding — one fault, reported once.
 */
final class LineWithoutSound implements PlanCheck
{
    public const CODE = 'line_without_sound';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->lines as $line) {
            if ($line->isVoiced() || $line->hasOtherGenderVoice()) {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::WARNING, $line->firstDay(), 'line', $line->ref,
                $line->expectedVoice === null
                    ? "Строка {$line->ref}: у языка нет голоса — звучит телефон"
                    : "Строка {$line->ref}: нет файла голосом по правилу — звучит телефон",
                ['scene_id' => $line->sceneId, 'text' => $line->text, 'expected_voice' => $line->expectedVoice],
            );
        }

        return $out;
    }
}
