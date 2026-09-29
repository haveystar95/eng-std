<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Check\Skeleton\TargetSound;
use App\Modules\Plan\Domain\Check\StageText;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `pronunciation.equals_native` — FATAL. A reading is the SOUND of the target text, never its translation: the reading of a
 * frame is not its native frame, of a filler not its native value, of a word not its translation — read {@see StageText::normal()}.
 * Unless the native text IS the target's sound — a word both languages share, «taxi» / «такси» read «такси» ({@see TargetSound}).
 */
final class PronunciationEqualsNative implements SkeletonRule
{
    public const CODE = 'pronunciation.equals_native';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Skeleton $skeleton, SkeletonContext $context): array
    {
        $out = [];
        foreach (PronunciationPairs::of($skeleton) as [$address, $reading, $native, $target]) {
            if (StageText::normal($reading) !== '' && StageText::normal($reading) === StageText::normal($native)
                && ! TargetSound::is($reading, $target, $native, $context->target->code)) {
                $out[] = new LessonViolation(self::CODE, $address, "the reading «{$reading}» is the native text «{$native}»");
            }
        }

        return $out;
    }
}
