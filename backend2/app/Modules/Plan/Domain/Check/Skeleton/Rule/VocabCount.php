<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/** `vocab.count` — FATAL. The number of words is within VOCABULARY_COUNT, the range the day was ordered with. */
final class VocabCount implements SkeletonRule
{
    public const CODE = 'vocab.count';

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
        $count = count($skeleton->vocabulary);

        return $count < $context->vocabularyMin || $count > $context->vocabularyMax
            ? [new LessonViolation(self::CODE, 'skeleton', "{$count} vocabulary items ({$context->vocabularyMin}–{$context->vocabularyMax})")]
            : [];
    }
}
