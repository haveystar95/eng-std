<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\ConversationAudioStore;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;
use App\Modules\Plan\Infrastructure\Eloquent\ConversationTurnModel;
use Illuminate\Contracts\Filesystem\Factory as Disks;

/**
 * `plan-audio/conversations/<talk>/<turn>.<ext>` on the day's own private disk. The turn row holds
 * the path, so reading back is one lookup by the turn's id — the address a phone is handed.
 */
final readonly class DiskConversationAudioStore implements ConversationAudioStore
{
    public function __construct(private Disks $disks, private string $disk) {}

    public function put(ConversationId $conversationId, ConversationTurnId $turnId, SpokenAudio $audio): TurnAudio
    {
        $path = 'plan-audio/conversations/'.$conversationId->value.'/'.$turnId->value.'.'.$audio->format;
        $this->disks->disk($this->disk)->put($path, $audio->bytes);

        return new TurnAudio(
            path: $path,
            format: $audio->format,
            durationMs: $audio->durationMs,
            voiceKey: $audio->voiceKey,
            characters: $audio->characters,
            credits: $audio->credits,
            costUsd: $audio->costUsd,
            requestId: $audio->requestId,
        );
    }

    public function read(string $turnId): ?string
    {
        $row = ConversationTurnModel::query()->where('id', $turnId)->first();
        if ($row === null || $row->audio_path === null) {
            return null;
        }
        $disk = $this->disks->disk($this->disk);

        return $disk->exists($row->audio_path) ? $disk->get($row->audio_path) : null;
    }
}
