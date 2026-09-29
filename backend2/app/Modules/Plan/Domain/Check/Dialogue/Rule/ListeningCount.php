<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/** `listening.count` — FATAL. The listening has 3 to 5 questions. */
final class ListeningCount implements DialogueRule
{
    public const CODE = 'listening.count';

    public const MIN = 3;

    public const MAX = 5;

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $count = count($dialogue->listening);

        return $count < self::MIN || $count > self::MAX
            ? [new LessonViolation(self::CODE, 'dialogue', "{$count} listening questions (".self::MIN.'–'.self::MAX.')')]
            : [];
    }
}
