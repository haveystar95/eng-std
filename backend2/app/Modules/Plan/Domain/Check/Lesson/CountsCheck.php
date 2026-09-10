<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;

/** The three counts the prompt was ordered with — phrases, vocabulary, exchanges — must be met exactly. */
final class CountsCheck implements LessonCheck
{
    public function name(): string
    {
        return 'counts';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        $pairs = [
            ['phrases', count($lesson->phrases), $context->phrasesCount],
            ['vocabulary', count($lesson->vocabulary), $context->vocabularyCount],
            ['dialogue', count($lesson->exchanges), $context->dialogueCount],
        ];
        foreach ($pairs as [$what, $got, $wanted]) {
            if ($got !== $wanted) {
                $out[] = "{$what}: {$got} instead of {$wanted}";
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson;
    }
}
