<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\VoicePacketLine;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * Buys one packet of the voice backfill ({@see \App\Modules\Plan\Application\Service\VoiceBackfillQueue}): stores
 * the casts decided now — conditionally, a cast stored meanwhile wins — says the packet in ONE call, and keeps only
 * the owed lines, each under its own scene.
 *
 * Refs repeat across scenes (every scene has its `p1`), so the call is keyed by scene and ref; a packet keyed by the
 * ref alone would store one scene's phrase under another's. A call the vendor could not answer stores nothing — the
 * lines stay owed for the next run; a transient error (a rate limit) propagates to the command.
 */
final readonly class BuyVoicePacketHandler
{
    public function __construct(
        private LineSpeaker $speaker,
        private LineAudioStore $store,
        private PlanRepository $plans,
    ) {}

    public function __invoke(BuyVoicePacket $command): void
    {
        $packet = $command->packet;
        foreach ($packet->casts as $sceneId => $partner) {
            $this->plans->castSceneVoices(PlanSceneId::fromString((string) $sceneId), $partner);
        }

        /** @var array<string, VoicePacketLine> $byKey */
        $byKey = [];
        $script = [];
        foreach ($packet->lines as $line) {
            $key = $line->sceneId->value.':'.$line->line->ref;
            $byKey[$key] = $line;
            $script[] = new LineToSay($key, $line->line->text, $line->line->voice);
        }

        $spoken = $this->speaker->say($packet->lang, $script);
        foreach ($byKey as $key => $line) {
            $audio = $spoken[$key] ?? null;
            if ($audio !== null && $line->owed) {
                $this->store->put($line->sceneId, $line->line->ref, $audio->voiceKey, $audio->format, $audio->bytes, $audio->durationMs, $audio->costUsd);
            }
        }
    }
}
