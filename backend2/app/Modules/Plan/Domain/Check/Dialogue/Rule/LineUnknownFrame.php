<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/** `line.unknown_frame` — FATAL. The learner line of an answer or an ask exchange stands on a frame of the skeleton (`phrase_id`). */
final class LineUnknownFrame implements DialogueRule
{
    public const CODE = 'line.unknown_frame';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $out = [];
        foreach ($dialogue->exchanges as $e) {
            $line = $e->exchange->learner();
            if ($line === null || ! $e->exchange->kind->takesFrame()) {
                continue;
            }
            if ($line->phraseId === null || $context->skeleton->frame($line->phraseId) === null) {
                $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), 'phrase_id «'.($line->phraseId ?? 'null').'» is no frame of the skeleton');
            }
        }

        return $out;
    }
}
