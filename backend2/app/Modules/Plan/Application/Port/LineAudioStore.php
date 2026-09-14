<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * The audio files of what a scene says out loud — both speakers' lines, the phrases, the words
 * (DAY-UI-3): bytes on a disk, one row per (scene, line reference, voice). The row is what a reader
 * resolves an address from; the file is read only on download.
 */
interface LineAudioStore
{
    /**
     * @param  list<string>  $sceneIds
     * @param  list<string>  $voiceKeys  the voices asked for — a scene speaks with two
     * @return array<string, LineAudioRow> keyed `<scene id>:<line ref>:<voice key>` — one query for all the scenes a day touches
     */
    public function forScenes(array $sceneIds, array $voiceKeys): array;

    public function find(string $audioId): ?LineAudioRow;

    /**
     * Writes the bytes and the row; returns the row, or null when this voice already had the line —
     * then nothing is written at all, the file behind an address already handed out stays as it was.
     */
    public function put(PlanSceneId $sceneId, string $lineRef, string $voiceKey, string $format, string $bytes, ?int $durationMs, ?string $costUsd): ?LineAudioRow;

    public function read(LineAudioRow $row): ?string;
}
