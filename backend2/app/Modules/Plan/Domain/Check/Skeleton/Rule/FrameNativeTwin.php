<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;

/** `frame.native_twin` — a warning. No two frames share `frame_native` ({@see FrameText::identity()}). */
final class FrameNativeTwin implements SkeletonRule
{
    public const CODE = 'frame.native_twin';

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
        $seen = [];
        $out = [];
        foreach ($skeleton->frames as $frame) {
            $key = FrameText::identity($frame->phrase->frameNative);
            if ($key === '') {
                continue;
            }
            if (isset($seen[$key])) {
                $out[] = new LessonViolation(self::CODE, $frame->id(), "«{$frame->phrase->frameNative}» is also the native frame of {$seen[$key]}");
            } else {
                $seen[$key] = $frame->id();
            }
        }

        return $out;
    }
}
