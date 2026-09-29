<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue;

use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * A LEARNER LINE IS ITS FRAME WITH ITS FILLER (`lesson_dialogue.v1`, WHAT IS FIXED: «the frame with its in_dialogue filler
 * substituted for ___, in TARGET_LANGUAGE and in NATIVE_LANGUAGE»; наряд GEN-4: «допускается только префикс-связка до
 * запятой»). What the comparison forgives, and nothing else: a short glue before the frame that ends in a comma («Da, »,
 * «Bine, », at most three words), the case of the frame's first letter after it, and the mark the sentence ends with — the
 * skeleton writes its frames without their full stop, the lesson closes them.
 */
final class LearnerLine
{
    private const GLUE = '/^[¡¿]?(?:[\p{L}\'’]+\s*){1,3},\s+$/u';

    /** The frame said with the filler in one language — `$side` 'target' or 'native'; null when a slot is left unfilled. */
    public static function core(Phrase $frame, ?Filler $filler, string $side): ?string
    {
        $pattern = $side === 'target' ? $frame->frameTarget : $frame->frameNative;
        $value = $filler === null ? null : ($side === 'target' ? $filler->target : $filler->native);
        $core = FrameText::fill($pattern, $value);

        return FrameText::hasSlot($core) ? null : $core;
    }

    /** Is `$line` the `$core` — as it is, or after a glue that ends in a comma? */
    public static function says(string $line, string $core): bool
    {
        $line = FrameText::withoutEndMark($line);
        $core = FrameText::withoutEndMark($core);
        if ($core === '') {
            return false;
        }
        if (self::sameButFirstLetter($line, $core)) {
            return true;
        }
        $length = mb_strlen($core);
        if (mb_strlen($line) <= $length) {
            return false;
        }
        $glue = mb_substr($line, 0, mb_strlen($line) - $length);

        return preg_match(self::GLUE, $glue) === 1 && self::sameButFirstLetter(mb_substr($line, -$length), $core);
    }

    private static function sameButFirstLetter(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if ($a === '' || $b === '' || mb_substr($a, 1) !== mb_substr($b, 1)) {
            return false;
        }

        return mb_strtolower(mb_substr($a, 0, 1)) === mb_strtolower(mb_substr($b, 0, 1));
    }
}
