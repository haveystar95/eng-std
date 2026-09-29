<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Service\SpeakingKey;
use App\Modules\Plan\Domain\Service\Words;

/**
 * `speaking_key.wrong` — a warning (LEARNER MESSAGES: 1 to 4 consecutive words copied verbatim from text_target, taken ONLY
 * from the frame part). The key is no substring of its line, or it holds a word of the line's filler. (The day serves the key
 * of the frame, the server's — {@see SpeakingKey}; this reads what the model wrote.)
 */
final class SpeakingKeyWrong implements DialogueRule
{
    public const CODE = 'speaking_key.wrong';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return false;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $out = [];
        foreach ($dialogue->exchanges as $e) {
            $line = $e->exchange->learner();
            $key = trim((string) $line?->speakingKey);
            if ($line === null || $key === '') {
                continue;
            }
            if (mb_stripos($line->textTarget, $key) === false) {
                $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), "the key «{$key}» is not in «{$line->textTarget}»");

                continue;
            }
            $frame = $line->phraseId === null ? null : $context->skeleton->frame($line->phraseId)?->phrase;
            $filler = $frame?->filler($line->filler);
            if ($filler === null) {
                continue;
            }
            $shared = array_intersect(Words::tokens($key), Words::tokens($filler->target));
            if ($shared !== []) {
                $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), "the key «{$key}» holds «".implode(' ', $shared)."» of the filler «{$filler->target}»");
            }
        }

        return $out;
    }
}
