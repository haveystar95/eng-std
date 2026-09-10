<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;

/** A learner message's speaking key is a verbatim substring of its own text. */
final class SpeakingKeySubstringCheck implements LessonCheck
{
    public function name(): string
    {
        return 'speaking_key_substring';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                if (! $message->isLearner() || $message->speakingKey === null) {
                    continue;
                }
                if (! self::isSubstring($message->speakingKey, $message->textTarget)) {
                    $out[] = "exchange {$exchange->step}: key «{$message->speakingKey}» is not inside «{$message->textTarget}»";
                }
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson;
    }

    /** Verbatim first; then case-insensitive, because a key that differs only in case underlines the same words. */
    public static function isSubstring(string $key, string $text): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        return str_contains($text, $key) || mb_stripos($text, $key) !== false;
    }
}
