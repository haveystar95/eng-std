<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\RescueAudioStore;
use Illuminate\Contracts\Filesystem\Factory as Disks;

/**
 * The rescue kit's sound on the plan's private audio disk (`plan.audio_disk`), under `plan-audio/rescue/`: the bytes as
 * `<key>.<format>` and beside them `<key>.json` — the format, the voice, how long it sounds and its bill (characters,
 * credits, price, the vendor's request id). No table: a line of the kit is found by its key alone.
 */
final class DiskRescueAudioStore implements RescueAudioStore
{
    private const DIR = 'plan-audio/rescue';

    public function __construct(private readonly Disks $disks, private readonly string $disk) {}

    public function has(string $key): bool
    {
        return self::valid($key) && $this->disks->disk($this->disk)->exists(self::meta($key));
    }

    public function put(string $key, SpokenAudio $audio): void
    {
        if (! self::valid($key) || $this->has($key)) {
            return;
        }
        $disk = $this->disks->disk($this->disk);
        $disk->put(self::DIR."/{$key}.{$audio->format}", $audio->bytes);
        $disk->put(self::meta($key), (string) json_encode([
            'format' => $audio->format,
            'voice_key' => $audio->voiceKey,
            'duration_ms' => $audio->durationMs,
            'characters' => $audio->characters,
            'credits' => $audio->credits,
            'cost_usd' => $audio->costUsd,
            'request_id' => $audio->requestId,
            'created_at' => now()->toAtomString(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    public function read(string $key): ?SpokenAudio
    {
        if (! $this->has($key)) {
            return null;
        }
        $disk = $this->disks->disk($this->disk);
        $meta = json_decode((string) $disk->get(self::meta($key)), true);
        $format = is_array($meta) && is_string($meta['format'] ?? null) ? $meta['format'] : 'mp3';
        $path = self::DIR."/{$key}.{$format}";
        if (! is_array($meta) || ! $disk->exists($path)) {
            return null;
        }

        return new SpokenAudio(
            bytes: (string) $disk->get($path),
            format: $format,
            voiceKey: (string) ($meta['voice_key'] ?? ''),
            durationMs: isset($meta['duration_ms']) ? (int) $meta['duration_ms'] : null,
            characters: (int) ($meta['characters'] ?? 0),
            credits: (int) ($meta['credits'] ?? 0),
            costUsd: (string) ($meta['cost_usd'] ?? '0.000000'),
            requestId: isset($meta['request_id']) ? (string) $meta['request_id'] : null,
        );
    }

    /** A key is the kit's sha1 — nothing else reaches the disk's paths. */
    private static function valid(string $key): bool
    {
        return preg_match('/^[0-9a-f]{40}$/', $key) === 1;
    }

    private static function meta(string $key): string
    {
        return self::DIR."/{$key}.json";
    }
}
