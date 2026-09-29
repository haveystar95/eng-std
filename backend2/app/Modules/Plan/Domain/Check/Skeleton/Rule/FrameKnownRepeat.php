<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * `frame.known_repeat` — FATAL. A frame is new: it is not a Frame of EARLIER_DAYS in either language — the same string, its
 * case, its run of spaces and the mark it ends with aside ({@see FrameText::identity()}): the earlier day's frame is served
 * with the full stop the skeleton does not write.
 */
final class FrameKnownRepeat implements SkeletonRule
{
    public const CODE = 'frame.known_repeat';

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
        $known = [];
        foreach ($context->earlierDays->frames() as $frame) {
            $known[FrameText::identity($frame['target'])] = "«{$frame['target']}» of day {$frame['day']}";
            $known[FrameText::identity($frame['native'])] = "«{$frame['native']}» of day {$frame['day']}";
        }
        $out = [];
        foreach ($skeleton->frames as $frame) {
            foreach ([$frame->phrase->frameTarget, $frame->phrase->frameNative] as $text) {
                $was = $known[FrameText::identity($text)] ?? null;
                if ($was !== null && trim($text) !== '') {
                    $out[] = new LessonViolation(self::CODE, $frame->id(), "«{$text}» is the frame {$was}");
                    break;
                }
            }
        }

        return $out;
    }
}
