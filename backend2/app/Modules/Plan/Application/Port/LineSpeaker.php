<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\SpokenAudio;

/**
 * Says one line in one language with the language pack's premium voice. Null when the pack has
 * no voice for the language or the pipe is switched off — the client then uses the system voice.
 */
interface LineSpeaker
{
    public function speak(string $text, string $lang): ?SpokenAudio;

    /** The voice key + variant a file would be stored under, so a stored one is found before buying. */
    public function voiceKeyFor(string $lang): ?string;
}
