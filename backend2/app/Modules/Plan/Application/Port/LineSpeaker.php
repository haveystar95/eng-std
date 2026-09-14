<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * Says a script of lines in ONE vendor call with the language pack's voices (DAY-UI-3): the dialogue
 * of a day with its two voices, or a batch of phrases or words with the learner's.
 *
 * An empty answer is «not now»: speech is switched off, the pack has no such voice, the vendor
 * refused the text or its sound did not cut into the lines — the client reads those lines with the
 * phone's voice until a later run buys them. A transient vendor error (a rate limit, a 5xx, the
 * network) propagates, so the job waits for the vendor's next window.
 */
interface LineSpeaker
{
    /**
     * @param  list<LineToSay>  $lines
     * @return array<string, SpokenAudio> ref → its audio
     */
    public function say(string $lang, array $lines): array;

    /** The key a file of this voice is stored under, so a stored one is found before buying. Null — no such voice. */
    public function voiceKeyFor(string $lang, VoiceGender $voice): ?string;
}
