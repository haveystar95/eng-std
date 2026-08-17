<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * Lifecycle of one practice dialog: active → (finished | expired). `expired` is what a dialog
 * becomes when its realtime token TTL lapses without an explicit finish; `finished` is terminal
 * and can still be reached from `expired` (the client asks for a summary after the audio ended).
 */
enum PracticeDialogStatus: string
{
    case Active = 'active';
    case Finished = 'finished';
    case Expired = 'expired';

    public function isFinished(): bool
    {
        return $this === self::Finished;
    }
}
