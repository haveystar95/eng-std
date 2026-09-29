<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `frame.missing_item` — a warning, not fatal: an item of `must_say` no frame serves may be one the plan's earlier days taught
 * already (WHEN THE SET IS NOT PERFECT: «an item whose natural frame is already a Frame of EARLIER_DAYS → no frame for it»),
 * and whether it is, the code cannot tell.
 */
final class FrameMissingItem implements SkeletonRule
{
    public const CODE = 'frame.missing_item';

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
        foreach ($context->survival->mustSay as $index => $item) {
            if ($skeleton->frameOfItem($index + 1) === null) {
                $out[] = new LessonViolation(self::CODE, 'skeleton', 'must_say '.($index + 1)." «{$item['text']}» has no frame");
            }
        }

        return $out;
    }
}
