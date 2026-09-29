<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\StageRule;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/** One rule of {@see DialogueCheck}: what it finds in a dialogue, read against its skeleton, each finding at its card. */
interface DialogueRule extends StageRule
{
    /** @return list<LessonViolation> */
    public function findings(Dialogue $dialogue, DialogueContext $context): array;
}
