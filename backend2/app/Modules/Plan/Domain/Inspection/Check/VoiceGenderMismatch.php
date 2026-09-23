<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ГОЛОС НЕ ПО ПОЛУ (DECISIONS п. 389): the learner speaks in the voice of their profile's gender, the partner in the voice
 * of the role's. A line whose only file is in the other gender's voice of its speaker was bought for the wrong person — the
 * reader looks for the cast's voice, does not find it, and the phone reads the line. A talk's role line said in a voice
 * other than its cast's is the same fault heard at once.
 */
final class VoiceGenderMismatch implements PlanCheck
{
    public const CODE = 'voice_gender_mismatch';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->lines as $line) {
            if ($line->isVoiced()) {
                continue;
            }
            foreach ($line->storedVoices as $key => $identity) {
                if ($identity === null || ! str_starts_with($identity, $line->speaker.':') || $identity === $line->speaker.':'.$line->expectedGender) {
                    continue;
                }
                $who = $line->speaker === 'learner' ? 'ученика' : 'собеседника';
                $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $line->firstDay(), 'line', $line->ref,
                    "Строка {$line->ref}: голос {$who} — {$identity}, а по правилу — {$line->expectedGender}; звучит телефон",
                    ['scene_id' => $line->sceneId, 'stored_voice' => $key, 'stored' => $identity, 'expected_gender' => $line->expectedGender],
                );
            }
        }
        foreach ($facts->talks as $talk) {
            foreach ($talk->voicedLines as $voiced) {
                if ($voiced['expected'] === null || $voiced['voice'] === $voiced['expected']) {
                    continue;
                }
                $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $talk->day, 'turn', $talk->id.'#'.$voiced['turn'],
                    "Разговор, ход {$voiced['turn']}: сказано голосом {$voiced['voice']}, а роли положен {$voiced['expected']}",
                    ['conversation_id' => $talk->id, 'turn' => $voiced['turn'], 'voice' => $voiced['voice'], 'expected' => $voiced['expected']],
                );
            }
        }

        return $out;
    }
}
