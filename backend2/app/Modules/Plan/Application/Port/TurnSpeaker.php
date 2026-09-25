<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE ROLE'S VOICE FOR ONE TURN (наряд CONV-1, п. 5) — the same vendor and the same pack voice the
 * day uses for that scene's partner, bought line by line as the talk goes: the voice fixed for the scene (наряд FIX-4c
 * §1), so the registrar and the doctor of one rehearsal are two voices even when both are women.
 *
 * NOTHING BEHIND THIS PORT MAY FAIL THE TURN. A line without sound is still a line: the phone reads
 * it with its own voice, exactly as it does for a day whose voice has not been bought yet. So every
 * refusal of the vendor — the concurrency limit, an account that cannot pay, a text it will not read
 * — comes back here as «no audio», and the learner gets their answer without waiting for anybody.
 */
interface TurnSpeaker
{
    /** @return array{audio: TurnAudio|null, latency_ms: int} */
    public function say(
        ConversationId $conversationId,
        ConversationTurnId $turnId,
        string $lang,
        string $text,
        VoiceGender $gender,
        ?string $voice = null,
    ): array;
}
