<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/** `frame.too_long` — a warning. The frame part — everything outside the slot, in the target language — is at most 7 words. */
final class FrameTooLong implements SkeletonRule
{
    public const CODE = 'frame.too_long';

    public const MAX_WORDS = 7;

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
        foreach ($skeleton->frames as $frame) {
            $words = Words::count((string) preg_replace(FrameText::SLOT_PATTERN, ' ', $frame->phrase->frameTarget));
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(self::CODE, $frame->id(), "the frame part of «{$frame->phrase->frameTarget}» is {$words} words (at most ".self::MAX_WORDS.')');
            }
        }

        return $out;
    }
}
