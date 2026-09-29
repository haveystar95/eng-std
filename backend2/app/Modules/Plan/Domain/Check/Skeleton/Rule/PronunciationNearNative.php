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
 * `pronunciation.near_native` — a warning. The reading of a frame or a filler CLOSE to its native text — alike by {@see NEAR}
 * or more, one minus the edit distance over the longer ({@see StageText::similarity()}), yet not the same (that is fatal) —
 * is likely the translation half-written as sound — unless it is as close to the target's own spelling, a word the two
 * languages share («operator» read «опэратор» beside «оператора», {@see TargetSound}). The card goes to a repair. A word's
 * reading is {@see VocabReading}'s.
 */
final class PronunciationNearNative implements SkeletonRule
{
    public const CODE = 'pronunciation.near_native';

    public const NEAR = 0.75;

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return false;
    }

    public function findings(Skeleton $skeleton, SkeletonContext $context): array
    {
        $out = [];
        foreach (PronunciationPairs::of($skeleton) as [$address, $reading, $native, $target]) {
            if (str_starts_with($address, 'v')) {
                continue;
            }
            $same = StageText::normal($reading) === StageText::normal($native);
            $alike = StageText::similarity($reading, $native);
            if (! $same && $alike >= self::NEAR && ! TargetSound::is($reading, $target, $native, $context->target->code)) {
                $out[] = new LessonViolation(self::CODE, $address, sprintf('the reading «%s» is close to the native text «%s» (%.2f)', $reading, $native, $alike));
            }
        }

        return $out;
    }
}
