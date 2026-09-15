<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use DateTimeImmutable;

/**
 * The audio files of what a scene says out loud — both speakers' lines, the phrases and their fillers, the words
 * (DAY-UI-3, TTS-2): bytes on a disk, one row per (scene, line reference, voice), with what the line cost.
 * The row is what a reader resolves an address from; the file is read only on download.
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
     * Writes the bytes and the row; returns the row, or null when this voice already had the line — then nothing is
     * written at all, the file behind an address already handed out stays as it was.
     */
    public function put(PlanSceneId $sceneId, string $lineRef, SpokenAudio $audio): ?LineAudioRow;

    public function read(LineAudioRow $row): ?string;

    /** Credits the vendor debited for every line stored since this moment — the fuse's count when the vendor would not say. */
    public function creditsSince(DateTimeImmutable $since): int;
}
