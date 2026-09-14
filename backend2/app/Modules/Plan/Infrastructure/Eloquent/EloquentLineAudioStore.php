<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Contracts\Filesystem\Factory as Disks;
use Illuminate\Support\Facades\DB;

/**
 * Bytes on the configured private disk under `plan-audio/<scene>/`, one row per (scene, line
 * reference, voice). The unique constraint is the idempotency: a second `put` for a line this voice
 * already has inserts no row, and its file lands on the very same path (the path is made of the
 * scene, the reference and the voice), so nothing is left behind.
 */
final class EloquentLineAudioStore implements LineAudioStore
{
    public function __construct(private readonly Disks $disks, private readonly string $disk) {}

    /** @param list<string> $sceneIds */
    public function forScenes(array $sceneIds, string $voiceKey): array
    {
        if ($sceneIds === []) {
            return [];
        }
        $rows = DB::table('plan_line_audios')
            ->whereIn('scene_id', $sceneIds)
            ->where('voice_key', $voiceKey)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $view = self::row((array) $row);
            $out[$view->sceneId.':'.$view->lineRef] = $view;
        }

        return $out;
    }

    public function find(string $audioId): ?LineAudioRow
    {
        $row = DB::table('plan_line_audios')->where('id', $audioId)->first();

        return $row === null ? null : self::row((array) $row);
    }

    public function put(PlanSceneId $sceneId, string $lineRef, string $voiceKey, string $format, string $bytes, ?int $durationMs, ?string $costUsd): ?LineAudioRow
    {
        $id = Ulid::generate();
        $path = sprintf('plan-audio/%s/%s-%s.%s', $sceneId->value, $lineRef, substr(sha1($voiceKey), 0, 12), $format);
        $userId = DB::table('plan_scenes')->where('id', $sceneId->value)->value('user_id');

        $this->disks->disk($this->disk)->put($path, $bytes);
        $inserted = DB::table('plan_line_audios')->insertOrIgnore([
            'id' => $id,
            'scene_id' => $sceneId->value,
            'user_id' => (string) $userId,
            'line_ref' => $lineRef,
            'voice_key' => $voiceKey,
            'format' => $format,
            'path' => $path,
            'bytes' => strlen($bytes),
            'duration_ms' => $durationMs,
            'cost_usd' => $costUsd,
            'created_at' => now(),
        ]);
        if ($inserted === 0) {
            return null;
        }

        return new LineAudioRow($id, $sceneId->value, $lineRef, $voiceKey, $format, $path, $durationMs);
    }

    public function read(LineAudioRow $row): ?string
    {
        $disk = $this->disks->disk($this->disk);

        return $disk->exists($row->path) ? $disk->get($row->path) : null;
    }

    /** @param array<string, mixed> $row */
    private static function row(array $row): LineAudioRow
    {
        return new LineAudioRow(
            (string) $row['id'], (string) $row['scene_id'], (string) $row['line_ref'], (string) $row['voice_key'],
            (string) $row['format'], (string) $row['path'], $row['duration_ms'] === null ? null : (int) $row['duration_ms'],
        );
    }
}
