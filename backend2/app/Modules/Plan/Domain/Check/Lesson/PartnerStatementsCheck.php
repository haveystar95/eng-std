<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;

/** At least three partner lines are statements, not questions — counted, never acted on. */
final class PartnerStatementsCheck implements LessonCheck
{
    public const MIN_STATEMENTS = 3;

    public function name(): string
    {
        return 'partner_statements';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $statements = 0;
        foreach ($lesson->exchanges as $exchange) {
            $partner = $exchange->partner();
            if ($partner !== null && ! $partner->isQuestion()) {
                $statements++;
            }
        }

        return $statements < self::MIN_STATEMENTS
            ? ["only {$statements} partner statements (min ".self::MIN_STATEMENTS.')']
            : [];
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson;
    }
}
