<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Service\FrameText;

/** `line.too_long` — a warning. A learner line is at most 10 words, not counting its glue ({@see FrameText::wordsWithoutGlue()}). */
final class LineTooLong implements DialogueRule
{
    public const CODE = 'line.too_long';

    public const MAX_WORDS = 10;

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
            $words = $line === null ? 0 : FrameText::wordsWithoutGlue($line->textTarget);
            if ($line !== null && $words > self::MAX_WORDS) {
                $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), "«{$line->textTarget}» has {$words} words without the glue (at most ".self::MAX_WORDS.')');
            }
        }

        return $out;
    }
}
