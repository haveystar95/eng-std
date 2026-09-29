<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\PartnerLine;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/** `partner.item_missing` — FATAL. Every `must_understand` item has a partner line — two when it holds two questions. */
final class PartnerItemMissing implements SkeletonRule
{
    public const CODE = 'partner.item_missing';

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
        $delivered = array_map(static fn (PartnerLine $l): int => $l->mustUnderstand, $skeleton->partnerLines);
        $out = [];
        foreach ($context->survival->mustUnderstand as $index => $item) {
            if (! in_array($index + 1, $delivered, true)) {
                $out[] = new LessonViolation(self::CODE, 'skeleton', 'must_understand '.($index + 1)." «{$item}» has no partner line");
            }
        }

        return $out;
    }
}
