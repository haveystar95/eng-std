<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * What a check DOES when it fires. `observe` counts and keeps the answer as it is; `drop` erases
 * the broken mark or field and keeps the rest; `gate` refuses the answer, and the caller re-asks
 * once. The mode is configuration, per check, and every check ships as `observe`.
 */
enum CheckMode: string
{
    case Observe = 'observe';
    case Drop = 'drop';
    case Gate = 'gate';
}
