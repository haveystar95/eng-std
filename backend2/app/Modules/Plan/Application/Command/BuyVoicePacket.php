<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\VoicePacket;

/** One vendor call of the voice backfill (DAY-UI-3): a scene's dialogue, or a packet of phrases or words of several scenes. */
final readonly class BuyVoicePacket
{
    public function __construct(public VoicePacket $packet) {}
}
