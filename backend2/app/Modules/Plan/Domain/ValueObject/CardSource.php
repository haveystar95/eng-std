<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** Written for today's scene, or returned from an earlier day the unit failed on. */
enum CardSource: string
{
    case Today = 'today';
    case Returned = 'returned';
}
