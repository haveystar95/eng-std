<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** The state of a scene's lesson — the one model call that writes a day's material. */
enum LessonStatus: string
{
    case Pending = 'pending';
    case Building = 'building';
    /**
     * The lesson is written, its photos are being found (DAY-UI-3): a day is ready when its pictures
     * are on it, not when the model answered. The wire does not know this state — see {@see wire()}.
     */
    case Illustrating = 'illustrating';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * What the client is told: the day is still being put together while its photos come, so
     * `illustrating` is `building` — the contract's four words stay four (an additive change).
     */
    public function wire(): string
    {
        return $this === self::Illustrating ? self::Building->value : $this->value;
    }
}
