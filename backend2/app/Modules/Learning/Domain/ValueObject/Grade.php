<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/** How well the user recalled a term. Drives the scheduler; it is the only review input. */
enum Grade: string
{
    case Again = 'again';
    case Hard = 'hard';
    case Good = 'good';
    case Easy = 'easy';

    /** A "correct" answer for retention statistics. */
    public function isCorrect(): bool
    {
        return $this === self::Good || $this === self::Easy;
    }
}
