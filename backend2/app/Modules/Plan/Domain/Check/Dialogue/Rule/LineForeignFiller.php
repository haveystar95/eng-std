<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `line.foreign_filler` — FATAL. The filler a learner line names is one of its frame's fillers, as the skeleton spells it; a
 * frame without a slot is said with no filler.
 */
final class LineForeignFiller implements DialogueRule
{
    public const CODE = 'line.foreign_filler';

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
            $frame = $line?->phraseId === null ? null : $context->skeleton->frame($line->phraseId)?->phrase;
            if ($line === null || $frame === null || ! $e->exchange->kind->takesFrame()) {
                continue;
            }
            if ($frame->slot === null && $line->filler !== null) {
                $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), "{$frame->id} has no slot, the line names the filler «{$line->filler}»");
            } elseif ($frame->slot !== null && $frame->filler($line->filler) === null) {
                $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), 'the filler «'.($line->filler ?? 'null')."» is not one of {$frame->id}'s");
            }
        }

        return $out;
    }
}
