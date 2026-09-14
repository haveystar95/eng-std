<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE FRAMES (`lesson_day.v4.4`, FRAMES): as many as half to all of the answer/ask exchanges, each
 * said at least once, at most seven words outside the slot, at most a third without a slot, a native
 * rendering with no «в/на»-style alternatives that ends the way the frame ends.
 */
final class FrameRules implements LessonRule
{
    public const MAX_WORDS = 7;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        $frames = count($answer->phrases);
        $framed = count(array_filter($answer->exchanges, static fn (Exchange $e): bool => $e->kind->takesFrame()));
        if ($frames > $framed) {
            $out[] = new LessonViolation(LessonCodes::FRAME_COUNT, 'lesson', "{$frames} frames for {$framed} answer/ask exchanges (at most all of them)");
        } elseif ($frames * 2 < $framed) {
            $out[] = new LessonViolation(LessonCodes::FRAME_COUNT, 'lesson', "{$frames} frames for {$framed} answer/ask exchanges (at least half)");
        }

        $withoutSlot = 0;
        foreach ($answer->phrases as $phrase) {
            if ($answer->linesOf($phrase->id) === []) {
                $out[] = new LessonViolation(LessonCodes::FRAME_UNUSED, $phrase->id, "no learner line stands on «{$phrase->frameTarget}»");
            }

            $words = Words::count((string) preg_replace(FrameText::SLOT_PATTERN, ' ', $phrase->frameTarget));
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(LessonCodes::FRAME_TOO_LONG, $phrase->id, "«{$phrase->frameTarget}» has {$words} words outside the slot (max ".self::MAX_WORDS.')');
            }

            if ($phrase->slot === null || ! FrameText::hasSlot($phrase->frameTarget)) {
                $withoutSlot++;
            }

            if (preg_match('/\p{L}\s*\/\s*\p{L}|\(\p{L}{1,3}\)/u', $phrase->frameNative) === 1) {
                $out[] = new LessonViolation(LessonCodes::FRAME_NATIVE_ALTERNATIVES, $phrase->id, "«{$phrase->frameNative}» writes alternatives inside the frame");
            }

            $target = self::terminal($phrase->frameTarget);
            $native = self::terminal($phrase->frameNative);
            if ($target !== $native) {
                $out[] = new LessonViolation(
                    LessonCodes::FRAME_NATIVE_PUNCT,
                    $phrase->id,
                    '«'.$phrase->frameTarget.'» ends with '.self::named($target).', «'.$phrase->frameNative.'» with '.self::named($native),
                );
            }
        }

        if ($frames > 0 && $withoutSlot * 3 > $frames) {
            $out[] = new LessonViolation(LessonCodes::FRAME_NO_SLOT_SHARE, 'lesson', "{$withoutSlot} of {$frames} frames have no slot (at most a third)");
        }

        return $out;
    }

    /** The sentence mark a text ends with — `.`, `?`, `!` or `…` — or '' when it ends with none. */
    public static function terminal(string $text): string
    {
        // Closing quotes and brackets are not the sentence's mark (and a byte-wise rtrim would cut Cyrillic).
        $trimmed = (string) preg_replace('/[\s»"\'”’)]+$/u', '', $text);
        $last = mb_substr($trimmed, -1);

        return in_array($last, ['.', '?', '!', '…'], true) ? $last : '';
    }

    private static function named(string $mark): string
    {
        return $mark === '' ? 'no mark' : "«{$mark}»";
    }
}
