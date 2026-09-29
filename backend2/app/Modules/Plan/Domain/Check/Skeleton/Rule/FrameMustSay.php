<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `frame.must_say` — FATAL. Every frame names the `must_say` items it serves: at least one, each a number of the list, none
 * named by another frame too.
 */
final class FrameMustSay implements SkeletonRule
{
    public const CODE = 'frame.must_say';

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
        $items = count($context->survival->mustSay);
        $out = [];
        $seen = [];
        foreach ($skeleton->frames as $frame) {
            if ($frame->mustSay === []) {
                $out[] = new LessonViolation(self::CODE, $frame->id(), 'the frame names no must_say item');
            }
            foreach ($frame->mustSay as $number) {
                if ($number < 1 || $number > $items) {
                    $out[] = new LessonViolation(self::CODE, $frame->id(), "must_say {$number} is no item of the list (1–{$items})");
                } elseif (isset($seen[$number])) {
                    $out[] = new LessonViolation(self::CODE, $frame->id(), "must_say {$number} is served by {$seen[$number]} too");
                } else {
                    $seen[$number] = $frame->id();
                }
            }
        }

        return $out;
    }
}
