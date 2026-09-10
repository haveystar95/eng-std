<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * One check over a lesson (`docs/plan-v2.md` §5). A check only ever COUNTS on its own; what
 * happens to the lesson is decided by the mode the runner hands it — observe, drop or gate.
 */
interface LessonCheck
{
    /** The config key and the counter's name. */
    public function name(): string;

    /** May the mode be switched at all? A check that is «observe навсегда» ignores its config. */
    public function switchable(): bool;

    /**
     * Every violation, as a human-readable detail.
     *
     * @return list<string>
     */
    public function violations(Lesson $lesson, LessonContext $context): array;

    /** The lesson with the broken marks or fields erased — what `drop` means for THIS check. */
    public function drop(Lesson $lesson, LessonContext $context): Lesson;
}
