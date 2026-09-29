<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `frame.count` — FATAL. The frames are the survival set's: one frame per `must_say` item, an item left without a frame only
 * when the set is already learned. So the frames and the items left without one are never more than the items: a frame
 * beyond that serves nothing of the set. (Two items said as one pattern are one frame — the count holds them.)
 */
final class FrameCount implements SkeletonRule
{
    public const CODE = 'frame.count';

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
        $unserved = 0;
        for ($n = 1; $n <= $items; $n++) {
            $unserved += $skeleton->frameOfItem($n) === null ? 1 : 0;
        }
        $frames = count($skeleton->frames);

        return $frames + $unserved > $items
            ? [new LessonViolation(self::CODE, 'skeleton', "{$frames} frames and {$unserved} items without one for {$items} must_say items")]
            : [];
    }
}
