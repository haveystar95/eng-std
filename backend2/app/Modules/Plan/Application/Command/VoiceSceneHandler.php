<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Exception\VoiceCapReached;
use App\Modules\Plan\Application\Exception\VoiceFuseTripped;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\SceneVoiceQueue;
use App\Modules\Plan\Application\Service\VoiceCap;
use App\Modules\Plan\Application\Service\VoiceFuse;

/**
 * Buys what a scene's voice still owes ({@see SceneVoiceQueue}) — every line on a call of its own, in the voice its
 * speaker has in the scene — and keeps each line as soon as it is bought (TTS-2).
 *
 * Two checks come first, and either stops the scene before a credit is spent: the credits cap of the run
 * ({@see VoiceCap}: what the run has bought plus what this scene would cost does not fit — {@see VoiceCapReached}), then
 * the fuse ({@see VoiceFuse}: too little of the vendor account left — {@see VoiceFuseTripped}). A line the vendor refused
 * to read stays the phone's voice; a transient error (the concurrency limit) or a refusal of the account propagates with
 * what was already bought kept — the job waits for the first and fails on the second.
 */
final readonly class VoiceSceneHandler
{
    public function __construct(
        private SceneVoiceQueue $queue,
        private LineSpeaker $speaker,
        private LineAudioStore $store,
        private VoiceCap $cap,
        private VoiceFuse $fuse,
    ) {}

    /** @throws VoiceCapReached|VoiceFuseTripped */
    public function __invoke(VoiceScene $command): void
    {
        $debt = $this->queue->owed($command->sceneId);
        if ($debt === null || $debt->isSettled()) {
            return;
        }
        $this->cap->assertRoom($debt, $command->spentThisRun);
        $this->fuse->assertOpen();

        $this->speaker->sayEach(
            $debt->lang,
            $debt->lines,
            function (string $ref, SpokenAudio $audio) use ($command): void {
                $this->store->put($command->sceneId, $ref, $audio);
            },
        );
    }
}
