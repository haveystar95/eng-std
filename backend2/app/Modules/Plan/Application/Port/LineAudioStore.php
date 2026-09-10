<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * The audio files of the partner's lines: bytes on a disk, one row per (scene, exchange step,
 * voice). The row is what a card reader resolves a URL from; the file is read only on download.
 */
interface LineAudioStore
{
    /**
     * @param  list<string>  $sceneIds
     * @return array<string, LineAudioRow> keyed `<scene id>:<step>` — one query for all the scenes a day touches
     */
    public function forScenes(array $sceneIds, string $voiceKey): array;

    public function find(string $audioId): ?LineAudioRow;

    /** Writes the bytes and the row; returns the row, or null when this voice already had the line. */
    public function put(PlanSceneId $sceneId, int $step, string $voiceKey, string $format, string $bytes, ?int $durationMs, ?string $costUsd): ?LineAudioRow;

    public function read(LineAudioRow $row): ?string;
}
