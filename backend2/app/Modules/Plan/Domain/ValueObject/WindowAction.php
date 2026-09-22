<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * The day window's one action (DAY-UI-2): «Начать», «Продолжить». A passed day has none: «Ещё раз» belongs to each
 * stage's row (`stages[].again`, наряд FIX-3 §8), not to the day.
 */
enum WindowAction: string
{
    case Start = 'start';
    case Continue = 'continue';
}
