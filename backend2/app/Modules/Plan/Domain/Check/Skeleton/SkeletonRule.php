<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\StageRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/** One rule of {@see SkeletonCheck}: what it finds in a skeleton, each finding at the card it stands at. */
interface SkeletonRule extends StageRule
{
    /** @return list<LessonViolation> */
    public function findings(Skeleton $skeleton, SkeletonContext $context): array;
}
