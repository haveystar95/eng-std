<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\ConversationAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\TurnSpeaker;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The pack's voice for one line of a talk, over the same seam the day's voice is bought through
 * ({@see LineSpeaker}), stored under the turn's own name ({@see ConversationAudioStore}).
 *
 * THE SWALLOW LIVES HERE, in Infrastructure, and not in the service that asks for a line: what the
 * vendor refuses and why is a fact about the vendor, it goes to the log with the ids that let a
 * report count the turns that came back mute, and the move carries on regardless.
 */
final readonly class GenerationTurnSpeaker implements TurnSpeaker
{
    public function __construct(
        private LineSpeaker $speaker,
        private ConversationAudioStore $store,
    ) {}

    public function say(
        ConversationId $conversationId,
        ConversationTurnId $turnId,
        string $lang,
        string $text,
        VoiceGender $gender,
    ): array {
        if (trim($text) === '') {
            return ['audio' => null, 'latency_ms' => 0];
        }
        $startedAt = hrtime(true);
        $kept = null;
        try {
            $this->speaker->sayEach(
                $lang,
                [new LineToSay($turnId->value, $text, Speaker::Partner, $gender)],
                function (string $ref, SpokenAudio $audio) use ($conversationId, $turnId, &$kept): void {
                    $kept = $this->store->put($conversationId, $turnId, $audio);
                },
            );
        } catch (Throwable $e) {
            Log::warning('plan.conversation voice', [
                'conversation_id' => $conversationId->value,
                'turn_id' => $turnId->value,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        }

        /** @var TurnAudio|null $kept */
        return ['audio' => $kept, 'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000)];
    }
}
