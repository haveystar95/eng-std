<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `skeleton.ids` — FATAL (a shape guard beside the order's list, наряд GEN-4). A frame, a partner line and a word are each
 * named once: the dialogue stands its lines on frames by id, the lesson's words say where they are by id — two cards under
 * one id cannot be told apart.
 */
final class SkeletonIds implements SkeletonRule
{
    public const CODE = 'skeleton.ids';

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
        foreach ([
            array_map(static fn ($f): string => $f->id(), $skeleton->frames),
            array_map(static fn ($l): string => $l->id, $skeleton->partnerLines),
            array_map(static fn ($v): string => $v->id, $skeleton->vocabulary),
        ] as $ids) {
            foreach (array_count_values($ids) as $id => $times) {
                if ($times > 1) {
                    $out[] = new LessonViolation(self::CODE, (string) $id, "{$times} cards are named {$id}");
                }
            }
        }

        return $out;
    }
}
