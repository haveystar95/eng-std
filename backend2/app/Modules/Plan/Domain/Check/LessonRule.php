<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * One family of the validator's rules over the model's ANSWER (the lesson as written, before the
 * server assembles lines and shuffles answers). A rule only counts: it never edits the lesson and
 * never refuses it.
 */
interface LessonRule
{
    /** @return list<LessonViolation> */
    public function violations(Lesson $answer, LessonValidationContext $context): array;
}
