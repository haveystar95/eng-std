<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\Words;

/** `partner.too_long` — a warning. A partner line is at most 18 words in the target language. */
final class PartnerTooLong implements SkeletonRule
{
    public const CODE = 'partner.too_long';

    public const MAX_WORDS = 18;

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
        foreach ($skeleton->partnerLines as $line) {
            $words = Words::count($line->textTarget);
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(self::CODE, $line->id, "{$words} words (at most ".self::MAX_WORDS.')');
            }
        }

        return $out;
    }
}
