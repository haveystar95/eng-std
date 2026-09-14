<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * The day window's one action (DAY-UI-2): «Начать», «Продолжить», «Ещё раз». `again` is the day's
 * «Говорю сам» said once more over the cards the day already has — not the day walked anew.
 */
enum WindowAction: string
{
    case Start = 'start';
    case Continue = 'continue';
    case Again = 'again';
}
