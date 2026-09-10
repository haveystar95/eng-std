<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\PhraseInMessage;

/** Every phrase is spoken by the learner in at least one exchange; `drop` removes the ones that are not. */
final class PhraseUnusedCheck implements LessonCheck
{
    public function name(): string
    {
        return 'phrase_unused';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach ($lesson->phrases as $phrase) {
            if (! self::spoken($lesson, $phrase)) {
                $out[] = "phrase {$phrase->id} «{$phrase->textTarget}» is in no learner message";
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson->withPhrases(array_values(array_filter(
            $lesson->phrases,
            static fn (Phrase $p): bool => self::spoken($lesson, $p),
        )));
    }

    public static function spoken(Lesson $lesson, Phrase $phrase): bool
    {
        foreach ($lesson->exchanges as $exchange) {
            $learner = $exchange->learner();
            if ($learner !== null && PhraseInMessage::matches($phrase->textTarget, $learner->textTarget)) {
                return true;
            }
        }

        return false;
    }
}
