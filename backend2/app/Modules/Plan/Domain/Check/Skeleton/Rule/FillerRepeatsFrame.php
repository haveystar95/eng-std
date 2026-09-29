<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * `filler.repeats_frame` — a warning (FILLERS: «a filler never repeats a word that stands next to the slot in the frame, in
 * either language» — «Here is my ___» + «my passport»). The word right before the slot or right after it is the filler's
 * last or first word, in the same language.
 */
final class FillerRepeatsFrame implements SkeletonRule
{
    public const CODE = 'filler.repeats_frame';

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
            foreach ($frame->phrase->fillers() as $index => $filler) {
                foreach ([[$frame->phrase->frameTarget, $filler->target], [$frame->phrase->frameNative, $filler->native]] as [$pattern, $value]) {
                    $repeated = self::repeated($pattern, $value);
                    if ($repeated !== null) {
                        $out[] = new LessonViolation(self::CODE, $frame->id().'.f'.($index + 1), "«{$value}» repeats «{$repeated}», the frame's word at the slot of «{$pattern}»");
                        break;
                    }
                }
            }
        }

        return $out;
    }

    private static function repeated(string $pattern, string $value): ?string
    {
        $parts = preg_split(FrameText::SLOT_PATTERN, $pattern, 2);
        $said = Words::tokens($value);
        if (! is_array($parts) || count($parts) < 2 || $said === []) {
            return null;
        }
        $before = Words::tokens($parts[0]);
        $after = Words::tokens($parts[1]);
        $left = $before === [] ? null : $before[count($before) - 1];
        $right = $after[0] ?? null;

        return match (true) {
            $left !== null && $left === $said[0] => $left,
            $right !== null && $right === $said[count($said) - 1] => $right,
            default => null,
        };
    }
}
