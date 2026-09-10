<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * How a card went. `hinted` is a pass bought with a hint; `skipped` is a spoken card given up on
 * after two attempts — a failure without the penalty (no return to the stage, no return tomorrow).
 */
enum CardResult: string
{
    case Passed = 'passed';
    case Hinted = 'hinted';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isPass(): bool
    {
        return $this === self::Passed || $this === self::Hinted;
    }
}
