<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Factory as Disks;
use Illuminate\Support\Facades\DB;

/**
 * Bytes on the configured private disk under `plan-audio/<scene>/`, one row per (scene, line reference, voice), with
 * the line's bill: its characters, the credits the vendor debited and their price, the vendor's request id (TTS-2). The
 * unique constraint is the idempotency: a second `put` for a line this voice already has inserts no row — and writes no
 * file: the file behind an address a phone already cached must stay the file it downloaded.
 */
final class EloquentLineAudioStore implements LineAudioStore
{
    public function __construct(private readonly Disks $disks, private readonly string $disk) {}

    public function forScenes(array $sceneIds, array $voiceKeys): array
    {
        if ($sceneIds === [] || $voiceKeys === []) {
            return [];
        }
        $rows = DB::table('plan_line_audios')
            ->whereIn('scene_id', $sceneIds)
            ->whereIn('voice_key', $voiceKeys)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $view = self::row((array) $row);
            $out[$view->sceneId.':'.$view->lineRef.':'.$view->voiceKey] = $view;
        }

        return $out;
    }

    public function find(string $audioId): ?LineAudioRow
    {
        $row = DB::table('plan_line_audios')->where('id', $audioId)->first();

        return $row === null ? null : self::row((array) $row);
    }

    public function ofScene(PlanSceneId $sceneId): array
    {
        $out = [];
        foreach (DB::table('plan_line_audios')->where('scene_id', $sceneId->value)->orderBy('line_ref')->get() as $row) {
            $out[] = self::row((array) $row);
        }

        return $out;
    }

    public function drop(LineAudioRow $row): void
    {
        $this->disks->disk($this->disk)->delete($row->path);
        DB::table('plan_line_audios')->where('id', $row->id)->delete();
    }

    public function put(PlanSceneId $sceneId, string $lineRef, SpokenAudio $audio): ?LineAudioRow
    {
        $exists = DB::table('plan_line_audios')
            ->where('scene_id', $sceneId->value)->where('line_ref', $lineRef)->where('voice_key', $audio->voiceKey)
            ->exists();
        if ($exists) {
            return null;
        }
        $id = Ulid::generate();
        $path = sprintf('plan-audio/%s/%s-%s.%s', $sceneId->value, $lineRef, substr(sha1($audio->voiceKey), 0, 12), $audio->format);
        $userId = DB::table('plan_scenes')->where('id', $sceneId->value)->value('user_id');

        $this->disks->disk($this->disk)->put($path, $audio->bytes);
        $inserted = DB::table('plan_line_audios')->insertOrIgnore([
            'id' => $id,
            'scene_id' => $sceneId->value,
            'user_id' => (string) $userId,
            'line_ref' => $lineRef,
            'voice_key' => $audio->voiceKey,
            'format' => $audio->format,
            'path' => $path,
            'bytes' => strlen($audio->bytes),
            'duration_ms' => $audio->durationMs,
            'characters' => $audio->characters,
            'cost_usd' => $audio->costUsd,
            'credits' => $audio->credits,
            'request_id' => $audio->requestId,
            'created_at' => now(),
        ]);
        if ($inserted === 0) {
            return null;
        }

        return new LineAudioRow($id, $sceneId->value, $lineRef, $audio->voiceKey, $audio->format, $path, $audio->durationMs);
    }

    public function read(LineAudioRow $row): ?string
    {
        $disk = $this->disks->disk($this->disk);

        return $disk->exists($row->path) ? $disk->get($row->path) : null;
    }

    public function creditsSince(DateTimeImmutable $since): int
    {
        return (int) DB::table('plan_line_audios')->where('created_at', '>=', $since)->sum('credits');
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
