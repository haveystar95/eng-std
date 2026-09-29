<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Service\Words;

/** `variant.longer` — a warning. A simplified variant is not longer than its line, in words. */
final class VariantLonger implements DialogueRule
{
    public const CODE = 'variant.longer';

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
            if ($line === null) {
                continue;
            }
            foreach ($line->simplifiedVariants as $variant) {
                if (Words::count($variant) > Words::count($line->textTarget)) {
                    $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), "the variant «{$variant}» has ".Words::count($variant).' words, the line '.Words::count($line->textTarget));
                    break;
                }
            }
        }

        return $out;
    }
}
