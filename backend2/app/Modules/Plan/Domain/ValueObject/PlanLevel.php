<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** The two levels both prompts know. The label is the exact word the prompts expect. */
enum PlanLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';

    public function promptLabel(): string
    {
        return match ($this) {
            self::Beginner => 'Beginner',
            self::Intermediate => 'Intermediate',
        };
    }
}
