<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/** `partner.item_unknown` — FATAL. A partner line delivers an item of `must_understand`: its number is one of the list. */
final class PartnerItemUnknown implements SkeletonRule
{
    public const CODE = 'partner.item_unknown';

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
        $items = count($context->survival->mustUnderstand);
        $out = [];
        foreach ($skeleton->partnerLines as $line) {
            if ($line->mustUnderstand < 1 || $line->mustUnderstand > $items) {
                $out[] = new LessonViolation(self::CODE, $line->id, "must_understand {$line->mustUnderstand} is no item of the list (1–{$items})");
            }
        }

        return $out;
    }
}
