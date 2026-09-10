<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** The state of a scene's lesson — the one model call that writes a day's material. */
enum LessonStatus: string
{
    case Pending = 'pending';
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
}
