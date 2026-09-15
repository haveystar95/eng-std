<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * ONE VISIT, NO STEP TWICE (`lesson_day.v4.5`, NATURAL ORDER OF ONE VISIT): «no two exchanges ask the same thing,
 * and a frame used twice takes two different fillers (exchange 8 must not repeat exchange 2's "How much is the
 * deposit?")». What a code can tell of «the same thing» is the same frame said with the same filler — a frame with
 * no slot said twice is the same sentence twice. The later exchange is the card: the repair keeps the frame and
 * brings a filler and a fact the visit has not had.
 */
final class VisitRules implements LessonRule
{
    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        $said = [];
        foreach ($answer->exchanges as $exchange) {
            $line = $exchange->learner();
            if (! $exchange->kind->takesFrame() || $line === null || $line->phraseId === null) {
                continue;
            }
            $key = $line->phraseId.'|'.mb_strtolower(trim((string) $line->filler));
            if (isset($said[$key])) {
                $out[] = new LessonViolation(
                    LessonCodes::EXCHANGE_REPEATS,
                    LessonViolation::exchange($exchange->step),
                    $line->filler === null
                        ? "exchange {$exchange->step} says {$line->phraseId} again, as exchange {$said[$key]} did"
                        : "exchange {$exchange->step} says {$line->phraseId} with «{$line->filler}», which exchange {$said[$key]} already said",
                );

                continue;
            }
            $said[$key] = $exchange->step;
        }

        return $out;
    }
}
