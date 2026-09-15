<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Service\SceneVoiceQueue;

/**
 * Deletes a scene's unread lines ({@see SceneVoiceQueue::unread()}) — rows and files — and returns how many there were
 * (TTS-2). What the scene's voice then owes is exactly those lines, in the voice their speaker has now.
 */
final readonly class DropUnreadVoiceHandler
{
    public function __construct(
        private SceneVoiceQueue $queue,
        private LineAudioStore $store,
    ) {}

    public function __invoke(DropUnreadVoice $command): int
    {
        $unread = $this->queue->unread($command->sceneId);
        foreach ($unread as $row) {
            $this->store->drop($row);
        }

        return count($unread);
    }
}
