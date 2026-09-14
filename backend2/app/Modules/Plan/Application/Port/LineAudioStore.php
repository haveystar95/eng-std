<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * The audio files of a scene's spoken lines — the partner's line of each exchange and, since
 * DAY-UI-2, each phrase: bytes on a disk, one row per (scene, line reference, voice). The row is
 * what a reader resolves an address from; the file is read only on download.
 */
interface LineAudioStore
{
    /**
     * @param  list<string>  $sceneIds
     * @return array<string, LineAudioRow> keyed `<scene id>:<line ref>` — one query for all the scenes a day touches
     */
    public function forScenes(array $sceneIds, string $voiceKey): array;

    public function find(string $audioId): ?LineAudioRow;

    /** Writes the bytes and the row; returns the row, or null when this voice already had the line. */
    public function put(PlanSceneId $sceneId, string $lineRef, string $voiceKey, string $format, string $bytes, ?int $durationMs, ?string $costUsd): ?LineAudioRow;

    public function read(LineAudioRow $row): ?string;
}
