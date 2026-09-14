<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\NativeWords;

/**
 * THE LEARNER'S GENDER IN THE LEARNER'S OWN LANGUAGE (`lesson_day.v4.4`, TEXT QUALITY): when it is
 * unknown, the learner's native lines, frames and fillers prefer constructions without gender; a
 * past form after «я» («я работал», «я была») is counted — the prompt allows the masculine only when
 * nothing else can be said, and whether that was the case is a human's reading.
 */
final class NativeRules implements LessonRule
{
    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        if ($context->learnerGender !== null || ! NativeWords::isChecked($context->nativeLang)) {
            return [];
        }

        /** @var list<array{0: string, 1: string}> $texts */
        $texts = [];
        foreach ($answer->exchanges as $exchange) {
            $learner = $exchange->learner();
            if ($learner !== null) {
                $texts[] = [LessonViolation::learner($exchange->step), $learner->textNative];
            }
        }
        foreach ($answer->phrases as $phrase) {
            $texts[] = [$phrase->id, $phrase->frameNative];
            foreach ($phrase->fillers() as $index => $filler) {
                $texts[] = [$phrase->id.'.f'.($index + 1), $filler->native];
            }
        }

        $out = [];
        foreach ($texts as [$address, $text]) {
            $forms = NativeWords::genderedPast($text);
            if ($forms !== []) {
                $out[] = new LessonViolation(LessonCodes::NATIVE_GENDERED_PAST, $address, '«'.$text.'» says «я '.implode('», «я ', $forms).'» while the learner\'s gender is unknown');
            }
        }

        return $out;
    }
}
