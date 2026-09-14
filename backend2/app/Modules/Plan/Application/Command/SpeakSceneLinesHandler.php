<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\RoleLineQueue;

/**
 * Buys the partner's lines of a scene once — only the ones the store does not have for this voice
 * ({@see RoleLineQueue}); the learner's phrases and the words stay on the phone's voice. A vendor
 * refusal skips the line; a transient error propagates so the job retries with what was already
 * stored kept.
 */
final readonly class SpeakSceneLinesHandler
{
    public function __construct(
        private RoleLineQueue $queue,
        private LineSpeaker $speaker,
        private LineAudioStore $store,
    ) {}

    public function __invoke(SpeakSceneLines $command): void
    {
        $owed = $this->queue->owed($command->sceneId);
        if ($owed === null) {
            return;
        }
        foreach ($owed->lines as $ref => $text) {
            $audio = $this->speaker->speak($text, $owed->lang);
            if ($audio === null) {
                continue;
            }
            $this->store->put($command->sceneId, $ref, $audio->voiceKey, $audio->format, $audio->bytes, $audio->durationMs, $audio->costUsd);
        }
    }
}
