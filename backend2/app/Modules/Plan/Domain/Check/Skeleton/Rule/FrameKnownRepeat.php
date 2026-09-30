<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * `frame.known_repeat` — FATAL. A frame of the target is new: it is not a frame of the target an earlier day taught
 * (EARLIER_DAYS) — read as the words it says, case, marks, apostrophes and the pack's contractions aside ({@see
 * FrameText::targetIdentity()}); the earlier day's frame is served with the full stop the skeleton does not write.
 *
 * THE NATIVE FRAME IS NOT COMPARED (наряд GEN-4c-3, п. 331 again): the learner learns the frames of the target, the native
 * one is its translation and may be the same for two of them — «I worked at ___» and «I worked ___» are both «Я работал ___».
 * Compared in either language, the e2e ru→en refused the repair of day 2 that wrote the second, and the day was served «Я
 * работал на на гриле». One rule for the skeleton's answer and for every repair of it.
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
            $known[FrameText::targetIdentity($frame['target'], $context->target)] ??= "«{$frame['target']}» of day {$frame['day']}";
        }
        $out = [];
        foreach ($skeleton->frames as $frame) {
            $text = $frame->phrase->frameTarget;
            $was = trim($text) === '' ? null : ($known[FrameText::targetIdentity($text, $context->target)] ?? null);
            if ($was !== null) {
                $out[] = new LessonViolation(self::CODE, $frame->id(), "«{$text}» is the frame {$was}");
            }
        }

        return $out;
    }
}
