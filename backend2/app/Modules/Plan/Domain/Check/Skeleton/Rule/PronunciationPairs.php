<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\Skeleton\TargetSound;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * Every reading of a skeleton beside the native text it must not be: a frame's, a filler's, a word's.
 *
 * A NAME IS READ AS IT IS WRITTEN: a filler or a word written with a capital in BOTH languages — «Andrei» / «Андрей», a
 * town, a firm — has a native text that is itself the sound of the target («андрей»), and that is no reading copied from a
 * translation. Such a pair is left out; a frame never is (every frame opens with a capital in both languages). A word the
 * two languages share is read by its sound too — the rules ask {@see TargetSound} of every pair.
 */
final class PronunciationPairs
{
    /** @return list<array{0: string, 1: string, 2: string, 3: string}> address, reading, native text, target text */
    public static function of(Skeleton $skeleton): array
    {
        $out = [];
        foreach ($skeleton->frames as $frame) {
            $out[] = [$frame->id(), $frame->phrase->pronunciationNative, $frame->phrase->frameNative, $frame->phrase->frameTarget];
            foreach ($frame->phrase->fillers() as $index => $filler) {
                if (! self::isName($filler->target, $filler->native)) {
                    $out[] = [$frame->id().'.f'.($index + 1), $filler->pronunciationNative, $filler->native, $filler->target];
                }
            }
        }
        foreach ($skeleton->vocabulary as $item) {
            if (! self::isName($item->termTarget, $item->translationNative)) {
                $out[] = [$item->id, $item->pronunciationNative, $item->translationNative, $item->termTarget];
            }
        }

        return $out;
    }

    /** Written with a capital in both languages — a name, whose native text is its own sound. */
    public static function isName(string $target, string $native): bool
    {
        return preg_match('/^\p{Lu}/u', trim($target)) === 1 && preg_match('/^\p{Lu}/u', trim($native)) === 1;
    }
}
