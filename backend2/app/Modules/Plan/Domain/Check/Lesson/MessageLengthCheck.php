<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\Words;

/** A partner line over 18 words, a learner line over 10 — counted, never acted on. */
final class MessageLengthCheck implements LessonCheck
{
    public const PARTNER_MAX_WORDS = 18;

    public const LEARNER_MAX_WORDS = 10;

    public function name(): string
    {
        return 'message_length';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                $words = Words::count($message->textTarget);
                $max = $message->isLearner() ? self::LEARNER_MAX_WORDS : self::PARTNER_MAX_WORDS;
                if ($words > $max) {
                    $out[] = "exchange {$exchange->step}: {$message->speaker} line has {$words} words (max {$max})";
                }
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson;
    }
}
