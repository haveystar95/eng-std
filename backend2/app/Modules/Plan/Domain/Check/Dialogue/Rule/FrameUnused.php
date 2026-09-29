<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/** `frame.unused` — FATAL. Every frame of the skeleton is said: some learner line stands on it. */
final class FrameUnused implements DialogueRule
{
    public const CODE = 'frame.unused';

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
        $said = [];
        foreach ($dialogue->exchanges as $e) {
            $id = $e->exchange->learner()?->phraseId;
            if ($id !== null) {
                $said[$id] = true;
            }
        }
        $out = [];
        foreach ($context->skeleton->frames as $frame) {
            if (! isset($said[$frame->id()])) {
                $out[] = new LessonViolation(self::CODE, 'dialogue', "no learner line stands on {$frame->id()} «{$frame->phrase->frameTarget}»");
            }
        }

        return $out;
    }
}
