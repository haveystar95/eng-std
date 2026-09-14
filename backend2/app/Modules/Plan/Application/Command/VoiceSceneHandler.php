<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\SceneVoiceQueue;
use App\Modules\Plan\Domain\Repository\PlanRepository;

/**
 * Buys what a scene's voice still owes ({@see SceneVoiceQueue}) — the dialogue in one call with its
 * two voices, the phrases in one, the words in one — and keeps only the lines the store did not have.
 *
 * A scene written before voices had genders gets its cast stored first, so every later reader hears
 * the same two people. A call the vendor could not answer (refused, or its sound did not cut) is
 * skipped and its lines stay the phone's voice; a transient error (a rate limit) propagates with what
 * was already stored kept — the job waits for the vendor's next window and buys only the rest.
 */
final readonly class VoiceSceneHandler
{
    public function __construct(
        private SceneVoiceQueue $queue,
        private LineSpeaker $speaker,
        private LineAudioStore $store,
        private PlanRepository $plans,
    ) {}

    public function __invoke(VoiceScene $command): void
    {
        $debt = $this->queue->owed($command->sceneId);
        if ($debt === null) {
            return;
        }
        if ($debt->castIsNew) {
            $this->plans->castSceneVoices($command->sceneId, $debt->cast->partner);
        }

        foreach ($debt->batches as $batch) {
            $spoken = $this->speaker->say($debt->lang, $batch->lines);
            foreach ($batch->owed as $ref) {
                $audio = $spoken[$ref] ?? null;
                if ($audio !== null) {
                    $this->store->put($command->sceneId, $ref, $audio->voiceKey, $audio->format, $audio->bytes, $audio->durationMs, $audio->costUsd);
                }
            }
        }
    }
}
