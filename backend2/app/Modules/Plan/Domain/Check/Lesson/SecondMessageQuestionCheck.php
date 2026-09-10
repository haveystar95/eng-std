<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;

/** The second message closes the exchange: it never ends with a question mark. */
final class SecondMessageQuestionCheck implements LessonCheck
{
    public function name(): string
    {
        return 'second_message_question';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach ($lesson->exchanges as $exchange) {
            $second = $exchange->second();
            if ($second !== null && $second->isQuestion()) {
                $out[] = "exchange {$exchange->step}: second message ends with «?» — «{$second->textTarget}»";
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson;
    }
}
